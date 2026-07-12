<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\SyncSurrogate;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SyncSurrogateHandler
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(SyncSurrogate $message): void
    {
        // TODO(phase 4): sync name changes, re-provision on email change
        $this->logger->info('Surrogate sync requested', [
            'discourse_user_id' => $message->discourseUserId,
            'username' => $message->username,
        ]);
    }
}
