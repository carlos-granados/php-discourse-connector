<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ProvisionSurrogate;
use App\Surrogate\SurrogateProvisioner;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ProvisionSurrogateHandler
{
    public function __construct(
        private SurrogateProvisioner $provisioner,
    ) {
    }

    public function __invoke(ProvisionSurrogate $message): void
    {
        $this->provisioner->provision(
            $message->discourseUserId,
            $message->username,
            $message->displayName,
            $message->email,
            $message->staged,
        );
    }
}
