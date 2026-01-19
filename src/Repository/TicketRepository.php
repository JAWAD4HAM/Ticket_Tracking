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
        ?\DateTimeInterface $toDate,
        $assignee = null,
        ?string $filterType = null,
        ?\App\Entity\User $viewingTech = null
    ): array {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.status', 's')
            ->leftJoin('t.priority', 'p')
            ->leftJoin('t.category', 'c')
            ->leftJoin('t.creator', 'cr')
            ->leftJoin('t.assignee', 'a')
            ->addSelect('s', 'p', 'c', 'cr', 'a')
            ->andWhere('t.deletedAt IS NULL');

        if ($viewingTech) {
             // Technician sees: Unassigned OR Assigned to themselves
             $qb->andWhere('(t.assignee IS NULL OR t.assignee = :viewingTech)')
                ->setParameter('viewingTech', $viewingTech);
        }

        if ($statusLabel) {
            $qb->andWhere('s.label = :status')->setParameter('status', $statusLabel);
        }

        if ($priorityId) {
            $qb->andWhere('p.id = :priority')->setParameter('priority', $priorityId);
        }

        if ($assignee === 'unassigned') {
            $qb->andWhere('t.assignee IS NULL');
        } elseif ($assignee === 'assigned') {
            $qb->andWhere('t.assignee IS NOT NULL');
        } elseif ($assignee) {
            $qb->andWhere('t.assignee = :assignee')->setParameter('assignee', $assignee);
        }

        if ($filterType === 'late') {
            $qb->andWhere('t.slaDueAt < :now')
               ->andWhere('s.label NOT IN (:closedStatuses)')
               ->setParameter('now', new \DateTime())
               ->setParameter('closedStatuses', ['Résolu', 'Fermé', 'Resolved', 'Closed']);
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

    public function getManagerStats(?\DateTimeInterface $startDate = null, ?\DateTimeInterface $endDate = null): array
    {
        $qb = $this->createQueryBuilder('t');
        $qb->select('count(t.id)')
           ->andWhere('t.deletedAt IS NULL');
        
        if ($startDate) {
            $qb->andWhere('t.createdAt >= :startDate')->setParameter('startDate', $startDate);
        }
        if ($endDate) {
            $qb->andWhere('t.createdAt <= :endDate')->setParameter('endDate', $endDate);
        }
        $total = $qb->getQuery()->getSingleScalarResult();
        
        $qb = $this->createQueryBuilder('t');
        $qb->select('count(t.id)')
            ->where('t.assignee IS NULL')
            ->andWhere('t.deletedAt IS NULL');
        if ($startDate) {
            $qb->andWhere('t.createdAt >= :startDate')->setParameter('startDate', $startDate);
        }
        if ($endDate) {
            $qb->andWhere('t.createdAt <= :endDate')->setParameter('endDate', $endDate);
        }
        $unassigned = $qb->getQuery()->getSingleScalarResult();

        // Open Tickets (Strictly Open, not In Progress)
    $qb = $this->createQueryBuilder('t');
    $qb->select('count(t.id)')
        ->leftJoin('t.status', 's')
        ->andWhere('s.label IN (:open)')
        ->andWhere('t.deletedAt IS NULL')
        ->setParameter('open', ['Ouvert', 'Open']);
    if ($startDate) {
        $qb->andWhere('t.createdAt >= :startDate')->setParameter('startDate', $startDate);
    }
    if ($endDate) {
        $qb->andWhere('t.createdAt <= :endDate')->setParameter('endDate', $endDate);
    }
    $open = $qb->getQuery()->getSingleScalarResult();

    // Late Tickets (SLA exceeded)
    $qb = $this->createQueryBuilder('t');
    $qb->select('count(t.id)')
        ->leftJoin('t.status', 's')
        ->where('t.slaDueAt < :now')
        ->andWhere('s.label NOT IN (:closed)')
        ->andWhere('t.deletedAt IS NULL')
        ->setParameter('now', new \DateTime())
        ->setParameter('closed', ['Résolu', 'Fermé', 'Resolved', 'Closed']);
    if ($startDate) {
        $qb->andWhere('t.createdAt >= :startDate')->setParameter('startDate', $startDate);
    }
    if ($endDate) {
        $qb->andWhere('t.createdAt <= :endDate')->setParameter('endDate', $endDate);
    }
    $late = $qb->getQuery()->getSingleScalarResult();

    // Incoming Tickets (Last 7 Days Fixed)
    $sevenDaysAgo = new \DateTime('-7 days');
    $qb = $this->createQueryBuilder('t');
    $qb->select('count(t.id)')
       ->andWhere('t.deletedAt IS NULL')
       ->andWhere('t.createdAt >= :sevenDaysAgo')
       ->setParameter('sevenDaysAgo', $sevenDaysAgo);
    $incoming = $qb->getQuery()->getSingleScalarResult();


    // Resolved Tickets (Strictly Resolved, not Closed)
    $qb = $this->createQueryBuilder('t')
        ->select('t')
        ->leftJoin('t.status', 's')
        ->andWhere('s.label IN (:resolved)')
        ->andWhere('t.deletedAt IS NULL')
        ->setParameter('resolved', ['Résolu', 'Resolved']);
    if ($startDate) {
        $qb->andWhere('t.createdAt >= :startDate')->setParameter('startDate', $startDate);
    }
    if ($endDate) {
        $qb->andWhere('t.createdAt <= :endDate')->setParameter('endDate', $endDate);
    }
    $resolvedTicketsEntities = $qb->getQuery()->getResult();
    $resolved = count($resolvedTicketsEntities);

        // Avg Resolution Time
        $totalSeconds = 0;
        $resolvedCountCalc = 0;
        foreach ($resolvedTicketsEntities as $ticket) {
            $createdAt = $ticket->getCreatedAt();
            $updatedAt = $ticket->getUpdatedAt();
            // Assuming updatedAt is resolution time for resolved tickets
            if ($createdAt && $updatedAt) {
                $totalSeconds += max(0, $updatedAt->getTimestamp() - $createdAt->getTimestamp());
                $resolvedCountCalc++;
            }
        }
        $avgResolutionHours = $resolvedCountCalc > 0 ? round(($totalSeconds / $resolvedCountCalc) / 3600, 1) : null;

        $closed = $this->countByStatus(['Fermé', 'Closed'], $startDate, $endDate);
        $inProgress = $this->countByStatus(['En cours', 'In progress', 'In Progress'], $startDate, $endDate);
        $assigned = $this->countAssigned($startDate, $endDate);


        $ticketsPerTech = $this->createQueryBuilder('t')
            ->select('u.id as id, u.name as name, COUNT(t.id) as total')
            ->leftJoin('t.assignee', 'u')
            ->andWhere('t.assignee IS NOT NULL')
            ->andWhere('t.deletedAt IS NULL');
        
        if ($startDate) {
            $ticketsPerTech->andWhere('t.createdAt >= :startDate')->setParameter('startDate', $startDate);
        }
        if ($endDate) {
            $ticketsPerTech->andWhere('t.createdAt <= :endDate')->setParameter('endDate', $endDate);
        }
            
        $ticketsPerTech = $ticketsPerTech->groupBy('u.id')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getArrayResult();

        return [
            'total' => $total,
            'open' => $open,
            'unassigned' => $unassigned,
            'late' => $late,
            'incoming' => $incoming,
            'resolved' => $resolved,
            'closed' => $closed,
            'in_progress' => $inProgress,
            'assigned' => $assigned,
            'avg_resolution_hours' => $avgResolutionHours,
            'tickets_per_tech' => $ticketsPerTech,
        ];
    }
    
    private function countByStatus(array $statusLabels, ?\DateTimeInterface $startDate = null, ?\DateTimeInterface $endDate = null): int
    {
        $qb = $this->createQueryBuilder('t')
            ->select('count(t.id)')
            ->leftJoin('t.status', 's')
            ->andWhere('s.label IN (:status)')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('status', $statusLabels);
            
        if ($startDate) {
            $qb->andWhere('t.createdAt >= :startDate')->setParameter('startDate', $startDate);
        }
        if ($endDate) {
            $qb->andWhere('t.createdAt <= :endDate')->setParameter('endDate', $endDate);
        }
        
        return (int) $qb->getQuery()->getSingleScalarResult();
    }
    
    private function countAssigned(?\DateTimeInterface $startDate = null, ?\DateTimeInterface $endDate = null): int
    {
        $qb = $this->createQueryBuilder('t')
            ->select('count(t.id)')
            ->where('t.assignee IS NOT NULL')
            ->andWhere('t.deletedAt IS NULL');
            
        if ($startDate) {
            $qb->andWhere('t.createdAt >= :startDate')->setParameter('startDate', $startDate);
        }
        if ($endDate) {
            $qb->andWhere('t.createdAt <= :endDate')->setParameter('endDate', $endDate);
        }
        
        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function getTicketVolumeOverTime(?\DateTimeInterface $startDate = null, ?\DateTimeInterface $endDate = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->select("SUBSTRING(t.createdAt, 1, 10) as date, COUNT(t.id) as count")
            ->andWhere('t.deletedAt IS NULL');
            
        if ($startDate) {
            $qb->andWhere('t.createdAt >= :startDate')->setParameter('startDate', $startDate);
        }
        if ($endDate) {
            $qb->andWhere('t.createdAt <= :endDate')->setParameter('endDate', $endDate);
        }
        
        return $qb->groupBy('date')
            ->orderBy('date', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    public function getStatusDistribution(?\DateTimeInterface $startDate = null, ?\DateTimeInterface $endDate = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->select('s.label as status, COUNT(t.id) as count')
            ->leftJoin('t.status', 's')
            ->andWhere('t.deletedAt IS NULL');

        if ($startDate) {
            $qb->andWhere('t.createdAt >= :startDate')->setParameter('startDate', $startDate);
        }
        if ($endDate) {
            $qb->andWhere('t.createdAt <= :endDate')->setParameter('endDate', $endDate);
        }

        return $qb->groupBy('s.id')
            ->getQuery()
            ->getArrayResult();
    }

    public function getAdminStats(): array
    {
        $qb = $this->createQueryBuilder('t');
        $unassigned = $qb->select('count(t.id)')
            ->where('t.assignee IS NULL')
            ->andWhere('t.deletedAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();

        $qb = $this->createQueryBuilder('t');
        $urgentQ = $qb->select('count(t.id) as count, p.id as priority_id')
            ->leftJoin('t.priority', 'p')
            ->where('p.label = :label') // Use Label 'Urgent'
            ->setParameter('label', 'Urgent')
            ->andWhere('t.deletedAt IS NULL')
            ->groupBy('p.id')
            ->getQuery()
            ->getOneOrNullResult(); // Might be null if no urgent tickets

        $urgent = $urgentQ ? $urgentQ['count'] : 0;
        
        // We need the ID even if count is 0, so let's find the Priority entity separately if needed,
        // or just return what we found. If we found nothing, we can't link to it easily without querying Priority repo.
        // It's better to fetch the ID in the Controller from PriorityRepository to be safe, but let's try to return what we have.
        // Actually, let's just return the count here, and let the Controller fetch the Priority ID.
        
        return [
            'unassigned' => $unassigned,
            'urgent' => $urgent,
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
    public function getReportVolumeMetrics(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $qb = $this->createQueryBuilder('t')
            ->select('count(t.id)')
            ->andWhere('t.createdAt >= :start')
            ->andWhere('t.createdAt <= :end')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate);
        $newTickets = (int) $qb->getQuery()->getSingleScalarResult();

        $qb = $this->createQueryBuilder('t')
            ->select('count(t.id)')
            ->leftJoin('t.status', 's')
            ->andWhere('s.label IN (:resolved)')
            ->andWhere('t.updatedAt >= :start') // Approximation: Resolved in this period
            ->andWhere('t.updatedAt <= :end')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->setParameter('resolved', ['Résolu', 'Fermé', 'Resolved', 'Closed']);
        $resolvedTickets = (int) $qb->getQuery()->getSingleScalarResult();

        return [
            'new' => $newTickets,
            'resolved' => $resolvedTickets,
            'backlog_growth' => $newTickets - $resolvedTickets
        ];
    }

    public function getEfficiencyMetrics(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
         // Avg Resolution Time
         $qb = $this->createQueryBuilder('t')
            ->select('t')
            ->leftJoin('t.status', 's')
            ->andWhere('s.label IN (:resolved)')
            ->andWhere('t.updatedAt >= :start')
            ->andWhere('t.updatedAt <= :end')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->setParameter('resolved', ['Résolu', 'Fermé', 'Resolved', 'Closed']);
        
        $resolvedEntities = $qb->getQuery()->getResult();
        $totalSeconds = 0;
        $count = 0;
        foreach ($resolvedEntities as $ticket) {
             $created = $ticket->getCreatedAt();
             $updated = $ticket->getUpdatedAt();
             if ($created && $updated) {
                 $totalSeconds += ($updated->getTimestamp() - $created->getTimestamp());
                 $count++;
             }
        }
        $avgResolutionHours = $count > 0 ? round(($totalSeconds / $count) / 3600, 1) : 0;

        // SLA Breach Rate
        $qb = $this->createQueryBuilder('t')
            ->where('t.createdAt >= :start')
            ->andWhere('t.createdAt <= :end')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate);
        $allPeriodTickets = $qb->getQuery()->getResult();
        
        $breachedCount = 0;
        $totalPeriod = count($allPeriodTickets);
        $now = new \DateTime();
        
        foreach ($allPeriodTickets as $ticket) {
            $sla = $ticket->getSlaDueAt();
            if (!$sla) continue;
            
            $isResolved = in_array($ticket->getStatus()->getLabel(), ['Résolu', 'Fermé', 'Resolved', 'Closed']);
            $compareTime = $isResolved ? $ticket->getUpdatedAt() : $now;
            
            if ($sla < $compareTime) {
                $breachedCount++;
            }
        }
        $slaBreachRate = $totalPeriod > 0 ? round(($breachedCount / $totalPeriod) * 100, 1) : 0;

        return [
            'avg_resolution_hours' => $avgResolutionHours,
            'sla_breach_rate' => $slaBreachRate,
            'first_response_time' => 0 
        ];
    }

    public function getCategoryBreakdown(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('t')
            ->select('c.label as label, count(t.id) as count')
            ->leftJoin('t.category', 'c')
            ->where('t.createdAt >= :start')
            ->andWhere('t.createdAt <= :end')
            ->andWhere('t.deletedAt IS NULL')
            ->groupBy('c.id')
            ->orderBy('count', 'DESC')
            ->setMaxResults(3) // Top 3
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getArrayResult();
    }

    public function getPriorityBreakdown(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
         return $this->createQueryBuilder('t')
            ->select('p.label as label, count(t.id) as count')
            ->leftJoin('t.priority', 'p')
            ->where('t.createdAt >= :start')
            ->andWhere('t.createdAt <= :end')
            ->andWhere('t.deletedAt IS NULL')
            ->groupBy('p.id')
            ->orderBy('p.level', 'ASC')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getArrayResult();
    }

    public function getTechnicianPerformance(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('t')
            ->select('u.name as name, count(t.id) as count')
            ->leftJoin('t.assignee', 'u')
            ->leftJoin('t.status', 's')
            ->where('s.label IN (:resolved)') // Solved tickets
            ->andWhere('t.updatedAt >= :start') // Solved in period
            ->andWhere('t.updatedAt <= :end')
            ->andWhere('t.assignee IS NOT NULL')
            ->andWhere('t.deletedAt IS NULL')
            ->groupBy('u.id')
            ->orderBy('count', 'DESC')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->setParameter('resolved', ['Résolu', 'Fermé', 'Resolved', 'Closed'])
            ->getQuery()
            ->getArrayResult();
    }
}
