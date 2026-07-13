<?php

declare(strict_types=1);

namespace App\Mail;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Sends a Discourse post to the mailing list as an email from the author's
 * surrogate, with the "<Name> via Discourse" From name and threading headers.
 */
final readonly class ListPostMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private ListAddressResolver $addresses,
        #[Autowire(env: 'bool:SENDING_ENABLED')]
        private bool $sendingEnabled,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param string      $messageId the outbound Message-ID (without angle brackets)
     * @param string|null $inReplyTo the parent Message-ID for threading (without angle brackets)
     *
     * @return bool whether the email was actually handed to the mailer
     */
    public function send(
        string $surrogateAddress,
        string $fromName,
        string $subject,
        string $body,
        string $messageId,
        ?string $inReplyTo,
    ): bool {
        if (!$this->sendingEnabled) {
            $this->logger->warning('Sending is disabled (SENDING_ENABLED=0), post not relayed to the list', [
                'message_id' => $messageId,
                'subject' => $subject,
            ]);

            return false;
        }

        $email = new Email()
            ->from(new Address($surrogateAddress, $fromName.' via Discourse'))
            ->to($this->addresses->listAddress())
            ->subject($subject)
            ->text($body);

        $headers = $email->getHeaders();
        $headers->remove('Message-ID');
        $headers->addIdHeader('Message-ID', $messageId);

        if (null !== $inReplyTo) {
            $headers->addIdHeader('In-Reply-To', $inReplyTo);
            $headers->addIdHeader('References', $inReplyTo);
        }

        $this->mailer->send($email);

        $this->logger->info('Post relayed to the mailing list', [
            'message_id' => $messageId,
            'in_reply_to' => $inReplyTo,
            'subject' => $subject,
        ]);

        return true;
    }
}
