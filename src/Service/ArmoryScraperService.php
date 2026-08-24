<?php
// src/Service/ArmoryScraperService.php

namespace App\Service;

use App\Enum\ItemTypes; // Use the new PHP Enum
use Psr\Log\LoggerInterface; // For logging
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use DOMDocument;
use DOMXPath;
use Throwable; // Catch any PHP errors/exceptions

// Define a custom exception for parsing errors
class WarmaneParserException extends \RuntimeException
{
}

class ArmoryScraperService
{
    private const RAID_ACHIEVEMENT_GROUPS = [
        'icc_rs' => 'ICC + RS',
        'toc_onyxia' => 'ToC + Onyxia',
        'ulduar' => 'Ulduar',
        'naxx_eoe_os' => 'Naxx + EoE + OS',
    ];

    private const RAID_ACHIEVEMENT_CATEGORIES = [
        14922 => [
            'raidSize' => 10,
            'achievements' => [
                4817 => ['group' => 'icc_rs', 'raid' => 'Ruby Sanctum', 'section' => 'Halion', 'difficulty' => 'normal', 'sort' => 60],
                4818 => ['group' => 'icc_rs', 'raid' => 'Ruby Sanctum', 'section' => 'Halion', 'difficulty' => 'heroic', 'sort' => 60],
                4396 => ['group' => 'toc_onyxia', 'raid' => "Onyxia's Lair", 'section' => 'Onyxia', 'difficulty' => 'normal', 'sort' => 20],
                562 => ['group' => 'naxx_eoe_os', 'raid' => 'Naxxramas', 'section' => 'Arachnid Quarter', 'difficulty' => 'normal', 'sort' => 10],
                564 => ['group' => 'naxx_eoe_os', 'raid' => 'Naxxramas', 'section' => 'Construct Quarter', 'difficulty' => 'normal', 'sort' => 20],
                566 => ['group' => 'naxx_eoe_os', 'raid' => 'Naxxramas', 'section' => 'Plague Quarter', 'difficulty' => 'normal', 'sort' => 30],
                568 => ['group' => 'naxx_eoe_os', 'raid' => 'Naxxramas', 'section' => 'Military Quarter', 'difficulty' => 'normal', 'sort' => 40],
                572 => ['group' => 'naxx_eoe_os', 'raid' => 'Naxxramas', 'section' => 'Sapphiron', 'difficulty' => 'normal', 'sort' => 50],
                574 => ['group' => 'naxx_eoe_os', 'raid' => 'Naxxramas', 'section' => "Kel'Thuzad", 'difficulty' => 'normal', 'sort' => 60],
                622 => ['group' => 'naxx_eoe_os', 'raid' => 'Eye of Eternity', 'section' => 'Malygos', 'difficulty' => 'normal', 'sort' => 70],
                1876 => ['group' => 'naxx_eoe_os', 'raid' => 'Obsidian Sanctum', 'section' => 'Sartharion', 'difficulty' => 'normal', 'sort' => 80],
            ],
        ],
        14923 => [
            'raidSize' => 25,
            'achievements' => [
                4815 => ['group' => 'icc_rs', 'raid' => 'Ruby Sanctum', 'section' => 'Halion', 'difficulty' => 'normal', 'sort' => 60],
                4816 => ['group' => 'icc_rs', 'raid' => 'Ruby Sanctum', 'section' => 'Halion', 'difficulty' => 'heroic', 'sort' => 60],
                4397 => ['group' => 'toc_onyxia', 'raid' => "Onyxia's Lair", 'section' => 'Onyxia', 'difficulty' => 'normal', 'sort' => 20],
                563 => ['group' => 'naxx_eoe_os', 'raid' => 'Naxxramas', 'section' => 'Arachnid Quarter', 'difficulty' => 'normal', 'sort' => 10],
                565 => ['group' => 'naxx_eoe_os', 'raid' => 'Naxxramas', 'section' => 'Construct Quarter', 'difficulty' => 'normal', 'sort' => 20],
                567 => ['group' => 'naxx_eoe_os', 'raid' => 'Naxxramas', 'section' => 'Plague Quarter', 'difficulty' => 'normal', 'sort' => 30],
                569 => ['group' => 'naxx_eoe_os', 'raid' => 'Naxxramas', 'section' => 'Military Quarter', 'difficulty' => 'normal', 'sort' => 40],
                573 => ['group' => 'naxx_eoe_os', 'raid' => 'Naxxramas', 'section' => 'Sapphiron', 'difficulty' => 'normal', 'sort' => 50],
                575 => ['group' => 'naxx_eoe_os', 'raid' => 'Naxxramas', 'section' => "Kel'Thuzad", 'difficulty' => 'normal', 'sort' => 60],
                623 => ['group' => 'naxx_eoe_os', 'raid' => 'Eye of Eternity', 'section' => 'Malygos', 'difficulty' => 'normal', 'sort' => 70],
                625 => ['group' => 'naxx_eoe_os', 'raid' => 'Obsidian Sanctum', 'section' => 'Sartharion', 'difficulty' => 'normal', 'sort' => 80],
            ],
        ],
        14961 => [
            'raidSize' => 10,
            'achievements' => [
                2886 => ['group' => 'ulduar', 'raid' => 'Ulduar', 'section' => 'The Siege', 'difficulty' => 'normal', 'sort' => 10],
                2888 => ['group' => 'ulduar', 'raid' => 'Ulduar', 'section' => 'The Antechamber', 'difficulty' => 'normal', 'sort' => 20],
                2890 => ['group' => 'ulduar', 'raid' => 'Ulduar', 'section' => 'The Keepers', 'difficulty' => 'normal', 'sort' => 30],
                2892 => ['group' => 'ulduar', 'raid' => 'Ulduar', 'section' => 'Descent into Madness', 'difficulty' => 'normal', 'sort' => 40],
                3036 => ['group' => 'ulduar', 'raid' => 'Ulduar', 'section' => 'Algalon', 'difficulty' => 'normal', 'sort' => 50],
            ],
        ],
        14962 => [
            'raidSize' => 25,
            'achievements' => [
                2887 => ['group' => 'ulduar', 'raid' => 'Ulduar', 'section' => 'The Siege', 'difficulty' => 'normal', 'sort' => 10],
                2889 => ['group' => 'ulduar', 'raid' => 'Ulduar', 'section' => 'The Antechamber', 'difficulty' => 'normal', 'sort' => 20],
                2891 => ['group' => 'ulduar', 'raid' => 'Ulduar', 'section' => 'The Keepers', 'difficulty' => 'normal', 'sort' => 30],
                2893 => ['group' => 'ulduar', 'raid' => 'Ulduar', 'section' => 'Descent into Madness', 'difficulty' => 'normal', 'sort' => 40],
                3037 => ['group' => 'ulduar', 'raid' => 'Ulduar', 'section' => 'Algalon', 'difficulty' => 'normal', 'sort' => 50],
            ],
        ],
        15001 => [
            'raidSize' => 10,
            'achievements' => [
                3917 => ['group' => 'toc_onyxia', 'raid' => 'Trial of the Crusader', 'section' => 'Full clear', 'difficulty' => 'normal', 'sort' => 10],
                3918 => ['group' => 'toc_onyxia', 'raid' => 'Trial of the Crusader', 'section' => 'Full clear', 'difficulty' => 'heroic', 'sort' => 10],
            ],
        ],
        15002 => [
            'raidSize' => 25,
            'achievements' => [
                3916 => ['group' => 'toc_onyxia', 'raid' => 'Trial of the Crusader', 'section' => 'Full clear', 'difficulty' => 'normal', 'sort' => 10],
                3812 => ['group' => 'toc_onyxia', 'raid' => 'Trial of the Crusader', 'section' => 'Full clear', 'difficulty' => 'heroic', 'sort' => 10],
            ],
        ],
        15041 => [
            'raidSize' => 10,
            'achievements' => [
                4531 => ['group' => 'icc_rs', 'raid' => 'Icecrown Citadel', 'section' => 'Lower Spire', 'difficulty' => 'normal', 'sort' => 10],
                4628 => ['group' => 'icc_rs', 'raid' => 'Icecrown Citadel', 'section' => 'Lower Spire', 'difficulty' => 'heroic', 'sort' => 10],
                4528 => ['group' => 'icc_rs', 'raid' => 'Icecrown Citadel', 'section' => 'The Plagueworks', 'difficulty' => 'normal', 'sort' => 20],
                4629 => ['group' => 'icc_rs', 'raid' => 'Icecrown Citadel', 'section' => 'The Plagueworks', 'difficulty' => 'heroic', 'sort' => 20],
                4529 => ['group' => 'icc_rs', 'raid' => 'Icecrown Citadel', 'section' => 'The Crimson Hall', 'difficulty' => 'normal', 'sort' => 30],
                4630 => ['group' => 'icc_rs', 'raid' => 'Icecrown Citadel', 'section' => 'The Crimson Hall', 'difficulty' => 'heroic', 'sort' => 30],
                4527 => ['group' => 'icc_rs', 'raid' => 'Icecrown Citadel', 'section' => 'The Frostwing Halls', 'difficulty' => 'normal', 'sort' => 40],
                4631 => ['group' => 'icc_rs', 'raid' => 'Icecrown Citadel', 'section' => 'The Frostwing Halls', 'difficulty' => 'heroic', 'sort' => 40],
                4530 => ['group' => 'icc_rs', 'raid' => 'Icecrown Citadel', 'section' => 'The Lich King', 'difficulty' => 'normal', 'sort' => 50],
                4583 => ['group' => 'icc_rs', 'raid' => 'Icecrown Citadel', 'section' => 'The Lich King', 'difficulty' => 'heroic', 'sort' => 50],
            ],
        ],
        15042 => [
            'raidSize' => 25,
            'achievements' => [
                4604 => ['group' => 'icc_rs', 'raid' => 'Icecrown Citadel', 'section' => 'Lower Spire', 'difficulty' => 'normal', 'sort' => 10],
                4632 => ['group' => 'icc_rs', 'raid' => 'Icecrown Citadel', 'section' => 'Lower Spire', 'difficulty' => 'heroic', 'sort' => 10],
                4605 => ['group' => 'icc_rs', 'raid' => 'Icecrown Citadel', 'section' => 'The Plagueworks', 'difficulty' => 'normal', 'sort' => 20],
                4633 => ['group' => 'icc_rs', 'raid' => 'Icecrown Citadel', 'section' => 'The Plagueworks', 'difficulty' => 'heroic', 'sort' => 20],
                4606 => ['group' => 'icc_rs', 'raid' => 'Icecrown Citadel', 'section' => 'The Crimson Hall', 'difficulty' => 'normal', 'sort' => 30],
                4634 => ['group' => 'icc_rs', 'raid' => 'Icecrown Citadel', 'section' => 'The Crimson Hall', 'difficulty' => 'heroic', 'sort' => 30],
                4607 => ['group' => 'icc_rs', 'raid' => 'Icecrown Citadel', 'section' => 'The Frostwing Halls', 'difficulty' => 'normal', 'sort' => 40],
                4635 => ['group' => 'icc_rs', 'raid' => 'Icecrown Citadel', 'section' => 'The Frostwing Halls', 'difficulty' => 'heroic', 'sort' => 40],
                4597 => ['group' => 'icc_rs', 'raid' => 'Icecrown Citadel', 'section' => 'The Lich King', 'difficulty' => 'normal', 'sort' => 50],
                4584 => ['group' => 'icc_rs', 'raid' => 'Icecrown Citadel', 'section' => 'The Lich King', 'difficulty' => 'heroic', 'sort' => 50],
            ],
        ],
    ];

