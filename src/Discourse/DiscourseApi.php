<?php

declare(strict_types=1);

namespace App\Discourse;

/**
 * Read access to the Discourse instance for the data the posting flow needs
 * that the webhook payload does not carry authoritatively.
 */
interface DiscourseApi
{
    /**
     * The raw markdown of a post (better source for a plain-text email than
     * the cooked HTML).
     */
    public function fetchPostRaw(int $postId): string;

    /**
     * Resolves a post's numeric id from its 1-based number within a topic,
     * used to find the parent of a reply. Null if it cannot be resolved.
     */
    public function resolvePostId(int $topicId, int $postNumber): ?int;

    /**
     * For a post that was imported from the mailing list, the Message-ID of the
     * original list email (without angle brackets), recovered from its raw
     * email. Null when the post did not arrive by email.
     */
    public function fetchListMessageId(int $postId): ?string;
}
