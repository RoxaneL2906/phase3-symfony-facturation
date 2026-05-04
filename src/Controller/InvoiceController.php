<?php

namespace App\Controller;

use App\Entity\Invoice;
use App\Entity\Product;
use App\Form\InvoiceType;
use App\Repository\InvoiceRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/invoice')]
final class InvoiceController extends AbstractController
{
    #[Route(name: 'app_invoice_index', methods: ['GET'])]
    public function index(InvoiceRepository $invoiceRepository, Request $request): Response
    {
        $status = $request->query->get('status');
        $user = $this->getUser();

        if ($status) {
            $invoices = $invoiceRepository->findBy(['user' => $user, 'status' => $status]);
        } else {
            $invoices = $invoiceRepository->findBy(['user' => $user]);
        }

        return $this->render('invoice/index.html.twig', [
            'invoices' => $invoices,
            'currentStatus' => $status,
        ]);
    }

    #[Route('/new', name: 'app_invoice_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, InvoiceRepository $invoiceRepository, ProductRepository $productRepository): Response
    {
        $invoice = new Invoice();
        $invoice->setUser($this->getUser());
        $invoice->setStatus('draft');
        $invoice->setCreatedAt(new \DateTimeImmutable());
        $invoice->setTotalTtc(0);

        $form = $this->createForm(InvoiceType::class, $invoice, ['user' => $this->getUser()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $invoice->setNumber($this->generateInvoiceNumber($invoiceRepository));
            $entityManager->persist($invoice);
            $entityManager->flush();

            return $this->redirectToRoute('app_invoice_show', ['id' => $invoice->getId()], Response::HTTP_SEE_OTHER);
        }

        $userProducts = $productRepository->findBy(['invoice' => null]);

        return $this->render('invoice/new.html.twig', [
            'invoice' => $invoice,
            'form' => $form,
            'userProducts' => $userProducts,
        ]);
    }

    #[Route('/{id}', name: 'app_invoice_show', methods: ['GET'])]
    public function show(Invoice $invoice): Response
    {
        return $this->render('invoice/show.html.twig', [
            'invoice' => $invoice,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_invoice_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Invoice $invoice, EntityManagerInterface $entityManager, ProductRepository $productRepository): Response
    {
        if ($invoice->getStatus() !== 'draft') {
            $this->addFlash('error', 'Seule une facture en brouillon peut être modifiée.');
            return $this->redirectToRoute('app_invoice_index');
        }

        $form = $this->createForm(InvoiceType::class, $invoice, ['user' => $this->getUser()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            return $this->redirectToRoute('app_invoice_show', ['id' => $invoice->getId()], Response::HTTP_SEE_OTHER);
        }

        $userProducts = $productRepository->findBy(['invoice' => null]);

        return $this->render('invoice/edit.html.twig', [
            'invoice' => $invoice,
            'form' => $form,
            'userProducts' => $userProducts,
        ]);
    }

    #[Route('/{id}/validate', name: 'app_invoice_validate', methods: ['POST'])]
    public function validate(Invoice $invoice, EntityManagerInterface $entityManager): Response
    {
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
        if ($invoice->getStatus() === 'pending_payment') {
            $invoice->setStatus('paid');
            $entityManager->flush();
            $this->addFlash('success', 'Facture marquée comme payée !');
        }

        return $this->redirectToRoute('app_invoice_show', ['id' => $invoice->getId()]);
    }

    #[Route('/{id}', name: 'app_invoice_delete', methods: ['POST'])]
    public function delete(Request $request, Invoice $invoice, EntityManagerInterface $entityManager): Response
    {
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

    private function generateInvoiceNumber(InvoiceRepository $invoiceRepository): string
    {
        $now = new \DateTimeImmutable();
        $year = $now->format('Y');
        $month = $now->format('m');

        $count = $invoiceRepository->countByMonth((int)$year, (int)$month);

        return sprintf('FACT-%s%s-%d', $year, $month, $count + 1);
    }
}