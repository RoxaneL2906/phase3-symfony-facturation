<?php

namespace App\Service;

use App\Entity\Invoice;
use App\Entity\Product;
use App\Repository\InvoiceRepository;
use Doctrine\ORM\EntityManagerInterface;

class InvoiceService
{
    public function __construct(
        private EntityManagerInterface $em,
        private InvoiceRepository $invoiceRepository,
    ) {}

    public function generateInvoiceNumber(): string
    {
        $now = new \DateTimeImmutable();
        $year = $now->format('Y');
        $month = $now->format('m');
        $day = $now->format('d');

        $count = $this->invoiceRepository->countByMonth((int)$year, (int)$month);

        return sprintf('FACT-%s%s%s-%d', $year, $month, $day, $count + 1);
    }

    public function processLines(Invoice $invoice, array $linesData): float
    {
        foreach ($invoice->getProducts() as $product) {
            $this->em->remove($product);
        }
        $this->em->flush();

        $total = 0;
        foreach ($linesData as $lineData) {
            $product = new Product();
            $product->setName($lineData['name']);
            $product->setDescription('');
            $product->setPrice($lineData['unitPrice']);
            $product->setQuantity((int)$lineData['quantity']);
            $product->setUnit('piece');
            $product->setInvoice($invoice);
            $this->em->persist($product);
            $total += $lineData['total'];
        }

        return $total;
    }
}