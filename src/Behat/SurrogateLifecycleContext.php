<?php

declare(strict_types=1);

namespace App\Behat;

use App\Entity\SurrogateUser;
use App\Enum\SurrogateStatus;
use App\Mail\SurrogateAddressFactory;
use App\Repository\SurrogateUserRepository;
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
        private readonly SurrogateUserRepository $surrogates,
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

    #[Then('a surrogate for Discourse user :username should exist')]
    public function aSurrogateForDiscourseUserShouldExist(string $username): void
    {
        $this->findByUsername($username) ?? throw new \RuntimeException(\sprintf('No surrogate found for Discourse user "%s".', $username));
    }

    #[Then('no surrogate for Discourse user :username should exist')]
    public function noSurrogateForDiscourseUserShouldExist(string $username): void
    {
        if ($this->findByUsername($username) instanceof SurrogateUser) {
            throw new \RuntimeException(\sprintf('A surrogate for Discourse user "%s" unexpectedly exists.', $username));
        }
    }

    #[Then('the surrogate should have display name :displayName')]
    public function theSurrogateShouldHaveDisplayName(string $displayName): void
    {
        $actual = $this->reloadedSurrogate()->getDisplayName();

        if ($displayName !== $actual) {
            throw new \RuntimeException(\sprintf('Expected display name "%s", got "%s".', $displayName, $actual));
        }
    }

    #[Then('the surrogate should have real email :email')]
    public function theSurrogateShouldHaveRealEmail(string $email): void
    {
        $actual = $this->reloadedSurrogate()->getRealEmail();

        if ($email !== $actual) {
            throw new \RuntimeException(\sprintf('Expected real email "%s", got "%s".', $email, $actual));
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

    /**
     * The single surrogate of the scenario, freshly loaded from the database.
     * Surrogates may be created outside this context (e.g. by webhook
     * processing), so this does not rely on the locally cached instance.
     */
    private function reloadedSurrogate(): SurrogateUser
    {
        $this->entityManager->clear();
        $all = $this->surrogates->findAll();

        if (1 !== \count($all)) {
            throw new \RuntimeException(\sprintf('Expected exactly 1 surrogate user in this scenario, found %d.', \count($all)));
        }

        return $all[0];
    }

    private function findByUsername(string $username): ?SurrogateUser
    {
        $this->entityManager->clear();

        return $this->surrogates->findOneBy(['discourseUsername' => $username]);
    }
}
