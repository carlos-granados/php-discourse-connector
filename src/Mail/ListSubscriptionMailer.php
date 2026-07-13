<?php

declare(strict_types=1);

namespace App\Mail;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Sends the ezmlm control emails (subscribe, unsubscribe, confirmation
 * replies) from a surrogate address. ezmlm only looks at the envelope, so
 * subjects and bodies are minimal.
 */
final readonly class ListSubscriptionMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private ListAddressResolver $addresses,
        #[Autowire(env: 'bool:SENDING_ENABLED')]
        private bool $sendingEnabled,
        private LoggerInterface $logger,
    ) {
    }

    public function requestSubscription(string $surrogateAddress): void
    {
        $this->send($surrogateAddress, $this->addresses->subscribeAddress(), 'subscribe');
    }

    public function requestUnsubscription(string $surrogateAddress): void
    {
        $this->send($surrogateAddress, $this->addresses->unsubscribeAddress(), 'unsubscribe');
    }

    public function replyToConfirmation(string $surrogateAddress, string $confirmationAddress, string $originalSubject): void
    {
        $this->send($surrogateAddress, $confirmationAddress, 'Re: '.$originalSubject);
    }

    private function send(string $from, string $to, string $subject): void
    {
        if (!$this->sendingEnabled) {
            $this->logger->warning('Sending is disabled (SENDING_ENABLED=0), list control email skipped', [
                'from' => $from,
                'to' => $to,
                'subject' => $subject,
            ]);

            return;
        }

        $this->mailer->send(
            new Email()
                ->from($from)
                ->to($to)
                ->subject($subject)
                ->text('confirm'),
        );

        $this->logger->info('List control email sent', ['from' => $from, 'to' => $to, 'subject' => $subject]);
    }
}
