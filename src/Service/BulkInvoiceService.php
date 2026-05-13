<?php

namespace App\Service;

use App\Repository\InvoiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class BulkInvoiceService
{
    public function __construct(
        private EntityManagerInterface $em,
        private InvoiceRepository $invoiceRepository,
        private Security $security,
    ) {}

    public function bulkPay(array $ids): int
    {
        $user = $this->security->getUser();
        $count = 0;

        foreach ($ids as $id) {
            $invoice = $this->invoiceRepository->find($id);
            if (!$invoice || $invoice->getUser() !== $user) {
                continue;
            }
            if ($invoice->getStatus() === 'pending_payment') {
                $invoice->setStatus('paid');
                $count++;
            }
        }

        $this->em->flush();
        return $count;
    }

    public function bulkDelete(array $ids): int
    {
        $user = $this->security->getUser();
        $count = 0;

        foreach ($ids as $id) {
            $invoice = $this->invoiceRepository->find($id);
            if (!$invoice || $invoice->getUser() !== $user) {
                continue;
            }
            if ($invoice->getStatus() === 'draft') {
                $this->em->remove($invoice);
                $count++;
            }
        }

        $this->em->flush();
        return $count;
    }

    public function bulkPdfInvoice(array $ids): ?\App\Entity\Invoice
    {
        $user = $this->security->getUser();

        foreach ($ids as $id) {
            $invoice = $this->invoiceRepository->find($id);
            if (!$invoice || $invoice->getUser() !== $user) {
                continue;
            }
            if ($invoice->getStatus() !== 'draft') {
                return $invoice;
            }
        }

        return null;
    }
}