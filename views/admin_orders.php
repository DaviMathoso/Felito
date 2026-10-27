<?php
$title = 'Pedidos — gestão Felito';
$next = ['received' => 'preparing', 'preparing' => 'ready', 'ready' => 'delivered'];
?>
<section class="content-layout manager-layout">
  <div class="manager-heading"><div><div class="eyebrow"><span class="dot"></span>Painel do gestor</div><h1>Pedidos</h1><p>Confira as encomendas e atualize cada etapa da preparação.</p></div><nav class="manager-nav" aria-label="Menu de gestão"><a class="active" href="/gestor">Pedidos</a><a href="/gestor/produtos">Produtos</a><a href="/gestor/estoque">Estoque</a><a href="/gestor/controle">Controle de vendas</a></nav></div>
  <div class="stat-grid">
    <article class="panel stat-card"><span class="eyebrow">Pedidos recebidos</span><strong><?= (int) ($stats['received'] ?? 0) ?></strong><small>Aguardando preparo</small></article>
    <article class="panel stat-card"><span class="eyebrow">Em andamento</span><strong><?= (int) ($stats['active'] ?? 0) ?></strong><small>Do recebimento à retirada</small></article>
    <article class="panel stat-card"><span class="eyebrow">Prontos para retirada</span><strong><?= (int) ($stats['ready'] ?? 0) ?></strong><small>Aguardando o cliente</small></article>
  </div>
  <section class="panel filter-panel"><form method="get" action="/gestor" class="filter-form"><label for="status-filter">Mostrar pedidos</label><select id="status-filter" name="status"><option value="">Todos</option><?php foreach (['received', 'preparing', 'ready', 'delivered', 'cancelled'] as $status): ?><option value="<?= e($status) ?>"<?= $filter === $status ? ' selected' : '' ?>><?= e(statusLabel($status)) ?></option><?php endforeach ?></select><button class="button button-outline button-small" type="submit">Filtrar</button></form><span class="muted-copy"><?= count($orders) ?> pedidos nesta lista</span></section>
  <?php if (!$orders): ?><div class="panel empty-state"><h2>Nenhum pedido neste filtro.</h2><p>As próximas encomendas aparecem aqui.</p></div>
  <?php else: ?><div class="admin-order-list">
    <?php foreach ($orders as $order): ?>
      <article class="panel admin-order-card">
        <div class="admin-order-top"><div><span class="eyebrow">Pedido #<?= (int) $order['id'] ?> · <?= e(localDateTime($order['created_at'])) ?></span><h2><?= e($order['customer_name']) ?></h2><p class="contact-line"><a href="mailto:<?= e($order['customer_email']) ?>"><?= e($order['customer_email']) ?></a> <span>·</span> <a href="tel:<?= e($order['customer_phone']) ?>"><?= e($order['customer_phone']) ?></a></p></div><span class="status status-<?= e($order['status']) ?>"><?= e(statusLabel($order['status'])) ?></span></div>
        <div class="admin-detail-grid"><div><span class="eyebrow">Retirada solicitada</span><strong><?= e(date('d/m/Y', strtotime($order['pickup_date']))) ?></strong></div><div><span class="eyebrow">Total</span><strong><?= money($order['total']) ?></strong></div></div>
        <div class="order-lines"><?php foreach ($order['items'] as $item): ?><div class="order-line"><span><?= (int) $item['quantity'] ?> × <?= e($item['product_name']) ?> <small><?= e($item['size_label']) ?></small></span><strong><?= money((float) $item['unit_price'] * (int) $item['quantity']) ?></strong></div><?php endforeach ?></div>
        <?php if ($order['note']): ?><p class="order-note"><span class="eyebrow">Observação do cliente</span><br><?= nl2br(e($order['note'])) ?></p><?php endif ?>
        <?php if (isset($next[$order['status']])): ?><div class="admin-actions"><form method="post" action="/gestor" class="inline-form"><?= csrfField() ?><input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="status" value="<?= e($next[$order['status']]) ?>"><input type="hidden" name="filter" value="<?= e($filter) ?>"><button class="button button-primary button-small" type="submit"><?= $order['status'] === 'ready' ? 'Marcar como entregue' : 'Avançar: ' . e(statusLabel($next[$order['status']])) ?></button></form>
          <form method="post" action="/gestor" class="inline-form" onsubmit="return confirm('Cancelar este pedido e devolver os itens ao estoque?')"><?= csrfField() ?><input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>"><input type="hidden" name="action" value="cancel"><input type="hidden" name="filter" value="<?= e($filter) ?>"><button class="button button-danger button-small" type="submit">Cancelar pedido</button></form></div><?php endif ?>
      </article>
    <?php endforeach ?>
  </div><?php endif ?>
</section>
