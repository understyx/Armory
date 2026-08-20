<?php

namespace App\Tests\Service;

use App\Entity\WowItem;
use App\Repository\WowItemRepository;
use App\Service\ItemIconResolverService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class ItemIconResolverServiceTest extends TestCase
{
    public function testResolveIconReturnsExistingIconFromDatabase(): void
    {
        $item = new WowItem();
        $item->setItemId(51543);
        $item->setIcon('inv_helmet_98');

        $repo = $this->createMock(WowItemRepository::class);
        $repo->method('findByItemId')->with(51543)->willReturn($item);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');

        $service = new ItemIconResolverService($repo, $em);
        $icon = $service->resolveIcon(51543);

        $this->assertEquals('inv_helmet_98', $icon);
    }

    public function testResolveIconFetchesFromApiWhenMissingAndSavesToDb(): void
    {
        $item = new WowItem();
        $item->setItemId(49623);
        $item->setIcon(null);

        $repo = $this->createMock(WowItemRepository::class);
        $repo->method('findByItemId')->with(49623)->willReturn($item);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist')->with($item);
        $em->expects($this->once())->method('flush');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->with(false)->willReturn([
            'name' => 'Shadowmourne',
            'icon' => 'inv_axe_113',
        ]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')
            ->with('GET', 'https://nether.wowhead.com/wotlk/tooltip/item/49623')
            ->willReturn($response);

        $service = new ItemIconResolverService($repo, $em, $httpClient);
        $icon = $service->resolveIcon(49623);

        $this->assertEquals('inv_axe_113', $icon);
        $this->assertEquals('inv_axe_113', $item->getIcon());
    }

    public function testResolveIconsBulkProcessesMissingIcons(): void
    {
        $item1 = new WowItem();
        $item1->setItemId(101);
        $item1->setIcon('inv_chest_cloth_01');

        $item2 = new WowItem();
        $item2->setItemId(102);
        $item2->setIcon(null);

        $repo = $this->createMock(WowItemRepository::class);
        $repo->method('findByItemIds')->with([101, 102])->willReturn([
            101 => $item1,
            102 => $item2,
        ]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->with(false)->willReturn([
            'icon' => 'inv_boots_01',
        ]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($response);

        $service = new ItemIconResolverService($repo, $em, $httpClient);
        $result = $service->resolveIconsBulk([101, 102]);

        $this->assertEquals([
            101 => 'inv_chest_cloth_01',
            102 => 'inv_boots_01',
        ], $result);
        $this->assertEquals('inv_boots_01', $item2->getIcon());
    }
}
