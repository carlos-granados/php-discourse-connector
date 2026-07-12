<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\MessageSource;
use App\Repository\MessageMapRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Maps a Discourse post to the email Message-ID it corresponds to on the
 * mailing list, so replies can carry correct In-Reply-To/References headers.
 */
#[ORM\Entity(repositoryClass: MessageMapRepository::class)]
#[ORM\Index(columns: ['email_message_id'])]
class MessageMap
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(unique: true)]
    private int $discoursePostId;

    #[ORM\Column]
    private int $discourseTopicId;

    #[ORM\Column(length: 255)]
    private string $emailMessageId;

    #[ORM\Column(length: 32, enumType: MessageSource::class)]
    private MessageSource $source;

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

    public function getEmailMessageId(): string
    {
        return $this->emailMessageId;
    }

    public function setEmailMessageId(string $emailMessageId): static
    {
        $this->emailMessageId = $emailMessageId;

        return $this;
    }

    public function getSource(): MessageSource
    {
        return $this->source;
    }

    public function setSource(MessageSource $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
