<?php

declare(strict_types=1);

namespace App\Mail;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Derives the ezmlm-idx command addresses from the configured list address.
 * The php.net lists use "+" as the command separator, e.g.
 * internals+subscribe-nomail@lists.php.net.
 */
final readonly class ListAddressResolver
{
    public function __construct(
        #[Autowire(env: 'LIST_ADDRESS')]
        private string $listAddress,
    ) {
    }

    public function listAddress(): string
    {
        return $this->listAddress;
    }

    /**
     * Subscription in "nomail" mode: may post, receives no list traffic.
     */
    public function subscribeAddress(): string
    {
        return $this->commandAddress('subscribe-nomail');
    }

    public function unsubscribeAddress(): string
    {
        return $this->commandAddress('unsubscribe-nomail');
    }

    public function listLocalPart(): string
    {
        return explode('@', $this->listAddress, 2)[0];
    }

    public function listDomain(): string
    {
        return explode('@', $this->listAddress, 2)[1] ?? '';
    }

    private function commandAddress(string $command): string
    {
        return \sprintf('%s+%s@%s', $this->listLocalPart(), $command, $this->listDomain());
    }
}
