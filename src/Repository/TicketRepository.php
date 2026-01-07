<?php

namespace App\Repository;

use App\Entity\Ticket;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ticket>
 */
class TicketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ticket::class);
    }

    public function findByCreator($user): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.creator = :user')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findAssignedTo($user): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.assignee = :user')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findUnassigned(): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.assignee IS NULL')
            ->andWhere('t.deletedAt IS NULL')
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findAllSorted(?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.deletedAt IS NULL')
            ->orderBy('t.createdAt', 'DESC');

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    public function findFiltered(
        ?string $statusLabel,
        ?int $priorityId,
        ?\DateTimeInterface $fromDate,
        ?\DateTimeInterface $toDate
    ): array {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.status', 's')
            ->leftJoin('t.priority', 'p')
            ->leftJoin('t.category', 'c')
            ->leftJoin('t.creator', 'cr')
            ->leftJoin('t.assignee', 'a')
            ->addSelect('s', 'p', 'c', 'cr', 'a')
            ->andWhere('t.deletedAt IS NULL');

        if ($statusLabel) {
            $qb->andWhere('s.label = :status')->setParameter('status', $statusLabel);
        }

        if ($priorityId) {
            $qb->andWhere('p.id = :priority')->setParameter('priority', $priorityId);
        }

        if ($fromDate) {
            $qb->andWhere('t.createdAt >= :fromDate')->setParameter('fromDate', $fromDate);
        }

        if ($toDate) {
            $qb->andWhere('t.createdAt <= :toDate')->setParameter('toDate', $toDate);
        }

        return $qb->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getManagerStats(): array
    {
        $qb = $this->createQueryBuilder('t');
        $total = $qb->select('count(t.id)')
            ->andWhere('t.deletedAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
        
        $qb = $this->createQueryBuilder('t');
        $unassigned = $qb->select('count(t.id)')
            ->where('t.assignee IS NULL')
            ->andWhere('t.deletedAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();

        $qb = $this->createQueryBuilder('t');
        $open = $qb->select('count(t.id)')
            ->leftJoin('t.status', 's')
            ->andWhere('s.label IN (:open)')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('open', ['Ouvert', 'En cours'])
            ->getQuery()
            ->getSingleScalarResult();

        $resolvedTickets = $this->createQueryBuilder('t')
            ->leftJoin('t.status', 's')
            ->andWhere('s.label IN (:resolved)')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('resolved', ['Résolu', 'Fermé'])
            ->getQuery()
            ->getResult();

        $totalSeconds = 0;
        $resolvedCount = 0;
        foreach ($resolvedTickets as $ticket) {
            $createdAt = $ticket->getCreatedAt();
            $updatedAt = $ticket->getUpdatedAt();
            if ($createdAt && $updatedAt) {
                $totalSeconds += max(0, $updatedAt->getTimestamp() - $createdAt->getTimestamp());
                $resolvedCount++;
            }
        }
        $avgResolutionHours = $resolvedCount > 0 ? round(($totalSeconds / $resolvedCount) / 3600, 1) : null;

        $ticketsPerTech = $this->createQueryBuilder('t')
            ->select('u.id as id, u.name as name, COUNT(t.id) as total')
            ->leftJoin('t.assignee', 'u')
            ->andWhere('t.assignee IS NOT NULL')
            ->andWhere('t.deletedAt IS NULL')
            ->groupBy('u.id')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getArrayResult();

        return [
            'total' => $total,
            'open' => $open,
            'unassigned' => $unassigned,
            'avg_resolution_hours' => $avgResolutionHours,
            'tickets_per_tech' => $ticketsPerTech,
        ];
    }

    public function findDeletedByCreator($user): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.creator = :user')
            ->andWhere('t.deletedAt IS NOT NULL')
            ->setParameter('user', $user)
            ->orderBy('t.deletedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findDeletedAll(): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.deletedAt IS NOT NULL')
            ->orderBy('t.deletedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
