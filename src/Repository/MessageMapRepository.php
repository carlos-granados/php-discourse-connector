<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MessageMap;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MessageMap>
 */
class MessageMapRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MessageMap::class);
    }

    public function findByDiscoursePostId(int $discoursePostId): ?MessageMap
    {
        return $this->findOneBy(['discoursePostId' => $discoursePostId]);
    }
}
