<?php

namespace App\Twig\Components;

use App\Entity\Invoice;
use App\Entity\Product;
use App\Repository\ClientRepository;
use App\Repository\InvoiceRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;

#[AsLiveComponent]
final class InvoiceForm
{
    use DefaultActionTrait;

    #[LiveProp]
    public Invoice $invoice;

    #[LiveProp(writable: true)]
    public int $selectedProductId = 0;

    #[LiveProp(writable: true)]
    public string $manualName = '';

    #[LiveProp(writable: true)]
    public float $manualQuantity = 1;

    #[LiveProp(writable: true)]
    public float $manualUnitPrice = 0;

    #[LiveProp(writable: true)]
    public float $quantity = 1;

    #[LiveProp(writable: true)]
    public float $unitPrice = 0;

    #[LiveProp(writable: true)]
    public array $lines = [];

    #[LiveProp(writable: true)]
    public int $selectedClientId = 0;

    #[LiveProp(writable: true)]
    public string $invoiceDate = '';

    #[LiveProp(writable: true)]
    public string $error = '';

    public function __construct(
        private ProductRepository $productRepository,
        private ClientRepository $clientRepository,
        private InvoiceRepository $invoiceRepository,
        private EntityManagerInterface $em,
        private Security $security,
        private RouterInterface $router,
    ) {}

    public function mount(Invoice $invoice): void
    {
        $this->invoice = $invoice;
        $this->invoiceDate = $invoice->getCreatedAt()
            ? $invoice->getCreatedAt()->format('Y-m-d')
            : (new \DateTimeImmutable())->format('Y-m-d');

        if ($invoice->getClient()) {
            $this->selectedClientId = $invoice->getClient()->getId();
        }

        foreach ($invoice->getProducts() as $product) {
            $this->lines[] = [
                'productId' => $product->getId(),
                'name' => $product->getName(),
                'quantity' => $product->getQuantity(),
                'unitPrice' => $product->getPrice(),
                'total' => $product->getQuantity() * $product->getPrice(),
            ];
        }
    }

    public function getAvailableProducts(): array
    {
        return $this->productRepository->findBy(['invoice' => null]);
    }

    public function getAvailableClients(): array
    {
        return $this->clientRepository->findBy(['user' => $this->security->getUser()]);
    }

    public function getTotal(): float
    {
        return array_reduce($this->lines, fn($carry, $line) => $carry + $line['total'], 0);
    }

    #[LiveAction]
    public function addLine(): void
    {
        if ($this->selectedProductId) {
            $product = $this->productRepository->find($this->selectedProductId);
            if (!$product || !$this->quantity || !$this->unitPrice) return;

            $this->lines[] = [
                'productId' => $product->getId(),
                'name' => $product->getName(),
                'quantity' => $this->quantity,
                'unitPrice' => $this->unitPrice,
                'total' => $this->quantity * $this->unitPrice,
            ];

            $this->selectedProductId = 0;
            $this->quantity = 1;
            $this->unitPrice = 0;

        } elseif ($this->manualName !== '') {
            if (!$this->manualQuantity || !$this->manualUnitPrice) return;

            $this->lines[] = [
                'productId' => 0,
                'name' => $this->manualName,
                'quantity' => $this->manualQuantity,
                'unitPrice' => $this->manualUnitPrice,
                'total' => $this->manualQuantity * $this->manualUnitPrice,
            ];

            $this->manualName = '';
            $this->manualQuantity = 1;
            $this->manualUnitPrice = 0;
        }
    }

    #[LiveAction]
    public function removeLine(#[LiveArg] int $index): void
    {
        array_splice($this->lines, $index, 1);
    }

    #[LiveAction]
    public function save(#[LiveArg] string $action = 'draft'): RedirectResponse|null
    {
        $this->error = '';

        if (!$this->selectedClientId) {
            $this->error = 'Veuillez sélectionner un client.';
            return null;
        }

        $client = $this->clientRepository->find($this->selectedClientId);

        if (!$client || $client->getUser() !== $this->security->getUser()) {
            $this->error = 'Client invalide.';
            return null;
        }

        $isNew = !$this->invoice->getId();

        if ($isNew) {
            $now = new \DateTimeImmutable($this->invoiceDate);
            $count = $this->invoiceRepository->countByMonth((int)$now->format('Y'), (int)$now->format('m'));
            $this->invoice->setNumber(sprintf('FACT-%s%s%s-%d', $now->format('Y'), $now->format('m'), $now->format('d'), $count + 1));
            $this->invoice->setStatus('draft');
            $this->invoice->setCreatedAt($now);
            $this->invoice->setTotalTtc(0);
            $this->invoice->setUser($this->security->getUser());
            $this->invoice->setClient($client);
            $this->em->persist($this->invoice);
            $this->em->flush();
        } else {
            $this->invoice->setClient($client);
            $this->invoice->setCreatedAt(new \DateTimeImmutable($this->invoiceDate));
        }

        foreach ($this->invoice->getProducts() as $product) {
            $this->em->remove($product);
        }
        $this->em->flush();

        $total = 0;
        foreach ($this->lines as $lineData) {
            $product = new Product();
            $product->setName($lineData['name']);
            $product->setDescription('');
            $product->setPrice($lineData['unitPrice']);
            $product->setQuantity((int)$lineData['quantity']);
            $product->setUnit('piece');
            $product->setInvoice($this->invoice);
            $this->em->persist($product);
            $total += $lineData['total'];
        }

        if ($action === 'validate') {
            $this->invoice->setStatus('pending_payment');
        }

        $this->invoice->setTotalTtc($total);
        $this->em->flush();

        return new RedirectResponse($this->router->generate('app_invoice_show', ['id' => $this->invoice->getId()]));
    }
}