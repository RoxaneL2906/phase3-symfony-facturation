<?php

namespace App\Twig\Components;

use App\Entity\Invoice;
use App\Entity\Product;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class InvoiceForm
{
    use DefaultActionTrait;

    #[LiveProp]
    public Invoice $invoice;

    #[LiveProp(writable: true)]
    public int $selectedProductId = 0;

    #[LiveProp(writable: true)]
    public float $quantity = 1;

    #[LiveProp(writable: true)]
    public float $unitPrice = 0;

    #[LiveProp]
    public array $lines = [];

    public function __construct(
        private ProductRepository $productRepository,
        private EntityManagerInterface $em,
        private Security $security,
    ) {}

    public function getAvailableProducts(): array
    {
        return $this->productRepository->findBy(['invoice' => null]);
    }

    public function getTotal(): float
    {
        return array_reduce($this->lines, fn($carry, $line) => $carry + $line['total'], 0);
    }

    #[LiveAction]
    public function addLine(): void
    {
        if (!$this->selectedProductId || !$this->quantity || !$this->unitPrice) {
            return;
        }

        $product = $this->productRepository->find($this->selectedProductId);
        if (!$product) return;

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
    }

    #[LiveAction]
    public function removeLine(int $index): void
    {
        array_splice($this->lines, $index, 1);
    }
}