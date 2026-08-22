<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class WowIconCache
{
    private const MAX_ICON_BYTES = 1_048_576;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%kernel.project_dir%/var/wow-icons')]
        private readonly string $cacheDirectory,
    ) {
    }

    public function get(string $iconName): ?string
    {
        $iconName = strtolower($iconName);
        if (!preg_match('/\A[a-z0-9][a-z0-9_-]{0,127}\z/', $iconName)) {
            return null;
        }

        $path = $this->cacheDirectory.'/'.$iconName.'.jpg';
        if ($this->isCachedIcon($path)) {
            return $path;
        }

        if (!is_dir($this->cacheDirectory) && !@mkdir($this->cacheDirectory, 0770, true) && !is_dir($this->cacheDirectory)) {
            return null;
        }

        $lock = @fopen($path.'.lock', 'c');
        if ($lock === false) {
            return null;
        }

        try {
            if (!flock($lock, LOCK_EX)) {
                return null;
            }

            if ($this->isCachedIcon($path)) {
                return $path;
            }

            $response = $this->httpClient->request(
                'GET',
                'https://wow.zamimg.com/images/wow/icons/large/'.$iconName.'.jpg',
                [
                    'headers' => ['Accept' => 'image/jpeg'],
                    'timeout' => 15,
                ]
            );

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $contents = $response->getContent();
            if (!$this->isJpeg($contents)) {
                return null;
            }

            $temporaryPath = $path.'.'.bin2hex(random_bytes(6)).'.tmp';
            if (file_put_contents($temporaryPath, $contents, LOCK_EX) === false) {
                return null;
            }

            if (!@rename($temporaryPath, $path)) {
                @unlink($temporaryPath);

                return null;
            }

            @chmod($path, 0660);

            return $path;
        } catch (\Throwable) {
            return null;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function isCachedIcon(string $path): bool
    {
        return is_file($path) && filesize($path) > 0;
    }

    private function isJpeg(string $contents): bool
    {
        $length = strlen($contents);

        return $length >= 4
            && $length <= self::MAX_ICON_BYTES
            && str_starts_with($contents, "\xFF\xD8\xFF");
    }
}
