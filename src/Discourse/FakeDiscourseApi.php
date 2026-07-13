<?php

declare(strict_types=1);

namespace App\Discourse;

use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * In-memory Discourse API for tests: returns deterministic post bodies and
 * lets scenarios configure post-number resolution and list Message-IDs.
 */
#[When(env: 'test')]
final class FakeDiscourseApi implements DiscourseApi
{
    /** @var array<int, string> */
    private array $rawByPostId = [];

    /** @var array<string, int> keyed by "topicId:postNumber" */
    private array $postIdByNumber = [];

    /** @var array<int, string> */
    private array $listMessageIdByPostId = [];

    public function reset(): void
    {
        $this->rawByPostId = [];
        $this->postIdByNumber = [];
        $this->listMessageIdByPostId = [];
    }

    public function setPostRaw(int $postId, string $raw): void
    {
        $this->rawByPostId[$postId] = $raw;
    }

    public function setPostIdForNumber(int $topicId, int $postNumber, int $postId): void
    {
        $this->postIdByNumber[$topicId.':'.$postNumber] = $postId;
    }

    public function setListMessageId(int $postId, string $messageId): void
    {
        $this->listMessageIdByPostId[$postId] = $messageId;
    }

    public function fetchPostRaw(int $postId): string
    {
        return $this->rawByPostId[$postId] ?? \sprintf('Body of post %d.', $postId);
    }

    public function resolvePostId(int $topicId, int $postNumber): ?int
    {
        return $this->postIdByNumber[$topicId.':'.$postNumber] ?? null;
    }

    public function fetchListMessageId(int $postId): ?string
    {
        return $this->listMessageIdByPostId[$postId] ?? null;
    }
}
