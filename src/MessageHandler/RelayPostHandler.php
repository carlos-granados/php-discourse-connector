<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\RelayPost;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RelayPostHandler
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(RelayPost $message): void
    {
        // TODO(phase 5): filter (category, via_email loop guard) and send to the mailing list
        $this->logger->info('Post relay requested', [
            'post_id' => $message->postId,
            'topic_id' => $message->topicId,
            'discourse_user_id' => $message->discourseUserId,
        ]);
    }
}
