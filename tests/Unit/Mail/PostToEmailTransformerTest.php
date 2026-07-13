<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mail;

use App\Mail\PostToEmailTransformer;
use PHPUnit\Framework\TestCase;

final class PostToEmailTransformerTest extends TestCase
{
    private PostToEmailTransformer $transformer;

    protected function setUp(): void
    {
        $this->transformer = new PostToEmailTransformer('https://discourse.example.test');
    }

    public function testLeavesPlainProseUntouchedApartFromTrailingNewline(): void
    {
        self::assertSame("Hello list, this is a short message.\n", $this->transformer->toPlainText('Hello list, this is a short message.'));
    }

    public function testAddsNoFooterOrDecoration(): void
    {
        $result = $this->transformer->toPlainText('Just the body.');

        self::assertStringNotContainsString('Discourse', $result);
        self::assertStringNotContainsString('--', $result);
    }

    public function testResolvesUploadShortUrlsToAbsoluteUrls(): void
    {
        $result = $this->transformer->toPlainText('See ![chart](upload://abc123.png) please.');

        self::assertStringContainsString('https://discourse.example.test/uploads/short-url/abc123.png', $result);
        self::assertStringNotContainsString('upload://', $result);
    }

    public function testAbsolutizesRootRelativeLinkTargets(): void
    {
        $result = $this->transformer->toPlainText('Discussed in [the RFC](/t/some-rfc/42).');

        self::assertStringContainsString('[the RFC](https://discourse.example.test/t/some-rfc/42)', $result);
    }

    public function testWrapsLongProseLines(): void
    {
        $line = str_repeat('word ', 30); // ~150 chars, no URL
        $result = $this->transformer->toPlainText(trim($line));

        foreach (explode("\n", trim($result)) as $out) {
            self::assertLessThanOrEqual(78, \strlen($out));
        }
    }

    public function testDoesNotWrapFencedCodeBlocks(): void
    {
        $code = '    $x = '.str_repeat('a', 100).';';
        $markdown = "```\n".$code."\n```";

        $result = $this->transformer->toPlainText($markdown);

        self::assertStringContainsString($code, $result);
    }

    public function testDoesNotWrapLinesContainingUrls(): void
    {
        $line = 'See https://example.com/'.str_repeat('a', 90).' for details on this topic.';

        $result = $this->transformer->toPlainText($line);

        self::assertStringContainsString($line, $result);
    }
}