    private HttpClientInterface $httpClient;
    private ?LoggerInterface $logger;
    private ?ItemDatabaseService $itemDatabaseService;

    public function __construct(
        ?ItemDatabaseService $itemDatabaseService = null,
        ?LoggerInterface $logger = null,
        ?HttpClientInterface $httpClient = null
    ) {
        $this->itemDatabaseService = $itemDatabaseService;
        $this->logger = $logger;
        $this->httpClient = $httpClient ?? HttpClient::create();
    }

    /**
     * Helper to log messages.
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->$level($message, $context);
        } else {
            error_log(strtoupper($level) . ': ' . $message . (empty($context) ? '' : ' ' . json_encode($context)));
        }
    }

    /**
     * Helper to fetch item details from database service.
     */
    public function getItemDetails(int $itemId): ?array
    {
        if ($this->itemDatabaseService !== null) {
            return $this->itemDatabaseService->getItem($itemId);
        }
        return null;
    }

    /**
     * Checks if response HTML indicates a Cloudflare block (1015 / Ray ID / Rate limit).
     */
    public function isCloudflareBlock(string $html): bool
    {
        if (empty($html)) {
            return false;
        }

        return str_contains($html, 'Error 1015')
            || str_contains($html, 'Ray ID:')
            || (str_contains($html, 'Cloudflare') && str_contains($html, 'Access denied'))
            || str_contains($html, 'You are being rate limited');
    }

