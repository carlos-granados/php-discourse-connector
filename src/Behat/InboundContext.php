<?php

declare(strict_types=1);

namespace App\Behat;

use App\Enum\InboundEmailKind;
use App\Inbound\InboundEmailProcessor;
use App\Inbound\InboundMessage;
use App\Mail\ListAddressResolver;
use App\Repository\InboundEmailRepository;
use App\Repository\SurrogateUserRepository;
use Behat\Behat\Context\Context;
use Behat\Step\Then;
use Behat\Step\When;

final readonly class InboundContext implements Context
{
    public const string COOKIE_ADDRESS = 'internals+sc.1770000000.abcdef@lists.php.net';
    public const string UNSUBSCRIBE_COOKIE_ADDRESS = 'internals+uc.1770000000.abcdef@lists.php.net';

    public function __construct(
        private InboundEmailProcessor $processor,
        private SurrogateUserRepository $surrogates,
        private InboundEmailRepository $inboundEmails,
        private ListAddressResolver $listAddresses,
    ) {
    }

    #[When('the inbox receives an ezmlm subscribe confirmation for the surrogate')]
    public function theInboxReceivesASubscribeConfirmation(): void
    {
        $this->processor->process(new InboundMessage(
            from: self::COOKIE_ADDRESS,
            to: $this->soleSurrogateAddress(),
            replyTo: self::COOKIE_ADDRESS,
            subject: 'confirm subscribe to '.$this->listAddresses->listAddress(),
            raw: "To confirm your subscription, reply to this message.\n",
        ));
    }

    #[When('the inbox receives an ezmlm unsubscribe confirmation for an unknown address')]
    public function theInboxReceivesAnUnsubscribeConfirmationForAnUnknownAddress(): void
    {
        $this->processor->process(new InboundMessage(
            from: self::UNSUBSCRIBE_COOKIE_ADDRESS,
            to: 'gone.user+example.com.aaaaaa@discourse.thephp.foundation',
            replyTo: self::UNSUBSCRIBE_COOKIE_ADDRESS,
            subject: 'confirm unsubscribe from '.$this->listAddresses->listAddress(),
            raw: "To confirm your unsubscription, reply to this message.\n",
        ));
    }

    #[When('the inbox receives the ezmlm welcome message for the surrogate')]
    public function theInboxReceivesTheWelcomeMessage(): void
    {
        $this->processor->process(new InboundMessage(
            from: $this->listAddresses->listLocalPart().'-help@'.$this->listAddresses->listDomain(),
            to: $this->soleSurrogateAddress(),
            replyTo: null,
            subject: 'WELCOME to '.$this->listAddresses->listAddress(),
            raw: "Welcome to the list!\n",
        ));
    }

    #[When('the inbox receives an unrelated email for the surrogate')]
    public function theInboxReceivesAnUnrelatedEmail(): void
    {
        $this->processor->process(new InboundMessage(
            from: 'random@example.org',
            to: $this->soleSurrogateAddress(),
            replyTo: null,
            subject: 'Hello there',
            raw: "Hi, unrelated content.\n",
        ));
    }

    #[Then('the inbound email should be recorded as :kind')]
    public function theInboundEmailShouldBeRecordedAs(string $kind): void
    {
        $records = $this->inboundEmails->findAll();

        if (1 !== \count($records)) {
            throw new \RuntimeException(\sprintf('Expected exactly 1 inbound email record, found %d.', \count($records)));
        }

        $actual = $records[0]->getKind();

        if (InboundEmailKind::from($kind) !== $actual) {
            throw new \RuntimeException(\sprintf('Expected kind "%s", got "%s".', $kind, $actual->value));
        }
    }

    private function soleSurrogateAddress(): string
    {
        $all = $this->surrogates->findAll();

        if (1 !== \count($all)) {
            throw new \LogicException(\sprintf('Expected exactly 1 surrogate user in this scenario, found %d.', \count($all)));
        }

        return $all[0]->getSurrogateAddress();
    }
}
