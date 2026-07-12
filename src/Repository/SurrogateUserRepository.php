<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SurrogateUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SurrogateUser>
 */
class SurrogateUserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SurrogateUser::class);
    }

    public function findByDiscourseUserId(int $discourseUserId): ?SurrogateUser
    {
        return $this->findOneBy(['discourseUserId' => $discourseUserId]);
    }

    public function findBySurrogateAddress(string $surrogateAddress): ?SurrogateUser
    {
        return $this->findOneBy(['surrogateAddress' => $surrogateAddress]);
    }
}
