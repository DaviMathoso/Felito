<?php
namespace App\Services;

// REGRAS do pedido (a "cozinha"). Aqui NÃO entra HTML nem $_POST.
class OrderService
{
    // RF09: reserva com prazo. Desconta o estoque e guarda "reserved_until".
    public function reserve(int $userId, array $cart): int { throw new \LogicException('TODO'); }

    // RF10: reserva venceu ou foi cancelada -> devolve os itens ao estoque.
    public function release(int $orderId): void { throw new \LogicException('TODO'); }

    // RF12: pagamento aprovado -> confirma pedido, dá baixa no cupom, soma pontos.
    public function markPaid(int $orderId): void { throw new \LogicException('TODO'); }
}
