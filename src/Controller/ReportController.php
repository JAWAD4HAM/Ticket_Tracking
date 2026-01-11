<?php

namespace App\Controller;

use App\Entity\Ticket;
use App\Repository\TicketRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\StreamedResponse;

use Dompdf\Dompdf;
use Dompdf\Options;

#[Route('/report')]
class ReportController extends AbstractController
{
    #[Route('/monthly', name: 'app_report_monthly', methods: ['GET'])]
    #[IsGranted('ROLE_MANAGER')]
    public function monthly(TicketRepository $ticketRepository): Response
    {
        $startDate = new \DateTime('first day of this month 00:00:00');
        $endDate = new \DateTime('last day of this month 23:59:59');

        $prevStartDate = (clone $startDate)->modify('-1 month');
        $prevEndDate = (clone $endDate)->modify('-1 month');

        // 1. Volume Metrics
        $volume = $ticketRepository->getReportVolumeMetrics($startDate, $endDate);
        $prevVolume = $ticketRepository->getReportVolumeMetrics($prevStartDate, $prevEndDate);

        // Calculate comparison %
        $volumeComparison = 0;
        if ($prevVolume['new'] > 0) {
            $volumeComparison = round((($volume['new'] - $prevVolume['new']) / $prevVolume['new']) * 100);
        }

        // 2. Efficiency Metrics
        $efficiency = $ticketRepository->getEfficiencyMetrics($startDate, $endDate);

        // 3. Categorization
        $categories = $ticketRepository->getCategoryBreakdown($startDate, $endDate);
        $priorities = $ticketRepository->getPriorityBreakdown($startDate, $endDate);

        // 4. Team Performance
        $team = $ticketRepository->getTechnicianPerformance($startDate, $endDate);

        // Executive Summary Generation
        $summary = sprintf(
            "Ticket volume has %s by %d%% (%d new tickets) this month compared to last month. The resolution rate is %d%% (%d solved vs %d new). The backlog has %s by %d tickets.",
            $volumeComparison >= 0 ? 'increased' : 'decreased',
            abs($volumeComparison),
            $volume['new'],
            $volume['new'] > 0 ? round(($volume['resolved'] / $volume['new']) * 100) : 0,
            $volume['resolved'],
            $volume['new'],
            $volume['backlog_growth'] > 0 ? 'grown' : 'shrunk',
            abs($volume['backlog_growth'])
        );


        $html = $this->renderView('report/monthly_pdf.html.twig', [
            'period' => $startDate->format('M d, Y') . ' - ' . $endDate->format('M d, Y'),
            'summary' => $summary,
            'volume' => $volume,
            'prev_volume' => $prevVolume,
            'volume_comparison' => $volumeComparison,
            'efficiency' => $efficiency,
            'categories' => $categories,
            'priorities' => $priorities,
            'team' => $team
        ]);

        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $pdfOptions->set('isRemoteEnabled', true); 

        $dompdf = new Dompdf($pdfOptions);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="monthly_report_' . date('Y_m') . '.pdf"',
        ]);
    }
}
