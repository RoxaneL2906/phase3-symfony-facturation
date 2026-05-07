<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\ClientRepository;
use App\Repository\InvoiceRepository;
use App\Repository\ProductRepository;
use App\Service\MailService;
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

    #[Route('/dashboard/remind-all', name: 'app_dashboard_remind_all', methods: ['POST'])]
    public function remindAll(
        InvoiceRepository $invoiceRepository,
        MailService $mailService,
    ): Response {
        $user = $this->getUser();
        assert($user instanceof User);

        $pendingInvoices = $invoiceRepository->findBy(['user' => $user, 'status' => 'pending_payment']);

        foreach ($pendingInvoices as $invoice) {
            if ($invoice->getClient()) {
                $mailService->sendReminder(
                    $invoice,
                    'Bonjour, je me permets de vous relancer concernant la facture ' . $invoice->getNumber() . ' qui est toujours en attente de paiement. Merci de bien vouloir procéder au règlement.' . $user->getIban()
                );
            }
        }

        $this->addFlash('success', count($pendingInvoices) . ' mail(s) de relance envoyé(s) !');

        return $this->redirectToRoute('app_dashboard');
    }
}