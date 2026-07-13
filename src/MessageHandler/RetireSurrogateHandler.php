<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\RetireSurrogate;
use App\Surrogate\SurrogateProvisioner;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RetireSurrogateHandler
{
    public function __construct(
        private SurrogateProvisioner $provisioner,
    ) {
    }

    public function __invoke(RetireSurrogate $message): void
    {
        $this->provisioner->retire($message->discourseUserId);
    }
}
