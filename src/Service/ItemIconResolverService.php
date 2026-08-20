<?php

namespace App\Service;

use App\Entity\WowItem;
use App\Repository\WowItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

class ItemIconResolverService
{
    private HttpClientInterface $httpClient;

    public function __construct(
        private readonly WowItemRepository $wowItemRepository,
        private readonly EntityManagerInterface $entityManager,
        ?HttpClientInterface $httpClient = null,
        private readonly ?LoggerInterface $logger = null
    ) {
        $this->httpClient = $httpClient ?? HttpClient::create();
    }

    /**
     * Resolves and returns the icon name for a given item ID.
     * If the item is in the DB and has no icon, updates and persists the icon.
     */
    public function resolveIcon(int $itemId): ?string
    {
        $item = $this->wowItemRepository->findByItemId($itemId);
        if ($item !== null && !empty($item->getIcon())) {
            return $item->getIcon();
        }

        $icon = $this->fetchIconFromApi($itemId);
        if ($icon !== null) {
            if ($item !== null) {
                $item->setIcon($icon);
                $this->entityManager->persist($item);
                $this->entityManager->flush();
            } else {
                // If item does not exist in DB yet, create a skeletal record with icon
                $newItem = new WowItem();
                $newItem->setItemId($itemId);
                $newItem->setIcon($icon);
                $this->entityManager->persist($newItem);
                $this->entityManager->flush();
            }
        }

        return $icon;
    }

    /**
     * Resolves missing icons for an array of item IDs in bulk.
     * Returns an array mapping item ID => icon name.
     *
     * @param int[] $itemIds
     * @return array<int, ?string>
     */
    public function resolveIconsBulk(array $itemIds): array
    {
        $result = [];
        $itemsToFetch = [];

        $existingItems = $this->wowItemRepository->findByItemIds($itemIds);

        foreach ($itemIds as $id) {
            if (isset($existingItems[$id]) && !empty($existingItems[$id]->getIcon())) {
                $result[$id] = $existingItems[$id]->getIcon();
            } else {
                $itemsToFetch[] = $id;
            }
        }

        if (empty($itemsToFetch)) {
            return $result;
        }

        $flushNeeded = false;
        foreach ($itemsToFetch as $itemId) {
            $icon = $this->fetchIconFromApi($itemId);
            $result[$itemId] = $icon;

            if ($icon !== null) {
                $item = $existingItems[$itemId] ?? null;
                if ($item !== null) {
                    $item->setIcon($icon);
                    $this->entityManager->persist($item);
                    $flushNeeded = true;
                } else {
                    $newItem = new WowItem();
                    $newItem->setItemId($itemId);
                    $newItem->setIcon($icon);
                    $this->entityManager->persist($newItem);
                    $flushNeeded = true;
                }
            }
        }

        if ($flushNeeded) {
            try {
                $this->entityManager->flush();
            } catch (Throwable $e) {
                if ($this->logger) {
                    $this->logger->error(sprintf("Failed to flush resolved item icons: %s", $e->getMessage()));
                }
            }
        }

        return $result;
    }

    /**
     * Fetches icon string from Wowhead API, falling back to CavernOfTime.
     */
    private function fetchIconFromApi(int $itemId): ?string
    {
        // 1. Try Wowhead WotLK API
        try {
            $url = sprintf('https://nether.wowhead.com/wotlk/tooltip/item/%d', $itemId);
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => 4,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                ],
            ]);

            if ($response->getStatusCode() === 200) {
                $data = $response->toArray(false);
                if (!empty($data['icon'])) {
                    return strtolower(trim($data['icon']));
                }
            }
        } catch (Throwable $e) {
            if ($this->logger) {
                $this->logger->warning(sprintf("Failed fetching Wowhead icon for item #%d: %s", $itemId, $e->getMessage()));
            }
        }

        // 2. Fallback to CavernOfTime
        try {
            $url = sprintf('https://wotlk.cavernoftime.com/item=%d&xml', $itemId);
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => 4,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                ],
            ]);

            if ($response->getStatusCode() === 200) {
                $content = $response->getContent(false);
                if (preg_match("/icon:\s*'([^']+)'/", $content, $matches)) {
                    return strtolower(trim($matches[1]));
                }
                if (preg_match('/<icon[^>]*>([^<]+)<\/icon>/', $content, $matches)) {
                    return strtolower(trim($matches[1]));
                }
            }
        } catch (Throwable $e) {
            if ($this->logger) {
                $this->logger->warning(sprintf("Failed fetching CavernOfTime icon fallback for item #%d: %s", $itemId, $e->getMessage()));
            }
        }

        return null;
    }
}
