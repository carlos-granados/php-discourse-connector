<?php

declare(strict_types=1);

namespace App\Mail;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Builds the canonical email Message-ID Discourse uses for a post
 * ("topic/<topicId>/<postId>@<host>", without angle brackets).
 *
 * Using this exact form for our outbound mail lets Discourse's incoming-mail
 * receiver recognise our email as the echo of the source post and dedupe it,
 * instead of importing a duplicate.
 */
final readonly class MessageIdFactory
{
    private string $host;

    public function __construct(
        #[Autowire(env: 'DISCOURSE_URL')]
        string $discourseUrl,
    ) {
        $this->host = parse_url($discourseUrl, \PHP_URL_HOST) ?: 'discourse';
    }

    public function forPost(int $topicId, int $postId): string
    {
        return \sprintf('topic/%d/%d@%s', $topicId, $postId, $this->host);
    }
}
