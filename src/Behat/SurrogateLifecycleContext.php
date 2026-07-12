<?php

declare(strict_types=1);

namespace App\Behat;

use App\Entity\SurrogateUser;
use App\Enum\SurrogateStatus;
use App\Mail\SurrogateAddressFactory;
use Behat\Behat\Context\Context;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\WorkflowInterface;

final class SurrogateLifecycleContext implements Context
{
    private ?SurrogateUser $surrogate = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        #[Target('surrogate_subscription')]
        private readonly WorkflowInterface $workflow,
        private readonly SurrogateAddressFactory $addressFactory,
    ) {
    }

    #[Given('a surrogate user for Discourse user :username with email :email')]
    public function aSurrogateUserExists(string $username, string $email): void
    {
        $this->surrogate = new SurrogateUser()
            ->setDiscourseUserId(42)
            ->setDiscourseUsername($username)
            ->setDisplayName(ucwords(str_replace('_', ' ', $username)))
            ->setRealEmail($email)
            ->setSurrogateAddress($this->addressFactory->addressFor($email));
        $this->entityManager->persist($this->surrogate);
        $this->entityManager->flush();
    }

    #[Given('a subscribed surrogate user for Discourse user :username with email :email')]
    public function aSubscribedSurrogateUserExists(string $username, string $email): void
    {
        $this->aSurrogateUserExists($username, $email);
        $this->apply('send_subscribe');
        $this->apply('receive_confirmation_request');
        $this->apply('complete_subscription');
    }

    #[When('the subscription request is sent')]
    public function theSubscriptionRequestIsSent(): void
    {
        $this->apply('send_subscribe');
    }

    #[When('the confirmation request is received')]
    public function theConfirmationRequestIsReceived(): void
    {
        $this->apply('receive_confirmation_request');
    }

    #[When('the subscription is confirmed')]
    public function theSubscriptionIsConfirmed(): void
    {
        $this->apply('complete_subscription');
    }

    #[When('the unsubscription starts')]
    public function theUnsubscriptionStarts(): void
    {
        $this->apply('start_unsubscribe');
    }

    #[When('the unsubscription is confirmed')]
    public function theUnsubscriptionIsConfirmed(): void
    {
        $this->apply('complete_unsubscribe');
    }

    #[When('the surrogate is disabled')]
    public function theSurrogateIsDisabled(): void
    {
        $this->apply('disable');
    }

    #[Then('the surrogate should be in state :state')]
    public function theSurrogateIsInState(string $state): void
    {
        $status = $this->reloadedSurrogate()->getStatus();

        if (SurrogateStatus::from($state) !== $status) {
            throw new \RuntimeException(\sprintf('Expected state "%s", got "%s".', $state, $status->value));
        }
    }

    #[Then('the surrogate should not be allowed to become subscribed')]
    public function theSurrogateCannotBeMarkedSubscribed(): void
    {
        if ($this->workflow->can($this->currentSurrogate(), 'complete_subscription')) {
            throw new \RuntimeException('The surrogate can unexpectedly be marked subscribed.');
        }
    }

    private function apply(string $transition): void
    {
        $this->workflow->apply($this->currentSurrogate(), $transition);
        $this->entityManager->flush();
    }

    private function currentSurrogate(): SurrogateUser
    {
        return $this->surrogate ?? throw new \LogicException('No surrogate user was created in this scenario.');
    }

    private function reloadedSurrogate(): SurrogateUser
    {
        $surrogate = $this->currentSurrogate();
        $this->entityManager->clear();

        return $this->entityManager->find(SurrogateUser::class, $surrogate->getId())
            ?? throw new \RuntimeException('Surrogate user not found in the database.');
    }
}
