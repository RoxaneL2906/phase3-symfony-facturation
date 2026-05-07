<?php

namespace App\Service;

use App\Entity\Invoice;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class MailService
{
    public function __construct(
        private MailerInterface $mailer,
        private PdfService $pdfService,
    ) {}

    public function sendInvoice(Invoice $invoice): void
    {
        $pdfContent = $this->pdfService->generateInvoicePdfContent($invoice);

        $email = (new Email())
            ->from('noreply@factursaas.fr')
            ->to($invoice->getClient()->getEmail())
            ->subject('Votre facture ' . $invoice->getNumber())
            ->text('Veuillez trouver ci-joint votre facture ' . $invoice->getNumber() . '.')
            ->attach($pdfContent, $invoice->getNumber() . '.pdf', 'application/pdf');

        $this->mailer->send($email);
    }

    public function sendReminder(Invoice $invoice, string $message): void
    {
        $pdfContent = $this->pdfService->generateInvoicePdfContent($invoice);

        $email = (new Email())
            ->from('noreply@factursaas.fr')
            ->to($invoice->getClient()->getEmail())
            ->subject('Relance - Facture ' . $invoice->getNumber())
            ->text($message)
            ->attach($pdfContent, $invoice->getNumber() . '.pdf', 'application/pdf');

        $this->mailer->send($email);
    }
}