<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\RelayPost;
use App\Posting\PostRelayer;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RelayPostHandler
{
    public function __construct(
        private PostRelayer $relayer,
    ) {
    }

    public function __invoke(RelayPost $message): void
    {
        $this->relayer->relay($message);
    }
}
