<?php

namespace App\Repository;

use App\Entity\Invoice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
}