<?php

namespace App\Repository;

use App\Entity\Client;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Client>
 */
class ClientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Client::class);
    }

    public function createUserQueryBuilder(User $user): QueryBuilder
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.user = :user')
            ->setParameter('user', $user)
            ->orderBy('c.id', 'DESC');
    }
}