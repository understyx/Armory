<?php

namespace App\Tests\Repository;

use App\Entity\WowItem;
use App\Repository\WowItemRepository;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

final class WowItemRepositoryGlyphTest extends TestCase
{
    private ?EntityManager $entityManager = null;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required for the in-memory repository integration test.');
        }

        $config = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 2).'/src/Entity'], true);
        $config->enableNativeLazyObjects(true);
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config);
        $this->entityManager = new EntityManager($connection, $config);
        (new SchemaTool($this->entityManager))->createSchema([
            $this->entityManager->getClassMetadata(WowItem::class),
        ]);
    }

    protected function tearDown(): void
    {
        $this->entityManager?->getConnection()->close();
    }

    public function testResolvesExactGlyphNamesToTheLowestClassSixteenItemId(): void
    {
        $this->entityManager->persist($this->glyph(44432, 'Glyph of Raise Dead', 16));
        $this->entityManager->persist($this->glyph(43673, 'Glyph of Raise Dead', 16));
        $this->entityManager->persist($this->glyph(100, 'Glyph of Missing Name', 16));
        $this->entityManager->persist($this->glyph(50, 'Glyph of Raise Dead', 15));
        $this->entityManager->flush();

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->entityManager);
        $repository = new WowItemRepository($registry);

        self::assertSame(
            ['Glyph of Raise Dead' => 43673],
            $repository->findGlyphItemIdsByNames(['Glyph of Raise Dead', 'Glyph of Missing']),
        );
    }

    private function glyph(int $id, string $name, int $class): WowItem
    {
        return (new WowItem())
            ->setItemId($id)
            ->setName($name)
            ->setClass($class);
    }
}
