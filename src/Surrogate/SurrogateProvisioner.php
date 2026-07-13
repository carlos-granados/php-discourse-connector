<?php

declare(strict_types=1);

namespace App\Surrogate;

use App\Entity\SurrogateUser;
use App\Mail\ListSubscriptionMailer;
use App\Mail\SurrogateAddressFactory;
use App\Repository\SurrogateUserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Manages the surrogate lifecycle: creation + list subscription on user
 * registration, sync on user updates (including email changes, which
 * re-provision the surrogate), and removal on user deletion.
 *
 * All methods are idempotent: Discourse may redeliver webhooks or deliver
 * them out of order.
 */
final readonly class SurrogateProvisioner
{
    public function __construct(
        private SurrogateUserRepository $surrogates,
        private EntityManagerInterface $entityManager,
        private SurrogateAddressFactory $addressFactory,
        private ListSubscriptionMailer $listMailer,
        #[Target('surrogate_subscription')]
        private WorkflowInterface $workflow,
        private LoggerInterface $logger,
    ) {
    }

    public function provision(int $discourseUserId, string $username, ?string $displayName, ?string $email, bool $staged = false): void
    {
        if ($this->shouldIgnore($discourseUserId, $staged)) {
            return;
        }

        $existing = $this->surrogates->findByDiscourseUserId($discourseUserId);

        if ($existing instanceof SurrogateUser) {
            // Redelivered webhook or backfill overlap: treat as a sync
            $this->sync($discourseUserId, $username, $displayName, $email, $staged);

            return;
        }

        if (null === $email) {
            $this->logger->error('Cannot provision surrogate: webhook payload contains no email (configure the Discourse webhook to include user emails)', [
                'discourse_user_id' => $discourseUserId,
                'username' => $username,
            ]);

            return;
        }

        $surrogate = new SurrogateUser()
            ->setDiscourseUserId($discourseUserId)
            ->setDiscourseUsername($username)
            ->setDisplayName($displayName ?? $username)
            ->setRealEmail(self::normalizeEmail($email))
            ->setSurrogateAddress($this->addressFactory->addressFor($email));

        $this->entityManager->persist($surrogate);
        $this->entityManager->flush();

        $this->startSubscription($surrogate);

        $this->logger->info('Surrogate provisioned', [
            'discourse_user_id' => $discourseUserId,
            'surrogate_address' => $surrogate->getSurrogateAddress(),
        ]);
    }

    public function sync(int $discourseUserId, string $username, ?string $displayName, ?string $email, bool $staged = false): void
    {
        if ($this->shouldIgnore($discourseUserId, $staged)) {
            return;
        }

        $surrogate = $this->surrogates->findByDiscourseUserId($discourseUserId);

        if (!$surrogate instanceof SurrogateUser) {
            $this->provision($discourseUserId, $username, $displayName, $email, $staged);

            return;
        }

        $surrogate->setDiscourseUsername($username);
        $surrogate->setDisplayName($displayName ?? $username);
        $this->entityManager->flush();

        if (null === $email || self::normalizeEmail($email) === $surrogate->getRealEmail()) {
            return;
        }

        // Email changed: unsubscribe the old surrogate address and re-provision.
        // MessageMap/OutboundMessage carry no FK to the surrogate, so threading
        // survives the replacement.
        $this->listMailer->requestUnsubscription($surrogate->getSurrogateAddress());
        $this->entityManager->remove($surrogate);
        $this->entityManager->flush();

        $this->logger->info('Surrogate retired after email change', ['discourse_user_id' => $discourseUserId]);

        $this->provision($discourseUserId, $username, $displayName, $email, $staged);
    }

    public function retire(int $discourseUserId): void
    {
        $surrogate = $this->surrogates->findByDiscourseUserId($discourseUserId);

        if (!$surrogate instanceof SurrogateUser) {
            return;
        }

        $this->listMailer->requestUnsubscription($surrogate->getSurrogateAddress());

        // Deleting the row removes all PII; the confirmation dance for the
        // unsubscription is handled by the inbound processor, which replies to
        // ezmlm confirmation requests for any address on our domain even
        // without a surrogate record.
        $this->entityManager->remove($surrogate);
        $this->entityManager->flush();

        $this->logger->info('Surrogate retired', ['discourse_user_id' => $discourseUserId]);
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private function startSubscription(SurrogateUser $surrogate): void
    {
        $this->listMailer->requestSubscription($surrogate->getSurrogateAddress());
        $this->workflow->apply($surrogate, 'send_subscribe');
        $this->entityManager->flush();
    }

    private function shouldIgnore(int $discourseUserId, bool $staged): bool
    {
        if ($discourseUserId <= 0) {
            // Discourse system users (system, discobot) have ids <= 0
            $this->logger->debug('Ignoring Discourse system user', ['discourse_user_id' => $discourseUserId]);

            return true;
        }

        if ($staged) {
            // Staged users are mail-in placeholders, not real forum accounts
            $this->logger->debug('Ignoring staged Discourse user', ['discourse_user_id' => $discourseUserId]);

            return true;
        }

        return false;
    }
}
