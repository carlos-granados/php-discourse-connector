<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\RetireSurrogate;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RetireSurrogateHandler
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(RetireSurrogate $message): void
    {
        // TODO(phase 4): unsubscribe the surrogate and delete the record (incl. PII)
        $this->logger->info('Surrogate retirement requested', [
            'discourse_user_id' => $message->discourseUserId,
        ]);
    }
}
