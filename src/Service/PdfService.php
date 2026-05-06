<?php

namespace App\Service;

use App\Entity\Invoice;
use Sensiolabs\GotenbergBundle\GotenbergPdfInterface;
use Symfony\Component\HttpFoundation\Response;

class PdfService
{
    public function __construct(
        private GotenbergPdfInterface $gotenberg,
    ) {}

    public function generateInvoicePdf(Invoice $invoice): Response
    {
        return $this->gotenberg
            ->html()
            ->content('invoice/pdf.html.twig', ['invoice' => $invoice])
            ->generate()
            ->stream();
    }
}