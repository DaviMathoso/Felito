<?php
namespace App\Services;

// Tudo que fala com o Mercado Pago fica aqui (RF16-RF18).
class PaymentService
{
    // RF16: cria a cobrança Pix e devolve o QR Code.
    // Obs.: o Mercado Pago exige no mínimo 30 min de validade para Pix.
    public function createPix(array $order): array { throw new \LogicException('TODO'); }

    // RF17: o webhook avisa que pagou -> consultar o pagamento na API e chamar OrderService::markPaid.
    public function handleWebhook(array $payload): void { throw new \LogicException('TODO'); }
}
