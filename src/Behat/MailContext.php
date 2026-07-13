<?php

declare(strict_types=1);

namespace App\Behat;

use App\Mail\ListAddressResolver;
use Behat\Behat\Context\Context;
use Behat\Step\Then;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\EventListener\MessageLoggerListener;
use Symfony\Component\Mime\Email;

final readonly class MailContext implements Context
{
    public function __construct(
        #[Autowire(service: 'mailer.message_logger_listener')]
        private MessageLoggerListener $messageLogger,
        private ListAddressResolver $listAddresses,
    ) {
    }

    #[Then('a subscription request should be emailed to the list')]
    public function aSubscriptionRequestShouldBeEmailedToTheList(): void
    {
        $this->assertEmailSentTo($this->listAddresses->subscribeAddress());
    }

    #[Then('an unsubscription request should be emailed to the list')]
    public function anUnsubscriptionRequestShouldBeEmailedToTheList(): void
    {
        $this->assertEmailSentTo($this->listAddresses->unsubscribeAddress());
    }

    #[Then('a confirmation reply should be emailed to the ezmlm cookie address')]
    public function aConfirmationReplyShouldBeEmailedToTheCookieAddress(): void
    {
        try {
            $this->assertEmailSentTo(InboundContext::COOKIE_ADDRESS);
        } catch (\RuntimeException) {
            $this->assertEmailSentTo(InboundContext::UNSUBSCRIBE_COOKIE_ADDRESS);
        }
    }

    #[Then('no email should be sent to the list')]
    public function noEmailShouldBeSentToTheList(): void
    {
        $sent = $this->sentEmails();

        if ([] !== $sent) {
            throw new \RuntimeException(\sprintf('Expected no emails, but %d were sent (first to: %s).', \count($sent), $this->recipientsOf($sent[0])));
        }
    }

    private function assertEmailSentTo(string $address): void
    {
        foreach ($this->sentEmails() as $email) {
            foreach ($email->getTo() as $to) {
                if ($to->getAddress() === $address) {
                    return;
                }
            }
        }

        $recipients = array_map($this->recipientsOf(...), $this->sentEmails());

        throw new \RuntimeException(\sprintf('No email sent to "%s". Emails sent to: %s', $address, [] === $recipients ? '(none)' : implode(', ', $recipients)));
    }

    /**
     * @return list<Email>
     */
    private function sentEmails(): array
    {
        return array_values(array_filter(
            $this->messageLogger->getEvents()->getMessages(),
            static fn (\Symfony\Component\Mime\RawMessage $message): bool => $message instanceof Email,
        ));
    }

    private function recipientsOf(Email $email): string
    {
        return implode('+', array_map(static fn (\Symfony\Component\Mime\Address $a): string => $a->getAddress(), $email->getTo()));
    }
}
