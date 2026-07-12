<?php

declare(strict_types=1);

namespace App\Message;

/**
 * A post was created in Discourse: relay it to the mailing list as an email
 * from the author's surrogate address.
 */
final readonly class RelayPost
{
    public function __construct(
        public int $postId,
        public int $topicId,
        public int $discourseUserId,
        public ?int $categoryId,
        public bool $viaEmail,
    ) {
    }
}
