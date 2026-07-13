<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\SyncSurrogate;
use App\Surrogate\SurrogateProvisioner;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SyncSurrogateHandler
{
    public function __construct(
        private SurrogateProvisioner $provisioner,
    ) {
    }

    public function __invoke(SyncSurrogate $message): void
    {
        $this->provisioner->sync(
            $message->discourseUserId,
            $message->username,
            $message->displayName,
            $message->email,
            $message->staged,
        );
    }
}
