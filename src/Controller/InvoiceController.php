<?php

namespace App\Controller;

use App\Entity\Invoice;
use App\Repository\ClientRepository;
use App\Repository\InvoiceRepository;
use App\Service\BulkInvoiceService;
use App\Service\InvoiceService;
use App\Service\MailService;
use App\Service\PdfService;
use Doctrine\ORM\EntityManagerInterface;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/invoice')]
final class InvoiceController extends AbstractController
{
    public function __construct(
        private InvoiceService $invoiceService,
        private BulkInvoiceService $bulkInvoiceService,
    ) {}

    #[Route(name: 'app_invoice_index', methods: ['GET'])]
    public function index(InvoiceRepository $invoiceRepository, ClientRepository $clientRepository, Request $request): Response
    {
        $status = $request->query->get('status');
        $user = $this->getUser();
        $page = $request->query->getInt('page', 1);

        $filters = [
            'client' => $request->query->get('client'),
            'month' => $request->query->get('month'),
            'year' => $request->query->get('year'),
            'date_from' => $request->query->get('date_from'),
            'date_to' => $request->query->get('date_to'),
        ];

        $qb = $invoiceRepository->createUserQueryBuilder($user, $status, $filters);

        $pagerfanta = new Pagerfanta(new QueryAdapter($qb));
        $pagerfanta->setMaxPerPage(10);
        $pagerfanta->setCurrentPage($page);

        $clients = $clientRepository->findBy(['user' => $user], ['name' => 'ASC']);

        return $this->render('invoice/index.html.twig', [
            'invoices' => $pagerfanta,
            'currentStatus' => $status,
            'filters' => $filters,
            'clients' => $clients,
        ]);
    }

    #[Route('/bulk/pdf', name: 'app_invoice_bulk_pdf', methods: ['POST'])]
    public function bulkPdf(Request $request, PdfService $pdfService): Response
    {
        $ids = $request->request->all('ids');
        $status = $request->request->get('status');

        $invoice = $this->bulkInvoiceService->bulkPdfInvoice($ids);

        if ($invoice) {
            return $pdfService->generateInvoicePdf($invoice);
        }

        $this->addFlash('error', 'Aucune facture valide sélectionnée pour le téléchargement PDF.');
        return $this->redirectToRoute('app_invoice_index', $status ? ['status' => $status] : []);
    }

