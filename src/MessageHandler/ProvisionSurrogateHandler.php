<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ProvisionSurrogate;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ProvisionSurrogateHandler
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ProvisionSurrogate $message): void
    {
        // TODO(phase 4): create the surrogate user and start the subscription flow
        $this->logger->info('Surrogate provisioning requested', [
            'discourse_user_id' => $message->discourseUserId,
            'username' => $message->username,
        ]);
    }
}
