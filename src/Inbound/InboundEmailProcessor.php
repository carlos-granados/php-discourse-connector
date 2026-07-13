<?php

declare(strict_types=1);

namespace App\Inbound;

use App\Entity\InboundEmail;
use App\Entity\SurrogateUser;
use App\Enum\InboundEmailKind;
use App\Mail\ListSubscriptionMailer;
use App\Repository\SurrogateUserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Handles every email that arrives in the catch-all mailbox: stores it for
 * audit, auto-replies to ezmlm confirmation requests, and advances the
 * surrogate state machine on confirmations and welcome messages.
 */
final readonly class InboundEmailProcessor
{
    public function __construct(
        private EzmlmConfirmationMatcher $matcher,
        private SurrogateUserRepository $surrogates,
        private ListSubscriptionMailer $listMailer,
        private EntityManagerInterface $entityManager,
        #[Target('surrogate_subscription')]
        private WorkflowInterface $workflow,
        private LoggerInterface $logger,
    ) {
    }

    public function process(InboundMessage $message): void
    {
        $record = new InboundEmail()
            ->setFromAddress($message->from)
            ->setToAddress($message->to)
            ->setSubject($message->subject)
            ->setRawMessage($message->raw);
        $this->entityManager->persist($record);

        $classification = $this->matcher->classify($message);
        $surrogate = $this->surrogates->findBySurrogateAddress($message->to);

        match ($classification->kind) {
            InboundEmailKind::SubscribeConfirmation => $this->handleSubscribeConfirmation($message, $classification, $surrogate),
            InboundEmailKind::UnsubscribeConfirmation => $this->handleUnsubscribeConfirmation($message, $classification),
            InboundEmailKind::Welcome => $this->handleWelcome($surrogate),
            InboundEmailKind::Goodbye => $this->handleGoodbye($surrogate),
            InboundEmailKind::Bounce => $this->handleBounce($message),
            InboundEmailKind::PostConfirmation, InboundEmailKind::Other => null,
        };

        $record->markProcessed($classification->kind, $surrogate);
        $this->entityManager->flush();
    }

    private function handleSubscribeConfirmation(InboundMessage $message, InboundClassification $classification, ?SurrogateUser $surrogate): void
    {
        \assert(null !== $classification->confirmationAddress);

        $this->listMailer->replyToConfirmation($message->to, $classification->confirmationAddress, $message->subject);

        if ($surrogate instanceof SurrogateUser && $this->workflow->can($surrogate, 'receive_confirmation_request')) {
            $this->workflow->apply($surrogate, 'receive_confirmation_request');
        }
    }

    private function handleUnsubscribeConfirmation(InboundMessage $message, InboundClassification $classification): void
    {
        \assert(null !== $classification->confirmationAddress);

        // Reply even when no surrogate record exists: retired surrogates are
        // deleted before ezmlm confirms the unsubscription, but we still
        // control every address on the domain.
        $this->listMailer->replyToConfirmation($message->to, $classification->confirmationAddress, $message->subject);
    }

    private function handleWelcome(?SurrogateUser $surrogate): void
    {
        if ($surrogate instanceof SurrogateUser && $this->workflow->can($surrogate, 'complete_subscription')) {
            $this->workflow->apply($surrogate, 'complete_subscription');
        }
    }

    private function handleGoodbye(?SurrogateUser $surrogate): void
    {
        if ($surrogate instanceof SurrogateUser && $this->workflow->can($surrogate, 'complete_unsubscribe')) {
            $this->workflow->apply($surrogate, 'complete_unsubscribe');
        }
    }

    private function handleBounce(InboundMessage $message): void
    {
        $this->logger->warning('Bounce received in the catch-all mailbox', [
            'from' => $message->from,
            'to' => $message->to,
            'subject' => $message->subject,
        ]);
    }
}
