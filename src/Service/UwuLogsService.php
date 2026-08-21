<?php

namespace App\Service;

use App\Exception\UwuLogsException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

class UwuLogsService
{
    private const ENDPOINT = 'https://uwu-logs.xyz/character';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return array{
     *     classId: int,
     *     name: string,
     *     server: string,
     *     spec: string,
     *     overallPoints: float,
     *     overallRank: int,
     *     bosses: list<array<string, int|float|string|null>>
     * }
     */
    public function fetchRankings(string $characterName, string $realmName, string $spec = '1'): array
    {
        if (!in_array($spec, ['1', '2', '3'], true)) {
            throw new UwuLogsException('Please choose a valid specialization.', 400);
        }

        try {
            $response = $this->httpClient->request('POST', self::ENDPOINT, [
                'headers' => [
                    'Accept' => 'application/json, text/plain, */*',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Origin' => 'https://uwu-logs.xyz',
                    'Referer' => 'https://uwu-logs.xyz/character',
                    'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64; rv:153.0) Gecko/20100101 Firefox/153.0',
                ],
                'json' => [
                    'name' => $characterName,
                    'server' => $realmName,
                    'spec' => $spec,
                ],
                'timeout' => 10,
                'max_duration' => 15,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode === 404) {
                throw new UwuLogsException('No Uwu-logs rankings were found for this character and specialization.', 404);
            }

            if ($statusCode < 200 || $statusCode >= 300) {
                throw new UwuLogsException('Uwu-logs is currently unavailable. Please try again later.');
            }

            $data = $response->toArray(false);
        } catch (UwuLogsException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $this->logger->warning('Failed to fetch Uwu-logs character rankings.', [
                'character' => $characterName,
                'realm' => $realmName,
                'spec' => $spec,
                'exception' => $exception,
            ]);

            throw new UwuLogsException('Uwu-logs is currently unavailable. Please try again later.');
        }

        if (!is_array($data) || !isset($data['name'], $data['server']) || !is_array($data['bosses'] ?? null)) {
            throw new UwuLogsException('Uwu-logs returned an unexpected response. Please try again later.');
        }

        $bosses = [];
        foreach ($data['bosses'] as $bossName => $boss) {
            if (!is_string($bossName) || !is_array($boss) || $boss === []) {
                continue;
            }

            $bosses[] = [
                'name' => $bossName,
                'dps' => $this->numberOrNull($boss['dps_max'] ?? null),
                'playerRank' => $this->integerOrNull($boss['rank_players'] ?? null),
                'totalPlayers' => $this->integerOrNull($boss['spec_total_players'] ?? null),
                'raidRank' => $this->integerOrNull($boss['rank_raids'] ?? null),
                'totalRaids' => $this->integerOrNull($boss['spec_total_raids'] ?? null),
                'score' => $this->scoreOrNull($boss['points'] ?? null),
                'dpsScore' => $this->scoreOrNull($boss['points_dps'] ?? null),
                'playerRankScore' => $this->scoreOrNull($boss['points_rank_players'] ?? null),
                'raidRankScore' => $this->scoreOrNull($boss['points_rank_raids'] ?? null),
                'rankOneDps' => $this->numberOrNull($boss['spec_r1_dps'] ?? null),
                'fastestKillSeconds' => $this->numberOrNull($boss['fastest_kill_duration'] ?? null),
                'reportId' => isset($boss['report_id']) && is_string($boss['report_id']) ? $boss['report_id'] : null,
            ];
        }

        return [
            'classId' => (int) ($data['class_i'] ?? 0),
            'name' => (string) $data['name'],
            'server' => (string) $data['server'],
            'spec' => $spec,
            'overallPoints' => $this->scoreOrNull($data['overall_points'] ?? null) ?? 0.0,
            'overallRank' => (int) ($data['overall_rank'] ?? 0),
            'bosses' => $bosses,
        ];
    }

    private function numberOrNull(mixed $value): ?float
    {
        return is_int($value) || is_float($value) ? (float) $value : null;
    }

    private function integerOrNull(mixed $value): ?int
    {
        return is_int($value) ? $value : (is_float($value) ? (int) $value : null);
    }

    private function scoreOrNull(mixed $value): ?float
    {
        $number = $this->numberOrNull($value);
        if ($number === null) {
            return null;
        }

        return max(0.0, min(100.0, $number / 100));
    }
}
