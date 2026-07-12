<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\SurrogateStatus;
use App\Repository\SurrogateUserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: SurrogateUserRepository::class)]
class SurrogateUser
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(unique: true)]
    private int $discourseUserId;

    #[ORM\Column(length: 255)]
    private string $discourseUsername;

    #[ORM\Column(length: 255)]
    private string $displayName;

    #[ORM\Column(length: 320, unique: true)]
    private string $realEmail;

    #[ORM\Column(length: 320, unique: true)]
    private string $surrogateAddress;

    #[ORM\Column(length: 32, enumType: SurrogateStatus::class)]
    private SurrogateStatus $status = SurrogateStatus::Pending;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
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
        $this->touch();

        return $this;
    }

    public function getDiscourseUsername(): string
    {
        return $this->discourseUsername;
    }

    public function setDiscourseUsername(string $discourseUsername): static
    {
        $this->discourseUsername = $discourseUsername;
        $this->touch();

        return $this;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): static
    {
        $this->displayName = $displayName;
        $this->touch();

        return $this;
    }

    public function getRealEmail(): string
    {
        return $this->realEmail;
    }

    public function setRealEmail(string $realEmail): static
    {
        $this->realEmail = $realEmail;
        $this->touch();

        return $this;
    }

    public function getSurrogateAddress(): string
    {
        return $this->surrogateAddress;
    }

    public function setSurrogateAddress(string $surrogateAddress): static
    {
        $this->surrogateAddress = $surrogateAddress;
        $this->touch();

        return $this;
    }

    public function getStatus(): SurrogateStatus
    {
        return $this->status;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Used by the surrogate_subscription workflow marking store.
     */
    public function getMarking(): string
    {
        return $this->status->value;
    }

    /**
     * Used by the surrogate_subscription workflow marking store.
     *
     * @param array<string, mixed> $context
     */
    public function setMarking(string $marking, array $context = []): void
    {
        $this->status = SurrogateStatus::from($marking);
        $this->touch();
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
