<?php

namespace App\Tests\Service;

use App\Service\WowIconCache;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class WowIconCacheTest extends TestCase
{
    private string $cacheDirectory;

    protected function setUp(): void
    {
        $this->cacheDirectory = sys_get_temp_dir().'/armorystuff-icon-test-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->cacheDirectory.'/*') ?: [] as $file) {
            unlink($file);
        }

        if (is_dir($this->cacheDirectory)) {
            rmdir($this->cacheDirectory);
        }
    }

    public function testDownloadsAnIconOnceAndThenUsesTheSavedCopy(): void
    {
        $requests = 0;
        $client = new MockHttpClient(function (string $method, string $url) use (&$requests): MockResponse {
            ++$requests;
            self::assertSame('GET', $method);
            self::assertSame(
                'https://wow.zamimg.com/images/wow/icons/large/inv_helmet_06.jpg',
                $url
            );

            return new MockResponse("\xFF\xD8\xFF\xE0icon", [
                'http_code' => 200,
                'response_headers' => ['content-type: image/jpeg'],
            ]);
        });
        $cache = new WowIconCache($client, $this->cacheDirectory);

        $firstPath = $cache->get('INV_HELMET_06');
        $secondPath = $cache->get('inv_helmet_06');

        self::assertNotNull($firstPath);
        self::assertSame($firstPath, $secondPath);
        self::assertFileExists($firstPath);
        self::assertSame(1, $requests);
    }

    public function testRejectsUnsafeNamesWithoutMakingARequest(): void
    {
        $client = new MockHttpClient(static function (): MockResponse {
            self::fail('No upstream request should be made for an invalid icon name.');
        });
        $cache = new WowIconCache($client, $this->cacheDirectory);

        self::assertNull($cache->get('../secret'));
        self::assertDirectoryDoesNotExist($this->cacheDirectory);
    }

    public function testDoesNotCacheAnInvalidResponse(): void
    {
        $client = new MockHttpClient(new MockResponse('<html>not an icon</html>', [
            'http_code' => 200,
            'response_headers' => ['content-type: text/html'],
        ]));
        $cache = new WowIconCache($client, $this->cacheDirectory);

        self::assertNull($cache->get('inv_helmet_06'));
        self::assertFileDoesNotExist($this->cacheDirectory.'/inv_helmet_06.jpg');
    }

    public function testUsesWarmaneWhenWowheadIsUnavailable(): void
    {
        $requestedUrls = [];
        $client = new MockHttpClient(static function (string $method, string $url) use (&$requestedUrls): MockResponse {
            $requestedUrls[] = $url;

            return count($requestedUrls) === 1
                ? new MockResponse('', ['http_code' => 503])
                : new MockResponse("\xFF\xD8\xFF\xE0warmane", ['http_code' => 200]);
        });
        $cache = new WowIconCache($client, $this->cacheDirectory);

        $path = $cache->get('spell_holy_holybolt');

        self::assertNotNull($path);
        self::assertSame("\xFF\xD8\xFF\xE0warmane", file_get_contents($path));
        self::assertSame(
            'https://cdn.warmane.com/wotlk/icons/medium/spell_holy_holybolt.jpg',
            $requestedUrls[1]
        );
    }
}
