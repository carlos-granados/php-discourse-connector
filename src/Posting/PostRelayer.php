<?php

declare(strict_types=1);

namespace App\Posting;

use App\Discourse\DiscourseApi;
use App\Entity\MessageMap;
use App\Entity\OutboundMessage;
use App\Entity\SurrogateUser;
use App\Enum\MessageSource;
use App\Enum\SurrogateStatus;
use App\Mail\ListPostMailer;
use App\Mail\MessageIdFactory;
use App\Mail\PostToEmailTransformer;
use App\Message\RelayPost;
use App\Repository\MessageMapRepository;
use App\Repository\OutboundMessageRepository;
use App\Repository\SurrogateUserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Relays a Discourse post to the mailing list: applies the safety guards,
 * renders the email, resolves threading, sends it and records the audit trail.
 */
final readonly class PostRelayer
{
    public function __construct(
        private DiscourseApi $discourse,
        private SurrogateUserRepository $surrogates,
        private OutboundMessageRepository $outboundMessages,
        private MessageMapRepository $messageMaps,
        private PostToEmailTransformer $transformer,
        private MessageIdFactory $messageIds,
        private ListPostMailer $mailer,
        private EntityManagerInterface $entityManager,
        #[Autowire(env: 'int:MIRROR_CATEGORY_ID')]
        private int $mirrorCategoryId,
        private LoggerInterface $logger,
    ) {
    }

    public function relay(RelayPost $post): void
    {
        if (!$this->shouldRelay($post)) {
            return;
        }

        $surrogate = $this->surrogates->findByDiscourseUserId($post->discourseUserId);

        if (!$surrogate instanceof SurrogateUser || SurrogateStatus::Subscribed !== $surrogate->getStatus()) {
            $this->logger->warning('Post not relayed: author has no subscribed surrogate', [
                'post_id' => $post->postId,
                'discourse_user_id' => $post->discourseUserId,
            ]);

            return;
        }

        $body = $this->transformer->toPlainText($this->discourse->fetchPostRaw($post->postId));
        $subject = $this->subjectFor($post);
        $messageId = $this->messageIds->forPost($post->topicId, $post->postId);
        $inReplyTo = $this->resolveInReplyTo($post);

        $sent = $this->mailer->send(
            $surrogate->getSurrogateAddress(),
            $surrogate->getDisplayName(),
            $subject,
            $body,
            $messageId,
            $inReplyTo,
        );

        if (!$sent) {
            return; // kill switch engaged; nothing recorded so a later run can retry
        }

        $this->record($post, $messageId, $subject);
    }

    private function shouldRelay(RelayPost $post): bool
    {
        if ($post->viaEmail) {
            // Loop guard: this post is the mirror of a list email; sending it
            // back would echo every list message onto the list.
            $this->logger->debug('Post skipped: arrived via email (mirror loop guard)', ['post_id' => $post->postId]);

            return false;
        }

        if ($post->categoryId !== $this->mirrorCategoryId) {
            $this->logger->debug('Post skipped: not in the mirrored category', ['post_id' => $post->postId]);

            return false;
        }

        if ($this->outboundMessages->findByDiscoursePostId($post->postId) instanceof OutboundMessage) {
            // Already relayed (redelivered webhook): idempotent no-op.
            $this->logger->debug('Post skipped: already relayed', ['post_id' => $post->postId]);

            return false;
        }

        return true;
    }

    private function resolveInReplyTo(RelayPost $post): ?string
    {
        $parentNumber = $post->replyToPostNumber ?? ($post->postNumber > 1 ? 1 : null);

        if (null === $parentNumber) {
            return null; // new topic
        }

        $parentId = $this->discourse->resolvePostId($post->topicId, $parentNumber);

        if (null === $parentId) {
            return null;
        }

        $mapped = $this->messageMaps->findByDiscoursePostId($parentId);

        if ($mapped instanceof MessageMap) {
            return $mapped->getEmailMessageId();
        }

        // Parent came from the list and we have not mapped it yet: recover the
        // original Message-ID from its raw email and remember it.
        $listMessageId = $this->discourse->fetchListMessageId($parentId);

        if (null !== $listMessageId) {
            $this->entityManager->persist(
                new MessageMap()
                    ->setDiscoursePostId($parentId)
                    ->setDiscourseTopicId($post->topicId)
                    ->setEmailMessageId($listMessageId)
                    ->setSource(MessageSource::List),
            );
            $this->entityManager->flush();

            return $listMessageId;
        }

        // Defensive fallback: the canonical id for a Discourse-originated parent.
        return $this->messageIds->forPost($post->topicId, $parentId);
    }

    private function subjectFor(RelayPost $post): string
    {
        if ($post->postNumber <= 1) {
            return $post->topicTitle;
        }

        return str_starts_with(mb_strtolower($post->topicTitle), 're:')
            ? $post->topicTitle
            : 'Re: '.$post->topicTitle;
    }

    private function record(RelayPost $post, string $messageId, string $subject): void
    {
        $outbound = new OutboundMessage()
            ->setDiscourseUserId($post->discourseUserId)
            ->setDiscoursePostId($post->postId)
            ->setDiscourseTopicId($post->topicId)
            ->setMessageId($messageId)
            ->setSubject($subject);
        $outbound->markSent();
        $this->entityManager->persist($outbound);

        $this->entityManager->persist(
            new MessageMap()
                ->setDiscoursePostId($post->postId)
                ->setDiscourseTopicId($post->topicId)
                ->setEmailMessageId($messageId)
                ->setSource(MessageSource::Connector),
        );

        $this->entityManager->flush();
    }
}
