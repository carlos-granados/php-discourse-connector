<?php

declare(strict_types=1);

namespace App\Mail;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Turns a Discourse post's raw markdown into a plain-text email body suitable
 * for the mailing list.
 *
 * The transform is deliberately minimal so the result reads like a message
 * written directly by email: markdown is left as-is (people write it in list
 * mail too), only upload URLs are made absolute and over-long prose lines are
 * wrapped. No footer, banner, or other decoration is added.
 */
final readonly class PostToEmailTransformer
{
    private const int WRAP_WIDTH = 78;

    private string $baseUrl;

    public function __construct(
        #[Autowire(env: 'DISCOURSE_URL')]
        string $discourseUrl,
    ) {
        $this->baseUrl = rtrim($discourseUrl, '/');
    }

    public function toPlainText(string $rawMarkdown): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $rawMarkdown);
        $text = $this->absolutizeUploads($text);
        $text = $this->wrapProse($text);

        return rtrim($text)."\n";
    }

    private function absolutizeUploads(string $text): string
    {
        // Discourse short-url scheme: upload://hash.ext -> /uploads/short-url/hash.ext
        $text = preg_replace_callback(
            '#upload://([A-Za-z0-9._-]+)#',
            fn (array $m): string => \sprintf('%s/uploads/short-url/%s', $this->baseUrl, $m[1]),
            $text,
        ) ?? $text;

        // Root-relative markdown link/image targets: [text](/uploads/x) / ![alt](/t/y)
        return preg_replace_callback(
            '#(\]\()(/[^)\s]+)#',
            fn (array $m): string => $m[1].$this->baseUrl.$m[2],
            $text,
        ) ?? $text;
    }

    private function wrapProse(string $text): string
    {
        $lines = explode("\n", $text);
        $out = [];
        $inFence = false;

        foreach ($lines as $line) {
            if (1 === preg_match('/^\s*(```|~~~)/', $line)) {
                $inFence = !$inFence;
                $out[] = $line;

                continue;
            }

            if ($inFence || $this->shouldNotWrap($line)) {
                $out[] = $line;

                continue;
            }

            $out[] = wordwrap($line, self::WRAP_WIDTH, "\n");
        }

        return implode("\n", $out);
    }

    private function shouldNotWrap(string $line): bool
    {
        if (mb_strlen($line) <= self::WRAP_WIDTH) {
            return true;
        }

        // Leave code, quotes, headings, lists, tables and lines with URLs intact
        return 1 === preg_match('#^(\s{4,}|\t|>|\#|[-*+] |\d+\. |\|)#', $line)
            || str_contains($line, '://');
    }
}
