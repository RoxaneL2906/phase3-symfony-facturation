<?php

namespace App\Repository;

use App\Entity\Invoice;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Invoice>
 */
class InvoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invoice::class);
    }

    public function countByMonth(int $year, int $month): int
    {
        $start = new \DateTimeImmutable("$year-$month-01 00:00:00");
        $end = $start->modify('last day of this month')->setTime(23, 59, 59);

        return $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.createdAt >= :start')
            ->andWhere('i.createdAt <= :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function createUserQueryBuilder(User $user, ?string $status = null, array $filters = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('i')
            ->andWhere('i.user = :user')
            ->setParameter('user', $user)
            ->orderBy('i.id', 'DESC');

        if ($status) {
            $qb->andWhere('i.status = :status')
               ->setParameter('status', $status);
        }

        if (!empty($filters['client'])) {
            $qb->andWhere('i.client = :client')
               ->setParameter('client', $filters['client']);
        }

        if (!empty($filters['month']) && !empty($filters['year'])) {
            $start = new \DateTimeImmutable($filters['year'] . '-' . $filters['month'] . '-01 00:00:00');
            $end = $start->modify('last day of this month')->setTime(23, 59, 59);
            $qb->andWhere('i.createdAt >= :start')
               ->andWhere('i.createdAt <= :end')
               ->setParameter('start', $start)
               ->setParameter('end', $end);
        } elseif (!empty($filters['month'])) {
            $year = date('Y');
            $start = new \DateTimeImmutable($year . '-' . $filters['month'] . '-01 00:00:00');
            $end = $start->modify('last day of this month')->setTime(23, 59, 59);
            $qb->andWhere('i.createdAt >= :start')
               ->andWhere('i.createdAt <= :end')
               ->setParameter('start', $start)
               ->setParameter('end', $end);
        } elseif (!empty($filters['year'])) {
            $start = new \DateTimeImmutable($filters['year'] . '-01-01 00:00:00');
            $end = new \DateTimeImmutable($filters['year'] . '-12-31 23:59:59');
            $qb->andWhere('i.createdAt >= :start')
               ->andWhere('i.createdAt <= :end')
               ->setParameter('start', $start)
               ->setParameter('end', $end);
        }

        if (!empty($filters['date_from'])) {
            $qb->andWhere('i.createdAt >= :date_from')
               ->setParameter('date_from', new \DateTimeImmutable($filters['date_from'] . ' 00:00:00'));
        }

        if (!empty($filters['date_to'])) {
            $qb->andWhere('i.createdAt <= :date_to')
               ->setParameter('date_to', new \DateTimeImmutable($filters['date_to'] . ' 23:59:59'));
        }

        return $qb;
    }
}