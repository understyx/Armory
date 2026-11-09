<?php

namespace App\Service;

final class CharacterCommentContent
{
    private const MAX_LENGTH = 255;

    private const ALLOWED_LINK_HOSTS = [
        'youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com', 'youtu.be',
        'twitch.tv', 'www.twitch.tv', 'm.twitch.tv', 'clips.twitch.tv',
    ];

    /** @return array{body: ?string, error: ?string} */
    public function validate(string $input): array
    {
        $body = preg_replace('/[\r\n\x{2028}\x{2029}]+/u', ' ', $input) ?? $input;
        $body = preg_replace('/[\p{Zs} ]+/u', ' ', $body) ?? $body;
        $body = trim($body);

        if (preg_match('/\A[\p{L}\p{M}\p{N}\p{P}\p{Zs}\x20-\x7E]*\z/u', $body) !== 1) {
            return ['body' => null, 'error' => 'Comments can only contain text, numbers and punctuation.'];
        }
        if ($body === '' || mb_strlen($body) > self::MAX_LENGTH) {
            return ['body' => $body, 'error' => 'Comments must be between 1 and 255 characters.'];
        }
        if (!$this->linksAreAllowed($body)) {
            return ['body' => $body, 'error' => 'Only YouTube and Twitch links are allowed.'];
        }

        return ['body' => $body, 'error' => null];
    }

    public function linkify(string $body): string
    {
        $parts = preg_split('/( )/u', $body, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$body];
        foreach ($parts as &$part) {
            if ($part === ' ' || $part === '') {
                $part = ' ';
                continue;
            }

            [$prefix, $candidate, $suffix] = $this->trimUrlPunctuation($part);
            $url = $this->allowedUrl($candidate);
            if ($url === null) {
                $part = $this->escape($part);
                continue;
            }

            $href = preg_match('/\Ahttps?:\/\//i', $candidate) === 1 ? $candidate : 'https://'.$candidate;
            $part = $this->escape($prefix)
                .'<a href="'.$this->escape($href).'" rel="nofollow ugc noopener noreferrer" target="_blank">'
                .$this->escape($candidate).'</a>'.$this->escape($suffix);
        }
        unset($part);

        return implode('', $parts);
    }

    private function linksAreAllowed(string $body): bool
    {
        foreach (preg_split('/\s+/u', $body, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
            [$prefix, $candidate] = $this->trimUrlPunctuation($token);
            if ($candidate === '') {
                continue;
            }
            if (filter_var($candidate, FILTER_VALIDATE_EMAIL) !== false) {
                $localPart = strstr($candidate, '@', true) ?: '';
                if (in_array(strtolower($localPart), self::ALLOWED_LINK_HOSTS, true)) {
                    return false;
                }
                continue;
            }

            $hasScheme = preg_match('/\A[a-z][a-z0-9+.-]*:\/\//i', $candidate) === 1;
            $looksLikeDomain = str_contains($candidate, '.') && !str_starts_with($prefix, '@');
            if (!$hasScheme && preg_match('/\A([a-z][a-z0-9+.-]*):/i', $candidate, $schemeMatch) === 1 && !str_contains($schemeMatch[1], '.')) {
                return false;
            }
            if (!$hasScheme && !$looksLikeDomain) {
                continue;
            }
            if ($this->allowedUrl($candidate) === null) {
                return false;
            }
        }

        return true;
    }

    private function allowedUrl(string $candidate): ?array
    {
        if ($candidate === '') {
            return null;
        }

        $hasScheme = preg_match('/\A([a-z][a-z0-9+.-]*):\/\//i', $candidate, $match) === 1;
        $scheme = $hasScheme ? strtolower($match[1]) : 'https';
        if (!$hasScheme && preg_match('/\A([a-z][a-z0-9+.-]*):/i', $candidate, $otherScheme) === 1 && !str_contains($otherScheme[1], '.')) {
            return null;
        }
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }
        if (preg_match('/[^\x00-\x7F]/', $candidate) === 1 || str_contains($candidate, '\\')) {
            return null;
        }

        $parsed = parse_url($hasScheme ? $candidate : 'https://'.$candidate);
        if (!is_array($parsed) || isset($parsed['user']) || isset($parsed['pass']) || !isset($parsed['host'])) {
            return null;
        }
        $host = strtolower($parsed['host']);
        if ($host !== rtrim($host, '.') || !in_array($host, self::ALLOWED_LINK_HOSTS, true)) {
            return null;
        }
        if (isset($parsed['port']) && !in_array([$scheme, (int) $parsed['port']], [['http', 80], ['https', 443]], true)) {
            return null;
        }

        return $parsed;
    }

    /** @return array{string, string, string} */
    private function trimUrlPunctuation(string $value): array
    {
        preg_match('/\A([(<\[{"\']*)(.*?)([.,!?;:)\]>}"\']*)\z/u', $value, $matches);

        return [$matches[1] ?? '', $matches[2] ?? $value, $matches[3] ?? ''];
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