    #[Route('/bulk/pay', name: 'app_invoice_bulk_pay', methods: ['POST'])]
    public function bulkPay(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('bulk_pay', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $ids = $request->request->all('ids');
        $status = $request->request->get('status');

        $count = $this->bulkInvoiceService->bulkPay($ids);
        $this->addFlash('success', $count . ' facture(s) marquée(s) comme payée(s) !');

        return $this->redirectToRoute('app_invoice_index', $status ? ['status' => $status] : []);
    }

    #[Route('/bulk/delete', name: 'app_invoice_bulk_delete', methods: ['POST'])]
    public function bulkDelete(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('bulk_delete', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $ids = $request->request->all('ids');
        $status = $request->request->get('status');

        $count = $this->bulkInvoiceService->bulkDelete($ids);
        $this->addFlash('success', $count . ' facture(s) supprimée(s) !');

        return $this->redirectToRoute('app_invoice_index', $status ? ['status' => $status] : []);
    }

    #[Route('/new', name: 'app_invoice_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, InvoiceRepository $invoiceRepository): Response
    {
        $invoice = new Invoice();
        $invoice->setUser($this->getUser());
        $invoice->setStatus('draft');
        $invoice->setCreatedAt(new \DateTimeImmutable());
        $invoice->setTotalTtc(0);

        if ($request->isMethod('POST')) {
            $linesData = json_decode($request->request->get('invoice_lines', '[]'), true);
            $total = $this->invoiceService->processLines($invoice, $linesData);

            $invoice->setNumber($this->invoiceService->generateInvoiceNumber());
            $invoice->setTotalTtc($total);

            if ($request->request->get('action') === 'validate') {
                $invoice->setStatus('pending_payment');
            }

            $entityManager->persist($invoice);
            $entityManager->flush();

            return $this->redirectToRoute('app_invoice_show', ['id' => $invoice->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('invoice/new.html.twig', [
            'invoice' => $invoice,
        ]);
    }

    #[Route('/{id}', name: 'app_invoice_show', methods: ['GET'])]
    public function show(Invoice $invoice): Response
    {
        if ($invoice->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('invoice/show.html.twig', [
            'invoice' => $invoice,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_invoice_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Invoice $invoice, EntityManagerInterface $entityManager): Response
    {
        if ($invoice->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($invoice->getStatus() !== 'draft') {
            $this->addFlash('error', 'Seule une facture en brouillon peut être modifiée.');
            return $this->redirectToRoute('app_invoice_index');
        }

        if ($request->isMethod('POST')) {
            $linesData = json_decode($request->request->get('invoice_lines', '[]'), true);
            $total = $this->invoiceService->processLines($invoice, $linesData);

            $invoice->setTotalTtc($total);

            if ($request->request->get('action') === 'validate') {
                $invoice->setStatus('pending_payment');
            }

            $entityManager->flush();

            return $this->redirectToRoute('app_invoice_show', ['id' => $invoice->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('invoice/edit.html.twig', [
            'invoice' => $invoice,
        ]);
    }

    #[Route('/{id}/validate', name: 'app_invoice_validate', methods: ['POST'])]
    public function validate(Invoice $invoice, EntityManagerInterface $entityManager): Response
    {
        if ($invoice->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($invoice->getStatus() === 'draft') {
            $invoice->setStatus('pending_payment');
            $entityManager->flush();
            $this->addFlash('success', 'Facture validée avec succès !');
        }

        return $this->redirectToRoute('app_invoice_show', ['id' => $invoice->getId()]);
    }

    #[Route('/{id}/pay', name: 'app_invoice_pay', methods: ['POST'])]
    public function pay(Invoice $invoice, EntityManagerInterface $entityManager): Response
    {
        if ($invoice->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($invoice->getStatus() === 'pending_payment') {
            $invoice->setStatus('paid');
            $entityManager->flush();
            $this->addFlash('success', 'Facture marquée comme payée !');
        }

        return $this->redirectToRoute('app_invoice_show', ['id' => $invoice->getId()]);
    }

    #[Route('/{id}/pdf', name: 'app_invoice_pdf', methods: ['GET'])]
    public function pdf(Invoice $invoice, PdfService $pdfService): Response
    {
        if ($invoice->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($invoice->getStatus() === 'draft') {
            $this->addFlash('error', 'Seule une facture validée peut être téléchargée en PDF.');
            return $this->redirectToRoute('app_invoice_show', ['id' => $invoice->getId()]);
        }

        return $pdfService->generateInvoicePdf($invoice);
    }

    #[Route('/{id}/send', name: 'app_invoice_send', methods: ['POST'])]
    public function send(Invoice $invoice, MailService $mailService): Response
    {
        if ($invoice->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($invoice->getStatus() === 'draft') {
            $this->addFlash('error', 'Seule une facture validée peut être envoyée par mail.');
            return $this->redirectToRoute('app_invoice_show', ['id' => $invoice->getId()]);
        }

        $mailService->sendInvoice($invoice);
        $this->addFlash('success', 'Facture envoyée par mail à ' . $invoice->getClient()->getEmail());

        return $this->redirectToRoute('app_invoice_show', ['id' => $invoice->getId()]);
    }

    #[Route('/{id}/remind', name: 'app_invoice_remind', methods: ['GET', 'POST'])]
    public function remind(Request $request, Invoice $invoice, MailService $mailService): Response
    {
        if ($invoice->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($request->isMethod('POST')) {
            $message = $request->request->get('message', '');
            $mailService->sendReminder($invoice, $message);
            $this->addFlash('success', 'Mail de relance envoyé à ' . $invoice->getClient()->getEmail());
            return $this->redirectToRoute('app_invoice_show', ['id' => $invoice->getId()]);
        }

        return $this->render('invoice/remind.html.twig', [
            'invoice' => $invoice,
            'defaultMessage' => 'Bonjour,

Je me permets de vous relancer concernant la facture ' . $invoice->getNumber() . ' qui est toujours en attente de paiement.

Merci de bien vouloir procéder au règlement.

Coordonnées bancaires : ' . $invoice->getUser()->getIban(),
        ]);
    }

    #[Route('/{id}', name: 'app_invoice_delete', methods: ['POST'])]
    public function delete(Request $request, Invoice $invoice, EntityManagerInterface $entityManager): Response
    {
        if ($invoice->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($invoice->getStatus() !== 'draft') {
            $this->addFlash('error', 'Seule une facture en brouillon peut être supprimée.');
            return $this->redirectToRoute('app_invoice_index');
        }

        if ($this->isCsrfTokenValid('delete'.$invoice->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($invoice);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_invoice_index', [], Response::HTTP_SEE_OTHER);
    }
}