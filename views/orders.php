<?php $title = 'Meus pedidos — Felito'; ?>
<section class="content-layout">
  <div class="page-heading"><div><div class="eyebrow"><span class="dot"></span>Suas encomendas</div><h1>Meus pedidos</h1><p>Acompanhe a preparação e a data solicitada de retirada.</p></div><a class="button button-outline" href="/">Ver sabores</a></div>
  <?php if ($newOrderId): ?><div class="notice notice-success order-confirmation" role="status"><strong>Encomenda #<?= (int) $newOrderId ?> recebida.</strong> A equipe vai confirmar a retirada pelo telefone cadastrado.</div><?php endif ?>
  <?php if (!$orders): ?>
    <div class="panel empty-state"><h2>Aqui aparecerão suas encomendas.</h2><p>Monte seu carrinho e escolha a data para começar.</p><a class="button button-primary" href="/">Escolher um sabor</a></div>
  <?php else: ?>
    <div class="order-grid">
      <?php foreach ($orders as $order): ?>
        <article class="panel order-card">
          <div class="order-card-top"><div><span class="eyebrow">Encomenda #<?= (int) $order['id'] ?></span><h2><?= e(date('d/m/Y', strtotime($order['pickup_date']))) ?></h2><span class="muted-copy">Data de retirada solicitada</span><?php if (in_array($order['status'], ['received', 'preparing', 'ready'], true)): ?><small class="reservation-note">Reserva válida até <?= e(localDateTime($order['reserved_until'])) ?></small><?php endif ?></div><span class="status status-<?= e($order['status']) ?>"><?= e(statusLabel($order['status'])) ?></span></div>
          <div class="order-lines">
            <?php foreach ($order['items'] as $item): ?><div class="order-line"><span><?= (int) $item['quantity'] ?> × <?= e($item['product_name']) ?> <small><?= e($item['size_label']) ?></small></span><strong><?= money((float) $item['unit_price'] * (int) $item['quantity']) ?></strong></div><?php endforeach ?>
          </div>
          <?php if ($order['note']): ?><p class="order-note"><span class="eyebrow">Observação</span><br><?= nl2br(e($order['note'])) ?></p><?php endif ?>
          <div class="order-total"><span>Total</span><strong><?= money($order['total']) ?></strong></div>
          <?php if ($order['status'] === 'expired'): ?><p class="order-help">A reserva expirou após cinco dias e as unidades voltaram ao estoque. Faça um novo pedido para reservar novamente.</p><?php elseif ($order['status'] !== 'cancelled' && $order['status'] !== 'delivered'): ?><p class="order-help">A equipe confirma a retirada pelo telefone cadastrado. O pagamento é combinado diretamente.</p><?php endif ?>
        </article>
      <?php endforeach ?>
    </div>
  <?php endif ?>
</section>
