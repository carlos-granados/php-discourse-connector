<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\OutboundMessageStatus;
use App\Repository\OutboundMessageRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Audit record of an email this connector sent (or tried to send) to the mailing list.
 */
#[ORM\Entity(repositoryClass: OutboundMessageRepository::class)]
class OutboundMessage
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    // No FK to SurrogateUser: audit records must survive user deletion without retaining PII
    #[ORM\Column]
    private int $discourseUserId;

    #[ORM\Column(unique: true)]
    private int $discoursePostId;

    #[ORM\Column]
    private int $discourseTopicId;

    #[ORM\Column(length: 255, unique: true)]
    private string $messageId;

    #[ORM\Column(length: 998)]
    private string $subject;

    #[ORM\Column(length: 32, enumType: OutboundMessageStatus::class)]
    private OutboundMessageStatus $status = OutboundMessageStatus::Pending;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getDiscourseUserId(): int
    {
        return $this->discourseUserId;
    }

    public function setDiscourseUserId(int $discourseUserId): static
    {
        $this->discourseUserId = $discourseUserId;

        return $this;
    }

    public function getDiscoursePostId(): int
    {
        return $this->discoursePostId;
    }

    public function setDiscoursePostId(int $discoursePostId): static
    {
        $this->discoursePostId = $discoursePostId;

        return $this;
    }

    public function getDiscourseTopicId(): int
    {
        return $this->discourseTopicId;
    }

    public function setDiscourseTopicId(int $discourseTopicId): static
    {
        $this->discourseTopicId = $discourseTopicId;

        return $this;
    }

    public function getMessageId(): string
    {
        return $this->messageId;
    }

    public function setMessageId(string $messageId): static
    {
        $this->messageId = $messageId;

        return $this;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    public function getStatus(): OutboundMessageStatus
    {
        return $this->status;
    }

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function markSent(): void
    {
        $this->status = OutboundMessageStatus::Sent;
        $this->sentAt = new \DateTimeImmutable();
        $this->errorMessage = null;
    }

    public function markFailed(string $errorMessage): void
    {
        $this->status = OutboundMessageStatus::Failed;
        $this->errorMessage = $errorMessage;
    }
}
