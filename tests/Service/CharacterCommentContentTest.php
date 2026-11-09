<?php

namespace App\Tests\Service;

use App\Service\CharacterCommentContent;
use PHPUnit\Framework\TestCase;

final class CharacterCommentContentTest extends TestCase
{
    private CharacterCommentContent $content;

    protected function setUp(): void
    {
        $this->content = new CharacterCommentContent();
    }

    public function testNormalizesLineBreaksWhitespaceAndUnicodeText(): void
    {
        self::assertSame(
            ['body' => 'Héllo 世界 — 2nd line', 'error' => null],
            $this->content->validate("  Héllo\r\n世界   —  2nd\nline  "),
        );
    }

    public function testEnforcesLengthAfterNormalization(): void
    {
        self::assertNull($this->content->validate('  '.str_repeat('a', 255).'  ')['error']);
        self::assertSame('Comments must be between 1 and 255 characters.', $this->content->validate(str_repeat('a', 256))['error']);
        self::assertSame('Comments must be between 1 and 255 characters.', $this->content->validate(" \n ")['error']);
    }

    public function testRejectsEmojiControlsZeroWidthAndBidiCharacters(): void
    {
        foreach (["hello 😀", "hello\x01world", "a\u{200B}b", "a\u{202E}b", "tab\there"] as $input) {
            self::assertSame(
                'Comments can only contain text, numbers and punctuation.',
                $this->content->validate($input)['error'],
                bin2hex($input),
            );
        }
    }

    public function testAllowsOnlyExactYoutubeAndTwitchHosts(): void
    {
        foreach ([
            'https://youtube.com/watch?v=abc',
            'http://www.youtube.com/a',
            'm.youtube.com/x',
            'music.youtube.com/a',
            'youtu.be/abc',
            'https://twitch.tv/a',
            'www.twitch.tv/a',
            'm.twitch.tv/a',
            'clips.twitch.tv/clip',
        ] as $url) {
            self::assertNull($this->content->validate($url)['error'], $url);
        }
    }

    public function testRejectsLookalikeHostsUserInfoAndNonHttpSchemes(): void
    {
        foreach ([
            'youtube.com.evil.com/x',
            'evil.com/youtube.com',
            'youtube.com@evil.com',
            'https://user@youtube.com/watch?v=abc',
            'https://youtube.com@evil.com/watch',
            'https://youtube.com.evil.com/',
            'https://xn--youtub-9za.com/',
            'https://yоutube.com/',
            'ftp://youtube.com/file',
            'https://youtube.com:8443/watch',
            'javascript:alert(1)',
            'mailto:user@example.com',
            'example.com/x',
        ] as $url) {
            self::assertSame('Only YouTube and Twitch links are allowed.', $this->content->validate($url)['error'], $url);
        }
    }

    public function testLinkifiesAllowedUrlsAndEscapesTheRestOfTheText(): void
    {
        $validated = $this->content->validate('Look (www.youtube.com/watch?v=abc), <hello>');
        self::assertNull($validated['error']);
        self::assertSame(
            'Look (<a href="https://www.youtube.com/watch?v=abc" rel="nofollow ugc noopener noreferrer" target="_blank">www.youtube.com/watch?v=abc</a>), &lt;hello&gt;',
            $this->content->linkify((string) $validated['body']),
        );
    }
}
