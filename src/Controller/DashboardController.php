<?php

namespace App\Controller;

use App\Repository\ClientRepository;
use App\Repository\InvoiceRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(
        InvoiceRepository $invoiceRepository,
        ClientRepository $clientRepository,
        ProductRepository $productRepository,
        ChartBuilderInterface $chartBuilder,
        Request $request,
    ): Response {
        $user = $this->getUser();
        $currentYear = (int) date('Y');
        $year = (int) ($request->query->get('year', $currentYear));

        $totalCa = array_reduce(
            $invoiceRepository->findBy(['user' => $user, 'status' => 'paid']),
            fn($carry, $invoice) => $carry + $invoice->getTotalTtc(),
            0
        );

        $pendingInvoices = count($invoiceRepository->findBy(['user' => $user, 'status' => 'pending_payment']));
        $totalClients = count($clientRepository->findBy(['user' => $user]));
        $totalProducts = count($productRepository->findBy(['invoice' => null]));

        $monthlyData = array_fill(1, 12, 0);
        $paidInvoices = $invoiceRepository->findBy(['user' => $user, 'status' => 'paid']);

        foreach ($paidInvoices as $invoice) {
            $invoiceYear = (int) $invoice->getCreatedAt()->format('Y');
            if ($invoiceYear === $year) {
                $month = (int) $invoice->getCreatedAt()->format('n');
                $monthlyData[$month] += $invoice->getTotalTtc();
            }
        }

        $chart = $chartBuilder->createChart(Chart::TYPE_BAR);
        $chart->setData([
            'labels' => ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'],
            'datasets' => [
                [
                    'label' => 'Chiffre d\'affaires (€)',
                    'backgroundColor' => 'rgba(37, 99, 235, 0.7)',
                    'borderColor' => 'rgb(37, 99, 235)',
                    'borderWidth' => 1,
                    'data' => array_values($monthlyData),
                ],
            ],
        ]);

        $chart->setOptions([
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
        ]);

        $years = range($currentYear, $currentYear - 4);

        return $this->render('dashboard/index.html.twig', [
            'totalCa' => $totalCa,
            'pendingInvoices' => $pendingInvoices,
            'totalClients' => $totalClients,
            'totalProducts' => $totalProducts,
            'chart' => $chart,
            'year' => $year,
            'years' => $years,
        ]);
    }
}