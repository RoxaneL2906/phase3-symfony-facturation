<?php

namespace App\Controller;

use App\Repository\ClientRepository;
use App\Repository\InvoiceRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(
        InvoiceRepository $invoiceRepository,
        ClientRepository $clientRepository,
        ProductRepository $productRepository,
    ): Response {
        $user = $this->getUser();

        $totalCa = array_reduce(
            $invoiceRepository->findBy(['user' => $user, 'status' => 'paid']),
            fn($carry, $invoice) => $carry + $invoice->getTotalTtc(),
            0
        );

        $pendingInvoices = count($invoiceRepository->findBy(['user' => $user, 'status' => 'pending_payment']));
        $totalClients = count($clientRepository->findBy(['user' => $user]));
        $totalProducts = count($productRepository->findBy(['invoice' => null]));

        return $this->render('dashboard/index.html.twig', [
            'totalCa' => $totalCa,
            'pendingInvoices' => $pendingInvoices,
            'totalClients' => $totalClients,
            'totalProducts' => $totalProducts,
        ]);
    }
}