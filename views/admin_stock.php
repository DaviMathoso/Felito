<?php $title = 'Estoque — gestão Felito'; $movementLabels = ['initial' => 'Estoque inicial', 'entry' => 'Entrada', 'adjustment' => 'Ajuste', 'sale' => 'Venda', 'return' => 'Devolução']; ?>
<section class="content-layout manager-layout">
  <div class="manager-heading"><div><div class="eyebrow"><span class="dot"></span>Painel do gestor</div><h1>Estoque</h1><p>Registre entradas e ajustes para acompanhar cada lote.</p></div><nav class="manager-nav" aria-label="Menu de gestão"><a href="/gestor">Pedidos</a><a href="/gestor/produtos">Produtos</a><a class="active" href="/gestor/estoque">Estoque</a><a href="/gestor/controle">Controle de vendas</a></nav></div>
  <?php if ($lowStock): ?><section class="stock-alert" role="status"><div><span class="eyebrow">Atenção ao estoque</span><p><?= count($lowStock) ?> <?= count($lowStock) === 1 ? 'sabor precisa' : 'sabores precisam' ?> de reposição.</p></div><div class="alert-chips"><?php foreach ($lowStock as $item): ?><span class="tag"><?= e($item['name']) ?> · <?= (int) $item['stock_qty'] ?> un.</span><?php endforeach ?></div></section><?php endif ?>
  <div class="manager-grid stock-grid">
    <section class="panel manager-form-panel"><div class="eyebrow"><span class="dot"></span>Movimentar unidades</div><h2>Atualizar estoque</h2><p class="muted-copy">Cada entrada ou ajuste fica registrado para consulta.</p>
      <form method="post" action="/gestor/estoque" class="form-stack">
        <?= csrfField() ?>
        <label for="product_id">Sabor</label><select id="product_id" name="product_id" required><option value="">Selecione um produto</option><?php foreach ($products as $product): ?><option value="<?= (int) $product['id'] ?>"><?= e($product['name']) ?> · saldo atual: <?= (int) $product['stock_qty'] ?><?= !$product['is_active'] ? ' · oculto' : '' ?></option><?php endforeach ?></select>
        <label for="mode">Tipo de movimentação</label><select id="mode" name="mode" required><option value="entry">Entrada de unidades</option><option value="adjustment">Ajustar para o novo saldo</option></select>
        <label for="quantity">Quantidade</label><input id="quantity" name="quantity" type="number" min="0" max="999999" required><p class="field-help">Na entrada, informe quantas unidades chegaram. No ajuste, informe o saldo final contado.</p>
        <label for="reason">Motivo</label><input id="reason" name="reason" type="text" maxlength="200" required placeholder="Ex.: produção do lote de hoje">
        <button class="button button-primary button-full" type="submit">Salvar movimentação</button>
      </form>
    </section>
    <section class="panel manager-list-panel"><div class="section-heading compact-heading"><div><div class="eyebrow">Entradas, vendas e ajustes</div><h2>Histórico recente</h2></div></div>
      <?php if (!$movements): ?><div class="empty-state small-empty"><p>As movimentações aparecem aqui depois da primeira atualização.</p></div>
      <?php else: ?><div class="movement-list"><?php foreach ($movements as $movement): ?><article class="movement-row"><span class="movement-icon <?= $movement['quantity_delta'] < 0 ? 'movement-out' : 'movement-in' ?>" aria-hidden="true"><?= $movement['quantity_delta'] < 0 ? '−' : '+' ?></span><div class="movement-copy"><strong><?= e($movement['product_name']) ?> <span class="muted-copy">· <?= e($movementLabels[$movement['movement_type']] ?? $movement['movement_type']) ?></span></strong><p><?= e($movement['reason']) ?><?php if ($movement['order_id']): ?> · pedido #<?= (int) $movement['order_id'] ?><?php endif ?></p><small><?= e(localDateTime($movement['created_at'])) ?><?= $movement['admin_name'] ? ' · ' . e($movement['admin_name']) : '' ?></small></div><strong class="movement-qty"><?= $movement['quantity_delta'] > 0 ? '+' : '' ?><?= (int) $movement['quantity_delta'] ?></strong></article><?php endforeach ?></div><?php endif ?>
    </section>
  </div>
</section>
