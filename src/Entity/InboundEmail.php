<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\InboundEmailKind;
use App\Repository\InboundEmailRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Audit record of an email received in the catch-all mailbox of the surrogate domain.
 */
#[ORM\Entity(repositoryClass: InboundEmailRepository::class)]
class InboundEmail
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(length: 320)]
    private string $fromAddress;

    #[ORM\Column(length: 320)]
    private string $toAddress;

    #[ORM\Column(length: 998)]
    private string $subject;

    #[ORM\Column(type: 'text')]
    private string $rawMessage;

    #[ORM\Column(length: 32, enumType: InboundEmailKind::class)]
    private InboundEmailKind $kind = InboundEmailKind::Other;

    #[ORM\ManyToOne(targetEntity: SurrogateUser::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?SurrogateUser $surrogateUser = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $receivedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $processedAt = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->receivedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getFromAddress(): string
    {
        return $this->fromAddress;
    }

    public function setFromAddress(string $fromAddress): static
    {
        $this->fromAddress = $fromAddress;

        return $this;
    }

    public function getToAddress(): string
    {
        return $this->toAddress;
    }

    public function setToAddress(string $toAddress): static
    {
        $this->toAddress = $toAddress;

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

    public function getRawMessage(): string
    {
        return $this->rawMessage;
    }

    public function setRawMessage(string $rawMessage): static
    {
        $this->rawMessage = $rawMessage;

        return $this;
    }

    public function getKind(): InboundEmailKind
    {
        return $this->kind;
    }

    public function setKind(InboundEmailKind $kind): static
    {
        $this->kind = $kind;

        return $this;
    }

    public function getSurrogateUser(): ?SurrogateUser
    {
        return $this->surrogateUser;
    }

    public function setSurrogateUser(?SurrogateUser $surrogateUser): static
    {
        $this->surrogateUser = $surrogateUser;

        return $this;
    }

    public function getReceivedAt(): \DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function getProcessedAt(): ?\DateTimeImmutable
    {
        return $this->processedAt;
    }

    public function markProcessed(InboundEmailKind $kind, ?SurrogateUser $surrogateUser): void
    {
        $this->kind = $kind;
        $this->surrogateUser = $surrogateUser;
        $this->processedAt = new \DateTimeImmutable();
    }
}