    /**
     * Fetches URL with exponential backoff retries for rate limits / Cloudflare 1015.
     */
    public function fetchWithRetry(string $url, array $retryDelays = [1, 2, 4, 8, 16], ?callable $sleepFunc = null): ?string
    {
        $maxRetries = count($retryDelays);
        $headers = [
            'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64; rv:133.0) Gecko/20100101 Firefox/133.0',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.5',
            'Referer' => 'http://www.warmane.com/',
            'Connection' => 'keep-alive',
            'Upgrade-Insecure-Requests' => '1',
            'Sec-Fetch-Dest' => 'document',
            'Sec-Fetch-Mode' => 'navigate',
            'Sec-Fetch-Site' => 'same-origin',
            'Sec-Fetch-User' => '?1',
        ];

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = $this->httpClient->request('GET', $url, [
                    'headers' => $headers,
                    'timeout' => 10,
                ]);

                $statusCode = $response->getStatusCode();
                $content = $response->getContent(false);

                if ($statusCode === 200 && !$this->isCloudflareBlock($content)) {
                    return $content;
                }

                // If character not found (200 status with error-page div), return content without retry
                if ($statusCode === 200 && !$this->checkCharacterExists($content)) {
                    return $content;
                }

                $this->log('warning', sprintf(
                    "Armory fetch attempt %d failed for %s. Status: %d, Cloudflare: %s",
                    $attempt + 1,
                    $url,
                    $statusCode,
                    $this->isCloudflareBlock($content) ? 'yes' : 'no'
                ));

                if ($attempt < $maxRetries) {
                    $delay = $retryDelays[$attempt] ?? 1;
                    if ($sleepFunc) {
                        $sleepFunc($delay);
                    } else {
                        sleep($delay);
                    }
                }
            } catch (ExceptionInterface $e) {
                $this->log('error', sprintf("HTTP Client error on attempt %d fetching %s: %s", $attempt + 1, $url, $e->getMessage()), ['exception' => $e]);
                if ($attempt < $maxRetries) {
                    $delay = $retryDelays[$attempt] ?? 1;
                    if ($sleepFunc) {
                        $sleepFunc($delay);
                    } else {
                        sleep($delay);
                    }
                }
            }
        }

        return null;
    }

    /**
     * Fetches HTML content from Warmane Armory.
     */
    public function fetchArmoryHtml(string $character, string $realm, string $dataType): ?string
    {
        $url = sprintf(
            "https://armory.warmane.com/character/%s/%s/%s",
            ucfirst($character),
            ucfirst($realm),
            $dataType
        );

        return $this->fetchWithRetry($url);
    }

    /**
     * Fetches the HTML fragment returned by Warmane's achievements category request.
     */
    public function fetchAchievementCategoryHtml(string $character, string $realm, int $category): ?string
    {
        if (!isset(self::RAID_ACHIEVEMENT_CATEGORIES[$category])) {
            throw new \InvalidArgumentException(sprintf('Unsupported raid achievement category: %d', $category));
        }

        $url = sprintf(
            'https://armory.warmane.com/character/%s/%s/achievements',
            rawurlencode(ucfirst(trim($character))),
            rawurlencode(ucfirst(trim($realm))),
        );

        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64; rv:133.0) Gecko/20100101 Firefox/133.0',
                    'Accept' => 'application/json, text/javascript, */*; q=0.01',
                    'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
                    'Referer' => $url,
                    'X-Requested-With' => 'XMLHttpRequest',
                ],
                'body' => ['category' => (string) $category],
                'timeout' => 10,
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->log('warning', sprintf('Warmane achievement category %d returned HTTP %d.', $category, $response->getStatusCode()));
                return null;
            }

            $payload = json_decode($response->getContent(false), true);
            return is_array($payload) && is_string($payload['content'] ?? null)
                ? $payload['content']
                : null;
        } catch (ExceptionInterface $e) {
            $this->log('error', sprintf('Failed to fetch Warmane achievement category %d: %s', $category, $e->getMessage()), ['exception' => $e]);
            return null;
        }
    }

    /**
     * @return array{raidSize: int, category: int, achievements: list<array<string, mixed>>}
     */
    public function extractRaidAchievements(string $html, int $category): array
    {
        $categoryConfig = self::RAID_ACHIEVEMENT_CATEGORIES[$category] ?? null;
        if ($categoryConfig === null) {
            throw new \InvalidArgumentException(sprintf('Unsupported raid achievement category: %d', $category));
        }

        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        $xpath = new DOMXPath($dom);
        $achievements = [];

        foreach ($xpath->query("//div[contains(concat(' ', normalize-space(@class), ' '), ' achievement ')]") as $node) {
            if (!$node instanceof \DOMElement || !preg_match('/^ach(\d+)$/', $node->getAttribute('id'), $matches)) {
                continue;
            }

            $achievementId = (int) $matches[1];
            $achievementConfig = $categoryConfig['achievements'][$achievementId] ?? null;
            if ($achievementConfig === null) {
                continue;
            }

            $readText = static function (DOMXPath $xpath, \DOMElement $node, string $class): ?string {
                $result = $xpath->query(".//div[contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')]", $node)->item(0);
                if ($result === null) {
                    return null;
                }

                $value = trim(preg_replace('/\s+/', ' ', $result->textContent) ?? '');
                return $value !== '' ? $value : null;
            };

            $iconNode = $xpath->query(".//div[contains(concat(' ', normalize-space(@class), ' '), ' icon ')]//img", $node)->item(0);
            $iconUrl = $iconNode instanceof \DOMElement ? trim($iconNode->getAttribute('src')) : null;
            if ($iconUrl !== null && str_starts_with($iconUrl, 'http://')) {
                $iconUrl = 'https://' . substr($iconUrl, 7);
            }

            $className = ' ' . preg_replace('/\s+/', ' ', trim($node->getAttribute('class'))) . ' ';
            $earnedText = $readText($xpath, $node, 'date');
            $achievements[] = [
                'id' => $achievementId,
                'group' => $achievementConfig['group'],
                'raid' => $achievementConfig['raid'],
                'section' => $achievementConfig['section'],
                'difficulty' => $achievementConfig['difficulty'],
                'sort' => $achievementConfig['sort'],
                'title' => $readText($xpath, $node, 'title') ?? $achievementConfig['section'],
                'description' => $readText($xpath, $node, 'description') ?? '',
                'points' => (int) ($readText($xpath, $node, 'points') ?? 0),
                'iconUrl' => $iconUrl,
                'earned' => !str_contains($className, ' locked '),
                'earnedDate' => $earnedText !== null ? preg_replace('/^Earned\s+/i', '', $earnedText) : null,
            ];
        }

        return [
            'raidSize' => $categoryConfig['raidSize'],
            'category' => $category,
            'achievements' => $achievements,
        ];
    }

    /**
     * Groups raid progression into display sections and hides a normal clear when
     * the corresponding heroic achievement has already been earned.
     *
     * @param list<array{raidSize: int, category: int, achievements: list<array<string, mixed>>}> $categoryResults
     * @return list<array{key: string, title: string, raidSizes: list<array{raidSize: int, achievements: list<array<string, mixed>>}>}>
     */
    public function groupRaidAchievements(array $categoryResults): array
    {
        $allAchievements = [];
        foreach ($categoryResults as $result) {
            foreach ($result['achievements'] ?? [] as $achievement) {
                $achievement['raidSize'] = (int) ($result['raidSize'] ?? 0);
                $allAchievements[] = $achievement;
            }
        }

        $earnedHeroicKeys = [];
        foreach ($allAchievements as $achievement) {
            if (($achievement['difficulty'] ?? null) === 'heroic' && ($achievement['earned'] ?? false)) {
                $earnedHeroicKeys[$this->raidAchievementPairKey($achievement)] = true;
            }
        }

        $grouped = [];
        foreach (self::RAID_ACHIEVEMENT_GROUPS as $key => $title) {
            $grouped[$key] = [
                'key' => $key,
                'title' => $title,
                'raidSizes' => [
                    10 => ['raidSize' => 10, 'achievements' => []],
                    25 => ['raidSize' => 25, 'achievements' => []],
                ],
            ];
        }

        foreach ($allAchievements as $achievement) {
            $groupKey = (string) ($achievement['group'] ?? '');
            $raidSize = (int) ($achievement['raidSize'] ?? 0);
            if (!isset($grouped[$groupKey]['raidSizes'][$raidSize])) {
                continue;
            }

            if (($achievement['difficulty'] ?? null) === 'normal'
                && isset($earnedHeroicKeys[$this->raidAchievementPairKey($achievement)])) {
                continue;
            }

            $grouped[$groupKey]['raidSizes'][$raidSize]['achievements'][] = $achievement;
        }

        foreach ($grouped as &$group) {
            foreach ($group['raidSizes'] as &$raidSizeGroup) {
                usort($raidSizeGroup['achievements'], static function (array $left, array $right): int {
                    $sortComparison = ((int) ($left['sort'] ?? 0)) <=> ((int) ($right['sort'] ?? 0));
                    if ($sortComparison !== 0) {
                        return $sortComparison;
                    }

                    return (($left['difficulty'] ?? '') === 'normal' ? 0 : 1)
                        <=> (($right['difficulty'] ?? '') === 'normal' ? 0 : 1);
                });
            }
            unset($raidSizeGroup);
            $group['raidSizes'] = array_values($group['raidSizes']);
        }
        unset($group);

        return array_values($grouped);
    }

    /** @param array<string, mixed> $achievement */
    private function raidAchievementPairKey(array $achievement): string
    {
        return implode('|', [
            (string) ($achievement['group'] ?? ''),
            (string) ($achievement['raid'] ?? ''),
            (string) ($achievement['section'] ?? ''),
            (string) ($achievement['raidSize'] ?? ''),
        ]);
    }

    /** Fetches a Warmane guild page. */
    public function fetchGuildHtml(string $guild, string $realm, string $dataType = 'summary'): ?string
    {
        $url = sprintf(
            'https://armory.warmane.com/guild/%s/%s/%s',
            rawurlencode(trim($guild)),
            rawurlencode(ucfirst(trim($realm))),
            $dataType,
        );

        return $this->fetchWithRetry($url);
    }

    public function checkGuildExists(string $html): bool
    {
        if ($html === '' || str_contains($html, 'The guild you are looking for does not exist')) {
            return false;
        }

        $dom = new DOMDocument();
        @$dom->loadHTML($html);

        return (new DOMXPath($dom))->query("//div[@id='guild-sheet']")->length > 0;
    }

    /**
     * @return array{
     *   name: string,
     *   faction: string|null,
     *   memberCount: int,
     *   pvePoints: int,
     *   members: list<array{name: string, race: string|null, class: string|null, faction: string|null, level: int, rank: string|null, achievementPoints: int, professions: list<string>}>
     * }
     */
    public function extractGuildSummary(string $html): array
    {
        if ($html === '') {
            throw new WarmaneParserException('Cannot parse an empty guild page.');
        }

        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        $nameNode = $xpath->query("//div[@id='guild-sheet']//div[contains(concat(' ', normalize-space(@class), ' '), ' name ')]")->item(0);
        $informationNode = $xpath->query("//div[@id='guild-sheet']//div[contains(concat(' ', normalize-space(@class), ' '), ' level-faction-realm ')]")->item(0);
        if ($nameNode === null || $informationNode === null) {
            throw new WarmaneParserException('Warmane returned an unexpected guild page.');
        }

        $information = preg_replace('/\s+/', ' ', trim($informationNode->textContent)) ?? '';
        preg_match('/\b(Horde|Alliance)\s+Guild\b/i', $information, $factionMatch);
        // DOM textContent does not insert whitespace for <br>, so this may be "members480".
        preg_match('/([\d,]+)\s+members/i', $information, $memberCountMatch);
        preg_match('/([\d,]+)\s+PVE\s+Points\b/i', $information, $pvePointsMatch);

        $headerIndexes = [];
        foreach ($xpath->query("//table[@id='data-table']/thead/tr/th") as $index => $header) {
            $headerIndexes[strtolower(trim($header->textContent))] = $index;
        }

        $members = [];
        foreach ($xpath->query("//tbody[@id='data-table-list']/tr") as $row) {
            $cells = $xpath->query('./td', $row);
            $cell = static fn(string $heading) => isset($headerIndexes[$heading]) ? $cells->item($headerIndexes[$heading]) : null;
            $nameCell = $cell('name');
            $characterLink = $nameCell ? $xpath->query(".//a[contains(@href, '/character/')]", $nameCell)->item(0) : null;
            if ($characterLink === null) {
                continue;
            }

            $imageAlt = static function (?\DOMNode $node) use ($xpath): ?string {
                $image = $node ? $xpath->query('.//img[@alt]', $node)->item(0) : null;
                return $image instanceof \DOMElement ? trim($image->getAttribute('alt')) ?: null : null;
            };
            $text = static fn(?\DOMNode $node): ?string => $node ? (trim($node->textContent) ?: null) : null;
            $professionNames = [];
            $professionCell = $cell('professions');
            if ($professionCell !== null) {
                foreach ($xpath->query('.//img[@alt]', $professionCell) as $image) {
                    if ($image instanceof \DOMElement && trim($image->getAttribute('alt')) !== '') {
                        $professionNames[] = trim($image->getAttribute('alt'));
                    }
                }
            }

            $members[] = [
                'name' => trim($characterLink->textContent),
                'race' => $imageAlt($cell('race')),
                'class' => $imageAlt($cell('class')),
                'faction' => $imageAlt($cell('faction')),
                'level' => (int) ($text($cell('level')) ?? 0),
                'rank' => $text($cell('rank')),
                'achievementPoints' => (int) str_replace(',', '', $text($cell('achievements points')) ?? '0'),
                'professions' => $professionNames,
            ];
        }

        return [
            'name' => trim($nameNode->textContent),
            'faction' => isset($factionMatch[1]) ? ucfirst(strtolower($factionMatch[1])) : null,
            'memberCount' => (int) str_replace(',', '', $memberCountMatch[1] ?? (string) count($members)),
            'pvePoints' => (int) str_replace(',', '', $pvePointsMatch[1] ?? '0'),
            'members' => $members,
        ];
    }

    /**
     * Checks if the HTML indicates a 'character not found' error.
     * Corresponds to `WarmaneParser::check_character_exists`.
     * Note: Returns true if character *exists*, false if *not found*.
     */
    public function checkCharacterExists(string $html): bool
    {
        if (empty($html)) {
            return false; // No HTML means character doesn't exist (or fetch failed)
        }
        try {
            $dom = new DOMDocument();
            @$dom->loadHTML($html);
            $xpath = new DOMXPath($dom);

            // Robust check: Look for the specific error div
            $errorDiv = $xpath->query("//div[contains(@class, 'error-page')]");
            if ($errorDiv->length > 0 && str_contains($errorDiv->item(0)->textContent, "does not exist")) {
                $this->log('warning', "Character not found message detected in error-page div.");
                return false;
            }
            // Fallback check if structure changes
            if (str_contains($html, "The character you are looking for does not exist")) {
                $this->log('warning', "Fallback 'character not found' text detected.");
                return false;
            }
        } catch (Throwable $e) {
            $this->log('error', "Error during character existence check: " . $e->getMessage());
            return false; // Assume character not found if parsing fails
        }
        return true; // Character seems to exist
    }


    /**
     * Parses the profile HTML to extract equipped item IDs, enchants, gems, and transmog.
     * Returns a flattened array of item objects, each with 'id', 'name', 'enchant', 'gems', 'transmog'.
     *
     * @param string $html The HTML content of the Armory profile.
     * @return array Returns an array of item objects. Example:
     *               [
     *                   {'id': 12345, 'name': 'Item Name', 'enchant': 3456, 'gems': [7890, 7891], 'transmog': 1122},
     *                   {'id': 12345, 'name': 'Item Name', 'enchant': null, 'gems': [], 'transmog': null}, // For a second ring/trinket
     *                   ...
     *               ]
     */
    public function extractEquippedItemsData(string $html): array
    {
        if (empty($html)) {
            $this->log('error', "Cannot parse empty HTML for items.");
            throw new WarmaneParserException("Cannot parse empty HTML for items.");
        }

        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);

        $equippedItems = []; // [{id: int, name: string, enchant: int|null, gems: [int], transmog: int|null}]

        $tagsWithRel = $xpath->query("//*[@rel]");
        if ($tagsWithRel->length === 0) {
            $this->log('warning', "No 'rel' tags found in HTML for item extraction.");
            return [];
        }

        foreach ($tagsWithRel as $tag) {
            $relValue = $tag->getAttribute('rel');
            $relParts = explode(' ', $relValue);

            $itemRelComponent = null;
            foreach ($relParts as $part) {
                if (str_starts_with($part, "item=")) {
                    $itemRelComponent = $part;
                    break;
                }
            }

            if ($itemRelComponent === null) {
                continue;
            }

            $components = explode('&', $itemRelComponent);
            $itemId = null;
            $enchantId = null;
            $gemIds = [];
            $transmogId = null;

            try {
                foreach ($components as $component) {
                    if (str_starts_with($component, "item=")) {
                        $itemId = (int)explode("=", $component)[1];
                    } elseif (str_starts_with($component, "ench=")) {
                        $enchantId = (int)explode("=", $component)[1];
                    } elseif (str_starts_with($component, "gems=")) {
                        $rawGems = explode("=", $component)[1];
                        $gemIds = array_map('intval', explode(":", $rawGems));

                        // Trailing zeroes are unused socket fields, but a zero before a
                        // later gem represents an actual empty socket and must retain
                        // its position (for example, 0:3375:0 becomes [0, 3375]).
                        while ($gemIds !== [] && end($gemIds) === 0) {
                            array_pop($gemIds);
                        }
                    } elseif (str_starts_with($component, "transmog=")) {
                        $transmogId = (int)explode("=", $component)[1];
                    }
                }

                if ($itemId !== null) {
                    $itemDetails = $this->getItemDetails($itemId);
                    $itemName = $itemDetails['name'] ?? null;

                    $equippedItems[] = [
                        "id" => $itemId,
                        "name" => $itemName, // Added item name
                        "enchant" => $enchantId,
                        "gems" => $gemIds,
                        "transmog" => $transmogId,
                    ];
                }
            } catch (Throwable $e) {
                $this->log('warning', sprintf("Failed to parse item rel component '%s': %s", $itemRelComponent, $e->getMessage()), ['exception' => $e]);
                continue;
            }
        }
        return $equippedItems;
    }


    /**
     * Extracts the guild name.
     * Corresponds to `WarmaneParser::extract_guild`.
     */
    public function extractGuild(string $html): ?string
    {
        if (empty($html)) {
            return null;
        }
        try {
            $dom = new DOMDocument();
            @$dom->loadHTML($html);
            $xpath = new DOMXPath($dom);
            $guildSpan = $xpath->query("//span[@class='guild-name']/a");
            if ($guildSpan->length > 0) {
                return trim($guildSpan->item(0)->textContent);
            }
            return null;
        } catch (Throwable $e) {
            $this->log('warning', "Error parsing guild: " . $e->getMessage(), ['exception' => $e]);
            return null;
        }
    }

    /**
     * Extracts professions and their levels.
     * Corresponds to `WarmaneParser::extract_professions`.
     */
    public function extractProfessions(string $html): array
    {
        if (empty($html)) {
            return [];
        }
        $results = [];
        try {
            $dom = new DOMDocument();
            @$dom->loadHTML($html);
            $xpath = new DOMXPath($dom);
            $profDivs = $xpath->query("//div[contains(@class, 'profskills')]//div[@class='text']");
            if ($profDivs->length === 0) {
                return [];
            }

            foreach ($profDivs as $div) {
                // Get the text node before any spans
                $profNameNode = $xpath->query("text()[1]", $div);
                $profName = $profNameNode->length > 0 ? trim($profNameNode->item(0)->textContent) : '';

                // Get the value from the span
                $valueSpan = $xpath->query("./span[@class='value']", $div);
                $profValue = ($valueSpan->length > 0) ? trim($valueSpan->item(0)->textContent) : "N/A";

                if (!empty($profName)) {
                    $results[] = sprintf("%s (%s)", $profName, $profValue);
                }
            }
        } catch (Throwable $e) {
            $this->log('warning', "Error parsing professions: " . $e->getMessage(), ['exception' => $e]);
        }
        return $results;
    }

    /**
     * Extracts specializations.
     * Corresponds to `WarmaneParser::extract_specializations`.
     */
    public function extractSpecializations(string $html): array
    {
        if (empty($html)) {
            return [];
        }
        $results = [];
        try {
            $dom = new DOMDocument();
            @$dom->loadHTML($html);
            $xpath = new DOMXPath($dom);

            // Find spec containers which usually have data-id="0" or data-id="1"
            $specContainers = $xpath->query("//div[contains(@class, 'specialization')]");

            if ($specContainers->length > 0) {
                foreach ($specContainers as $i => $container) {
                    $textDivs = $xpath->query(".//div[@class='text']", $container);
                    if ($textDivs->length > 0) {
                        foreach ($textDivs as $div) {
                            $specNameNode = $xpath->query("text()[1]", $div);
                            $specName = $specNameNode->length > 0 ? trim($specNameNode->item(0)->textContent) : '';
                            $valueSpan = $xpath->query("./span[@class='value']", $div);
                            $specValue = ($valueSpan->length > 0) ? trim($valueSpan->item(0)->textContent) : "0/0/0";
                            if (!empty($specName)) {
                                $results[] = sprintf("%s (%s)", $specName, $specValue);
                            }
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            $this->log('warning', "Error parsing specializations: " . $e->getMessage(), ['exception' => $e]);
        }
        return $results;
    }

    /**
     * Extracts Level, Race, and Class.
     * Corresponds to `WarmaneParser::extract_level_race_class`.
     */
    public function extractLevelRaceClass(string $html): array // Returns array{level: ?int, race: ?string, class: ?string}
    {
        if (empty($html)) {
            return ['level' => null, 'race' => null, 'class' => null];
        }
        try {
            $dom = new DOMDocument();
            @$dom->loadHTML($html);
            $xpath = new DOMXPath($dom);
            $dataDiv = $xpath->query("//div[@class='level-race-class']");
            if ($dataDiv->length === 0) {
                return ['level' => null, 'race' => null, 'class' => null];
            }

            $text = trim($dataDiv->item(0)->textContent);
            $parts = explode(', ', $text);
            $baseInfo = $parts[0];

            $levelMatch = [];
            $level = null;
            if (preg_match('/Level (\d+)/', $baseInfo, $levelMatch)) {
                $level = (int)$levelMatch[1];
                $baseInfo = str_replace($levelMatch[0], '', $baseInfo);
            }

            $baseInfo = trim($baseInfo);

            $race = null;
            $class = null;

            $races = [
                "Blood Elf", "Human", "Orc", "Dwarf", "Night Elf", "Undead",
                "Tauren", "Gnome", "Troll", "Draenei", "Worgen", "Goblin"
            ];
            $classes = [
                "Death Knight", "Druid", "Hunter", "Mage", "Paladin",
                "Priest", "Rogue", "Shaman", "Warlock", "Warrior"
            ];

            foreach ($classes as $c) {
                if (str_contains($baseInfo, $c)) {
                    $class = $c;
                    $baseInfo = str_replace($c, '', $baseInfo);
                    break;
                }
            }

            foreach ($races as $r) {
                if (str_contains($baseInfo, $r)) {
                    $race = $r;
                    break;
                }
            }
            if (empty($race) && empty($class) && str_contains($baseInfo, ' ')) {
                foreach ($races as $r) {
                    foreach ($classes as $c) {
                        $combined = str_replace(' ', '', $r) . str_replace(' ', '', $c);
                        if (str_contains(str_replace(' ', '', $baseInfo), $combined)) {
                            $race = $r;
                            $class = $c;
                            break 2;
                        }
                    }
                }
            }


            return ['level' => $level, 'race' => $race, 'class' => $class];
        } catch (Throwable $e) {
            $this->log('warning', "Error parsing level/race/class: " . $e->getMessage(), ['exception' => $e]);
            return ['level' => null, 'race' => null, 'class' => null];
        }
    }

    /**
     * Extracts the appearance and display IDs embedded in Warmane's model-viewer
     * configuration and normalizes them for Miorey's wow-model-viewer package.
     */
    public function extractCharacterModelData(string $html): ?array
    {
        if (!preg_match('/var\s+charactermodel\s*=\s*\{(?<config>.*?)\};/s', $html, $configMatch)) {
            return null;
        }

        $config = $configMatch['config'];
        if (!preg_match('/models\s*:\s*\{.*?id\s*:\s*[\'\"](?<id>[^\'\"]+)[\'\"]/s', $config, $modelMatch)) {
            return null;
        }

        $modelId = strtolower(preg_replace('/[^a-z]/i', '', $modelMatch['id']));
        $gender = match (true) {
            str_ends_with($modelId, 'female') => 1,
            str_ends_with($modelId, 'male') => 0,
            default => null,
        };

        if ($gender === null) {
            return null;
        }

        $raceName = preg_replace('/(?:female|male)$/', '', $modelId);
        $race = [
            'human' => 1,
            'orc' => 2,
            'dwarf' => 3,
            'nightelf' => 4,
            'undead' => 5,
            'scourge' => 5,
            'tauren' => 6,
            'gnome' => 7,
            'troll' => 8,
            'goblin' => 9,
            'bloodelf' => 10,
            'draenei' => 11,
            'worgen' => 22,
        ][$raceName] ?? null;

        if ($race === null) {
            return null;
        }

        $readInt = static function (string $key) use ($config): int {
            return preg_match('/(?:^|[,\s])' . preg_quote($key, '/') . '\s*:\s*(\d+)/m', $config, $match)
                ? (int) $match[1]
                : 0;
        };

        $items = [];
        if (preg_match('/items\s*:\s*(?<items>\[\s*(?:\[\s*\d+\s*,\s*\d+\s*\]\s*,?\s*)*\])/s', $config, $itemsMatch)) {
            $decodedItems = json_decode($itemsMatch['items'], true);
            if (is_array($decodedItems)) {
                foreach ($decodedItems as $item) {
                    if (is_array($item) && count($item) === 2 && (int) $item[0] > 0 && (int) $item[1] > 0) {
                        $slot = match ((int) $item[0]) {
                            13, 17 => 21,
                            14, 23 => 22,
                            default => (int) $item[0],
                        };
                        $items[] = [$slot, (int) $item[1]];
                    }
                }
            }
        }

        return [
            'race' => $race,
            'gender' => $gender,
            'skin' => $readInt('sk'),
            'face' => $readInt('fa'),
            'hairStyle' => $readInt('ha'),
            'hairColor' => $readInt('hc'),
            'facialStyle' => $readInt('fh'),
            'items' => $items,
        ];
    }

    /**
     * Extracts Major and Minor glyphs for each spec (0 and 1).
     * Corresponds to `WarmaneParser::extract_glyphs`.
     */
    public function extractGlyphs(string $html): array // Returns Dict<string, Dict<string, List<string>>>
    {
        if (empty($html)) {
            return [];
        }
        $glyphData = [];
        try {
            $dom = new DOMDocument();
            @$dom->loadHTML($html);
            $xpath = new DOMXPath($dom);

            // Find glyph sections, typically with data-glyphs="0" or "1"
            $glyphSections = $xpath->query("//div[@data-glyphs='0' or @data-glyphs='1']");

            if ($glyphSections->length === 0) {
                $this->log('warning', "Could not find glyph sections with 'data-glyphs'. This character might not have specs defined or HTML structure changed.");
                return []; // Return empty if no spec-specific glyph sections are found
            }

            foreach ($glyphSections as $section) {
                $specId = $section->getAttribute('data-glyphs');
                // Sanity check for expected spec IDs
                if (!in_array($specId, ['0', '1'])) {
                    $this->log('warning', "Unexpected data-glyphs ID found: " . $specId . ". Skipping this section.");
                    continue;
                }

                $glyphData[$specId] = ["Major Glyphs" => [], "Minor Glyphs" => []];

                // Query for major and minor glyphs *relative to the current section*
                $majorGlyphs = $xpath->query(".//div[contains(@class, 'glyph') and contains(@class, 'major')]/a", $section);
                foreach ($majorGlyphs as $glyphLink) {
                    $glyphData[$specId]["Major Glyphs"][] = trim($glyphLink->textContent);
                }

                $minorGlyphs = $xpath->query(".//div[contains(@class, 'glyph') and contains(@class, 'minor')]/a", $section);
                foreach ($minorGlyphs as $glyphLink) {
                    $glyphData[$specId]["Minor Glyphs"][] = trim($glyphLink->textContent);
                }
            }
        } catch (Throwable $e) {
            $this->log('error', "Error parsing glyphs: " . $e->getMessage(), ['exception' => $e]);
        }
        return $glyphData;
    }


    /**
     * Placeholder for GearScore calculation. Requires detailed Item data (item_level, gear_score_value).
     * Python's `items: Dict[int, List[Item]]` needs conversion.
     * Corresponds to `WarmaneParser::calculate_gear_score`.
     *
     * @param array $equippedItemsData The output from extractEquippedItemsData, now a flat list of items.
     * @param array $dbItemData This would be a map of item_id => full DB item details including GS/iLvl.
     * @return int GearScore
     */
    public function calculateGearScore(array $equippedItemsData, array $dbItemData = []): int
    {
        $gearscore = 0.0;
        $weapons = [];

        foreach ($equippedItemsData as $itemInstance) {
            $itemId = $itemInstance['id'];

            $itemDetails = $dbItemData[$itemId] ?? $this->getItemDetails($itemId);
            if ($itemDetails === null) {
                continue; // Skip if no DB data
            }

            $itemType = $itemDetails['type']; // ItemType (Enum value)
            $itemGs = (float)($itemDetails['gs'] ?? 0); // GearScore

            if ($itemType == ItemTypes::SHIRT->value || $itemType == ItemTypes::TABARD->value) {
                continue;
            }

            if ($this->isEquippedWeaponType($itemType)) {
                $weapons[] = ['type' => $itemType, 'gs' => $itemGs];
            } else {
                $gearscore += $itemGs;
            }
        }

        if ($weapons !== []) {
            // GearScore 3.1.20 applies a 0.5 Titan's Grip multiplier to both
            // weapons when a character has two weapons and either is two-handed.
            $hasTwoHandedWeapon = in_array(
                ItemTypes::WEAPON_2H->value,
                array_column($weapons, 'type'),
                true
            );
            $weaponMultiplier = count($weapons) >= 2 && $hasTwoHandedWeapon ? 0.5 : 1.0;
            $gearscore += array_sum(array_column($weapons, 'gs')) * $weaponMultiplier;
        }

        return (int)floor($gearscore);
    }


    /**
     * Placeholder for Average Item Level calculation. Requires detailed Item data (item_level).
     * Corresponds to `WarmaneParser::calculate_avg_ilvl`.
     *
     * @param array $equippedItemsData The output from extractEquippedItemsData.
     * @param array $dbItemData This would be a map of item_id => full DB item details including GS/iLvl.
     * @return float Average Item Level
     */
    public function calculateAvgIlvl(array $equippedItemsData, array $dbItemData = []): float
    {
        $ilvlTotal = 0.0;
        $count = 0;
        $weaponItemLevels = [];

        foreach ($equippedItemsData as $itemInstance) {
            $itemId = $itemInstance['id'];
            $itemDetails = $dbItemData[$itemId] ?? $this->getItemDetails($itemId);
            if ($itemDetails === null) {
                continue;
            }

            $itemType = $itemDetails['type'];
            $itemIlvl = (float)($itemDetails['ilvl'] ?? 0);

            if ($itemType == ItemTypes::SHIRT->value || $itemType == ItemTypes::TABARD->value) {
                continue;
            }

            if ($this->isEquippedWeaponType($itemType)) {
                $weaponItemLevels[] = $itemIlvl;
            } else {
                $ilvlTotal += $itemIlvl;
                $count++;
            }
        }

        if (!empty($weaponItemLevels)) {
            $ilvlTotal += array_sum($weaponItemLevels) / count($weaponItemLevels);
            $count++;
        }

        return $count > 0 ? round($ilvlTotal / $count, 2) : 0.0;
    }

    private function isEquippedWeaponType(?int $itemType): bool
    {
        return in_array($itemType, [
            ItemTypes::WEAPON_1H->value,
            ItemTypes::WEAPON_2H->value,
            ItemTypes::WEAPON_MAINHAND->value,
            ItemTypes::WEAPON_OFFHAND->value,
        ], true);
    }


    /**
     * Checks for missing enchants. This simplified version will rely on a basic "does it have an enchant_id".
     * A full implementation would require a PHP "Item" class and knowledge of which slots *should* be enchanted.
     * Corresponds to `WarmaneParser::check_enchants`.
     *
     * @param array $equippedItemsData The output from extractEquippedItemsData.
     * @param string|null $charClass Character class (e.g., "Paladin").
     * @param array $professions List of professions (e.g., ["Blacksmithing (450)"]).
     * @return string Status message about enchants.
     */
    public function checkEnchants(array $equippedItemsData, ?string $charClass, array $professions): string
    {
        $notEnchantedItemNames = [];
        $enchantingProfFound = false;
        foreach ($professions as $prof) {
            if (str_contains($prof, "Enchanting")) {
                $enchantingProfFound = true;
                break;
            }
        }

        foreach ($equippedItemsData as $itemInstance) {
            $itemId = $itemInstance['id'];
            $scrapedEnchantId = $itemInstance['enchant'];

            $itemDetails = $this->getItemDetails($itemId);
            if ($itemDetails === null) {
                continue;
            }
            $itemType = $itemDetails['type'];
            $itemName = $itemDetails['name'];

            if ($itemType === null) {
                continue;
            }

            $shouldHaveEnchant = false;
            switch ($itemType) {
                case ItemTypes::HEAD->value:
                case ItemTypes::SHOULDER->value:
                case ItemTypes::CHEST->value:
                case ItemTypes::BACK->value:
                case ItemTypes::WRIST->value:
                case ItemTypes::GLOVES->value:
                case ItemTypes::LEGS->value:
                case ItemTypes::FEET->value:
                case ItemTypes::WEAPON_1H->value:
                case ItemTypes::WEAPON_2H->value:
                    $shouldHaveEnchant = true;
                    break;
                case ItemTypes::RING->value:
                    $shouldHaveEnchant = $enchantingProfFound;
                    break;
                case ItemTypes::SHIELD->value:
                    if ($charClass && !in_array($charClass, ['Mage', 'Warlock', 'Priest', 'Druid'])) {
                        $shouldHaveEnchant = true;
                    }
                    break;
                case ItemTypes::OFF_HAND->value:
                    $shouldHaveEnchant = false;
                    break;
                case ItemTypes::RANGED->value:
                    if ($charClass && !in_array($charClass, ['Mage', 'Warlock', 'Priest', 'Druid', 'Warrior', 'Rogue'])) {
                        $shouldHaveEnchant = true;
                    }
                    break;
                case ItemTypes::NECK->value:
                case ItemTypes::SHIRT->value:
                case ItemTypes::TABARD->value:
                case ItemTypes::TRINKET->value:
                case ItemTypes::RELIC->value:
                case ItemTypes::WAIST->value:
                    $shouldHaveEnchant = false;
                    break;
                default:
                    $shouldHaveEnchant = false;
            }

            if ($shouldHaveEnchant && $scrapedEnchantId === null) {
                $notEnchantedItemNames[] = $itemName;
            }
        }
        $notEnchantedItemNames = array_unique($notEnchantedItemNames);

        if (empty($notEnchantedItemNames)) {
            return "All applicable items are enchanted! ✅";
        } else {
            return "Enchants missing from: " . implode(", ", $notEnchantedItemNames) . " ❌";
        }
    }

    /**
     * Checks for missing gems.
     * Corresponds to `WarmaneParser::check_gems`.
     *
     * @param array $equippedItemsData The output from extractEquippedItemsData.
     * @return string Status message about gems.
     */
    public function checkGems(array $equippedItemsData): string
    {
        $missingGemsItemNames = [];

        foreach ($equippedItemsData as $itemInstance) {
            $itemId = $itemInstance['id'];
            $scrapedGemIds = $itemInstance['gems'];

            $itemDetails = $this->getItemDetails($itemId);
            if ($itemDetails === null) {
                continue;
            }
            $itemType = $itemDetails['type'];
            $itemName = $itemDetails['name'];
            $declaredGemSlots = (int)($itemDetails['gem_slots'] ?? 0);

            if ($itemType === null) {
                continue;
            }

            $amountOfExpectedGems = $declaredGemSlots;
            if ($itemType === ItemTypes::WAIST->value) {
                $amountOfExpectedGems = $declaredGemSlots + 1;
            }

            $filledGemSlots = count(array_filter(
                $scrapedGemIds,
                static fn (mixed $gemId): bool => (int) $gemId > 0
            ));

            if ($amountOfExpectedGems > 0 && $filledGemSlots < $amountOfExpectedGems) {
                $missingGemsItemNames[] = $itemName;
            }
        }
        $missingGemsItemNames = array_unique($missingGemsItemNames);

        if (empty($missingGemsItemNames)) {
            return "All applicable items are gemmed! ✅";
        } else {
            return "Gems missing from: " . implode(", ", $missingGemsItemNames) . " ❌";
        }
    }


    /**
     * Extracts the talent points string (e.g., "0500...") for each spec.
     * Corresponds to `WarmaneParser::extract_talent_points_string`.
     */
    public function extractTalentPointsString(string $html, ?string $className): array // Returns Dict<string, string>
    {
        if (empty($html) || empty($className)) {
            return [];
        }
        $talentStrings = [];
        try {
            $dom = new DOMDocument();
            @$dom->loadHTML($html);
            $xpath = new DOMXPath($dom);

            $containers = $xpath->query("//div[contains(@class, 'talents-container')]"); // More general class matching

            if ($containers->length === 0) {
                $this->log('warning', "Could not find talent containers.");
                return [];
            }

            foreach ($containers as $i => $container) {
                $specId = $container->getAttribute('data-id'); // Try to get data-id
                if (empty($specId)) {
                    $specId = (string)$i; // Fallback to index if no data-id
                }

                $talentDivs = $xpath->query(".//div[contains(@class, 'talent-points')]", $container);
                if ($talentDivs->length === 0) {
                    // Try another common pattern (e.g., span with points)
                    $talentDivs = $xpath->query(".//span[@class='points']", $container);
                }

                $talentNumbers = [];
                foreach ($talentDivs as $div) {
                    $text = trim($div->textContent);
                    try {
                        $allocated = explode("/", $text)[0];
                        $talentNumbers[] = $allocated;
                    } catch (Throwable $e) {
                        $this->log('warning', sprintf("Could not parse talent points from '%s': %s", $text, $e->getMessage()));
                        $talentNumbers[] = "0"; // Append '0' on error
                    }
                }

                if (!empty($talentNumbers)) {
                    $talentStrings[$specId] = implode("", $talentNumbers);
                } else {
                    $this->log('warning', sprintf("No talent points found for spec_id %s", $specId));
                }
            }
        } catch (Throwable $e) {
            $this->log('error', "Error parsing talent points string: " . $e->getMessage(), ['exception' => $e]);
        }

        // Basic validation: Check length based on class (approximate total talent nodes)
        $expectedLengths = [
            "Death Knight" => 88,
            "Druid"        => 88,
            "Hunter"       => 85,
            "Mage"         => 85,
            "Paladin"      => 85,
            "Priest"       => 85,
            "Rogue"        => 85,
            "Shaman"       => 85,
            "Warlock"      => 85,
            "Warrior"      => 85,
        ];
        $expectedLen = $expectedLengths[$className] ?? 85;

        $finalTalentStrings = [];
        foreach ($talentStrings as $specId => $tStr) {
            // Allow flexibility, especially if some points are "0" for lower levels
            if (strlen($tStr) >= $expectedLen - 15 && strlen($tStr) <= $expectedLen + 5) {
                $finalTalentStrings[$specId] = $tStr;
            } else {
                $this->log('warning', sprintf("Talent string for spec %s (%s) seems unusual length (%d points for %s). Expected ~%d. Discarding.", $specId, $tStr, strlen($tStr), $className, $expectedLen));
            }
        }

        return $finalTalentStrings;
    }

    /**
     * Extracts full structured talent trees (icons, positions, points, tiers, tooltips) per spec.
     */
    public function extractTalentTrees(string $html): array
    {
        if (empty($html)) {
            return [];
        }

        $parsedTalentSpecs = [];
        try {
            $dom = new DOMDocument();
            @$dom->loadHTML($html);
            $xpath = new DOMXPath($dom);

            $specContainers = $xpath->query("//div[contains(@class, 'talents-container')]");

            foreach ($specContainers as $specIdx => $container) {
                $specId = $container->getAttribute('data-id');
                if (empty($specId)) {
                    $specId = (string)$specIdx;
                }

                $treeFrames = $xpath->query(".//div[contains(@class, 'talent-frame')]", $container);
                $trees = [];

                foreach ($treeFrames as $treeIdx => $frame) {
                    $infoNode = $xpath->query(".//div[contains(@class, 'talent-tree-info')]", $frame)->item(0);
                    $treeName = 'Tree ' . ($treeIdx + 1);
                    $treeIcon = null;
                    $treePoints = 0;

                    if ($infoNode) {
                        $style = $infoNode->getAttribute('style');
                        if (preg_match('/url\(([^)]+)\)/', $style, $m)) {
                            $treeIcon = trim($m[1], "'\"");
                            if (str_starts_with($treeIcon, '//')) {
                                $treeIcon = 'https:' . $treeIcon;
                            }
                        }
                        $spans = $xpath->query(".//span", $infoNode);
                        if ($spans->length >= 1) {
                            $treeName = trim($spans->item(0)->textContent);
                        }
                        if ($spans->length >= 2) {
                            $treePoints = (int)trim($spans->item(1)->textContent);
                        }
                    }

                    $tierNodes = $xpath->query(".//div[contains(@class, 'tier')]", $frame);
                    $tiers = [];

                    foreach ($tierNodes as $tierNode) {
                        $colNodes = $xpath->query(".//a[contains(@class, 'talent')]", $tierNode);
                        $tierCols = array_fill(0, 4, null);

                        foreach ($colNodes as $talentNode) {
                            $href = $talentNode->getAttribute('href');
                            if (str_starts_with($href, '//')) {
                                $href = 'https:' . $href;
                            }

                            $style = $talentNode->getAttribute('style');
                            $iconUrl = null;
                            if (preg_match('/url\(([^)]+)\)/', $style, $m)) {
                                $iconUrl = trim($m[1], "'\"");
                                if (str_starts_with($iconUrl, '//')) {
                                    $iconUrl = 'https:' . $iconUrl;
                                }
                            }

                            $classStr = $talentNode->getAttribute('class');
                            $colIdx = 0;
                            if (preg_match('/col(\d)/', $classStr, $cm)) {
                                $colIdx = (int)$cm[1];
                            }

                            $ptsNode = $xpath->query(".//div[contains(@class, 'talent-points')]", $talentNode)->item(0);
                            $ptsText = $ptsNode ? trim($ptsNode->textContent) : '0/0';
                            $ptsClass = $ptsNode ? $ptsNode->getAttribute('class') : '';

                            $ptsParts = explode('/', $ptsText);
                            $ptsAllocated = isset($ptsParts[0]) ? (int)$ptsParts[0] : 0;
                            $ptsMax = isset($ptsParts[1]) ? (int)$ptsParts[1] : 0;

                            $status = 'disabled';
                            if (str_contains($ptsClass, 'max')) {
                                $status = 'max';
                            } elseif (!str_contains($ptsClass, 'disabled') && $ptsAllocated > 0) {
                                $status = 'active';
                            }

                            $spellId = null;
                            if (preg_match('/spell=(\d+)/', $href, $sm)) {
                                $spellId = $sm[1];
                            }

                            $tierCols[$colIdx] = [
                                'href' => $href,
                                'spellId' => $spellId,
                                'iconUrl' => $iconUrl,
                                'pointsText' => $ptsText,
                                'allocated' => $ptsAllocated,
                                'max' => $ptsMax,
                                'status' => $status,
                                'col' => $colIdx,
                            ];
                        }
                        $tiers[] = $tierCols;
                    }

                    $trees[] = [
                        'name' => $treeName,
                        'iconUrl' => $treeIcon,
                        'points' => $treePoints,
                        'tiers' => $tiers,
                    ];
                }

                $parsedTalentSpecs[$specId] = $trees;
            }
        } catch (\Throwable $e) {
            $this->log('error', 'Error extracting talent trees: ' . $e->getMessage(), ['exception' => $e]);
        }

        return $parsedTalentSpecs;
    }


    /**
     * Extracts kill counts from statistics HTML.
     * Corresponds to `WarmaneParser::extract_killcount`.
     * This method expects a JSON string where "content" key holds the HTML.
     */
    public function extractKillCount(string $jsonHtmlContent, string $category): array // Returns processed stats or empty array
    {
        if (empty($jsonHtmlContent)) {
            return [];
        }
        try {
            $data = json_decode($jsonHtmlContent, true); // Decode JSON into an associative array

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->log('error', "Failed to decode JSON for killcount: " . json_last_error_msg());
                return [];
            }

            $htmlContent = $data["content"] ?? null; // Get the HTML from the "content" key

            if (empty($htmlContent)) {
                $this->log('warning', "No 'content' key found in JSON or content is empty for killcount.");
                return [];
            }

            $dom = new DOMDocument();
            @$dom->loadHTML($htmlContent);
            $xpath = new DOMXPath($dom);

            $tableBody = $xpath->query("//tbody[@id='data-table-list']");
            if ($tableBody->length === 0) {
                $this->log('warning', "No table body with id 'data-table-list' found for killcount.");
                return [];
            }

            $rawTableData = [];
            $rows = $xpath->query("./tr", $tableBody->item(0)); // Query rows relative to tbody

            foreach ($rows as $row) {
                $cols = $xpath->query("./td", $row);
                if ($cols->length === 2) {
                    $description = trim($cols->item(0)->textContent);
                    $value = trim($cols->item(1)->textContent);
                    $rawTableData[] = ["description" => $description, "value" => $value];
                }
            }

            return $this->processKillCountByCategory($rawTableData, $category);

        } catch (Throwable $e) {
            $this->log('warning', "Error parsing killcount: " . $e->getMessage(), ['exception' => $e]);
            return [];
        }
    }

    /**
     * Helper to process kill count data based on category.
     */
    private function processKillCountByCategory(array $rawTableData, string $category): array
    {
        $processed = ['category' => $category, 'stats' => []];
        foreach ($rawTableData as $row) {
            $processed['stats'][$row['description']] = $row['value'];
        }
        return $processed;
    }

    /**
     * Extracts PvP summary (Honorable Kills, Kills Today, Arena Teams) from profile HTML.
     */
    public function extractPvpSummary(string $html): array
    {
        $pvpSummary = [
            'totalKills' => 0,
            'killsToday' => 0,
            'arenaTeams' => [],
        ];

        if (empty($html)) {
            return $pvpSummary;
        }

        try {
            $dom = new DOMDocument();
            @$dom->loadHTML($html);
            $xpath = new DOMXPath($dom);

            // Extract Total Kills and Kills Today from pvpbasic
            $pvpStubs = $xpath->query("//div[contains(@class, 'pvpbasic')]//div[@class='stub']");
            foreach ($pvpStubs as $stub) {
                $textNode = $xpath->query(".//div[@class='text']", $stub)->item(0);
                if ($textNode) {
                    $rawText = trim($textNode->textContent);
                    $valSpan = $xpath->query(".//span[@class='value']", $textNode)->item(0);
                    $val = $valSpan ? (int)preg_replace('/[^0-9]/', '', $valSpan->textContent) : 0;

                    if (str_contains($rawText, 'Total Kills')) {
                        $pvpSummary['totalKills'] = $val;
                    } elseif (str_contains($rawText, 'Kills Today')) {
                        $pvpSummary['killsToday'] = $val;
                    }
                }
            }

            // Extract Arena Teams (2v2, 3v3, 5v5)
            // Found in profile HTML in stubs with "2v2 team", "3v3 team", "5v5 team"
            $teamStubs = $xpath->query("//div[@class='text' and (contains(., '2v2 team') or contains(., '3v3 team') or contains(., '5v5 team'))]");
            foreach ($teamStubs as $stubDiv) {
                $rawText = trim($stubDiv->textContent);
                $linkNode = $xpath->query(".//a", $stubDiv)->item(0);
                $valSpan = $xpath->query(".//span[@class='value']", $stubDiv)->item(0);

                $parentStub = $stubDiv->parentNode;
                $rankDiv = $xpath->query(".//div[@class='rank']", $parentStub)->item(0);
                $rank = $rankDiv ? (int)trim($rankDiv->textContent) : null;

                $bracket = '2v2';
                if (str_contains($rawText, '3v3 team')) {
                    $bracket = '3v3';
                } elseif (str_contains($rawText, '5v5 team')) {
                    $bracket = '5v5';
                }

                $teamName = $linkNode ? trim($linkNode->textContent) : 'Unknown';
                $teamUrl = $linkNode ? $linkNode->getAttribute('href') : '';
                $rating = $valSpan ? (int)preg_replace('/[^0-9]/', '', $valSpan->textContent) : 0;

                $pvpSummary['arenaTeams'][] = [
                    'bracket' => $bracket,
                    'name' => $teamName,
                    'rating' => $rating,
                    'rank' => $rank,
                    'url' => $teamUrl,
                ];
            }
        } catch (Throwable $e) {
            $this->log('warning', "Error parsing PvP summary: " . $e->getMessage(), ['exception' => $e]);
        }

        return $pvpSummary;
    }

    /**
     * Extracts Match History list from match-history HTML table.
     */
    public function extractMatchHistory(string $html): array
    {
        if (empty($html)) {
            return [];
        }

        $matches = [];
        try {
            $dom = new DOMDocument();
            @$dom->loadHTML($html);
            $xpath = new DOMXPath($dom);

            $rows = $xpath->query("//table[@id='data-table-history']//tbody/tr");
            foreach ($rows as $row) {
                $cols = $xpath->query("./td", $row);
                if ($cols->length < 7) {
                    continue;
                }

                $matchIdCell = $cols->item(0);
                $teamCell = $cols->item(1);
                $outcomeCell = $cols->item(2);
                $ratingCell = $cols->item(3);
                $startTimeCell = $cols->item(4);
                $durationCell = $cols->item(5);
                $mapCell = $cols->item(6);

                $detailsCell = $cols->length >= 8 ? $cols->item(7) : null;
                $gameId = null;
                if ($detailsCell) {
                    $gameId = $detailsCell->getAttribute('data-gameid');
                }
                if (empty($gameId)) {
                    $gameId = trim($matchIdCell->textContent);
                }

                if (empty($gameId)) {
                    continue;
                }

                $teamLink = $xpath->query(".//a", $teamCell)->item(0);
                $teamName = $teamLink ? trim($teamLink->textContent) : trim($teamCell->textContent);
                $teamUrl = $teamLink ? $teamLink->getAttribute('href') : '';

                $outcome = trim($outcomeCell->textContent);
                $rating = trim($ratingCell->textContent);
                $startTime = trim($startTimeCell->textContent);
                $duration = trim($durationCell->textContent);
                $map = trim($mapCell->textContent);

                $matches[] = [
                    'gameId' => $gameId,
                    'team' => $teamName,
                    'teamUrl' => $teamUrl,
                    'outcome' => $outcome,
                    'ratingChange' => $rating,
                    'startTime' => $startTime,
                    'duration' => $duration,
                    'map' => $map,
                ];
            }
        } catch (Throwable $e) {
            $this->log('warning', "Error parsing match history: " . $e->getMessage(), ['exception' => $e]);
        }

        return $matches;
    }

    /**
     * Fetches detailed player breakdown for a specific match ID via POST request.
     */
    public function fetchMatchDetails(string $character, string $realm, string $gameId): array
    {
        $url = sprintf(
            "https://armory.warmane.com/character/%s/%s/match-history",
            ucfirst($character),
            ucfirst($realm)
        );

        $headers = [
            'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64; rv:133.0) Gecko/20100101 Firefox/133.0',
            'Accept' => 'application/json, text/javascript, */*; q=0.01',
            'X-Requested-With' => 'XMLHttpRequest',
            'Referer' => $url,
        ];

        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => $headers,
                'body' => ['matchinfo' => $gameId],
                'timeout' => 10,
            ]);

            if ($response->getStatusCode() === 200) {
                $data = $response->toArray(false);
                if (is_array($data)) {
                    $players = [];
                    foreach ($data as $p) {
                        $players[] = [
                            'charname' => $p['charname'] ?? '',
                            'realm' => $p['realm'] ?? '',
                            'race' => $p['race'] ?? '',
                            'gender' => $p['gender'] ?? '',
                            'class' => $p['class'] ?? '',
                            'teamname' => $p['teamname'] ?? '',
                            'teamnamerich' => $p['teamnamerich'] ?? ($p['teamname'] ?? ''),
                            'damage' => (int)($p['damage'] ?? 0),
                            'healing' => (int)($p['healing'] ?? 0),
                            'killingblows' => (int)($p['killingblows'] ?? ($p['kbs'] ?? 0)),
                            'deaths' => (int)($p['deaths'] ?? 0),
                            'mmr' => (int)($p['mmr'] ?? 0),
                            'prating' => $p['prating'] ?? ($p['ratingchange'] ?? 0),
                        ];
                    }
                    return $players;
                }
            }
        } catch (Throwable $e) {
            $this->log('error', sprintf("Failed to fetch match details for gameId %s: %s", $gameId, $e->getMessage()), ['exception' => $e]);
        }

        return [];
    }
}
