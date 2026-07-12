<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\OutboundMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OutboundMessage>
 */
class OutboundMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OutboundMessage::class);
    }

    public function findByDiscoursePostId(int $discoursePostId): ?OutboundMessage
    {
        return $this->findOneBy(['discoursePostId' => $discoursePostId]);
    }
}
