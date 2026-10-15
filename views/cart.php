<?php $title = 'Seu carrinho — Felito'; $byId = []; foreach ($products as $product) $byId[(int) $product['id']] = $product; $total = 0.0; ?>
<section class="content-layout">
  <div class="page-heading"><div><div class="eyebrow"><span class="dot"></span>Quase na hora de saborear</div><h1>Seu carrinho</h1><p>Revise os sabores e as quantidades do seu pedido.</p></div><a class="text-link" href="/">← Continuar escolhendo</a></div>
  <?php if (!$byId): ?>
    <div class="panel empty-state"><div class="empty-mark" aria-hidden="true">F</div><h2>Seu carrinho ainda está vazio.</h2><p>Escolha um sabor artesanal e ele aparece aqui.</p><a class="button button-primary" href="/">Ver sabores</a></div>
  <?php else: ?>
    <div class="cart-list">
      <?php foreach ($byId as $id => $product): $quantity = (int) ($cart[$id] ?? 0); $subtotal = (float) $product['price'] * $quantity; $total += $subtotal; ?>
        <article class="cart-row panel">
          <div class="cart-product"><span class="mini-art art-<?= $id % 3 ?>" aria-hidden="true"></span><div><h2><?= e($product['name']) ?></h2><p><?= e($product['size_label']) ?> · <?= money($product['price']) ?> cada</p><?php if (!$product['is_active']): ?><span class="availability warning">Removido do catálogo</span><?php elseif ($quantity > (int) $product['stock_qty']): ?><span class="availability warning">Disponíveis agora: <?= (int) $product['stock_qty'] ?></span><?php endif ?></div></div>
          <form method="post" action="/carrinho" class="quantity-form">
            <?= csrfField() ?><input type="hidden" name="action" value="update">
            <label for="qty-<?= $id ?>">Quantidade</label><input id="qty-<?= $id ?>" name="items[<?= $id ?>]" type="number" inputmode="numeric" min="0" max="99" value="<?= $quantity ?>" aria-describedby="hint-<?= $id ?>"><span id="hint-<?= $id ?>" class="field-help">Digite 0 para remover</span>
            <button class="button button-outline button-small" type="submit">Atualizar</button>
          </form>
          <div class="cart-subtotal"><span>Subtotal</span><strong><?= money($subtotal) ?></strong><form method="post" action="/carrinho"><?= csrfField() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="product_id" value="<?= $id ?>"><button class="text-button danger-text" type="submit">Remover</button></form></div>
        </article>
      <?php endforeach ?>
    </div>
    <section class="panel summary-panel"><div><span class="eyebrow">Total do pedido</span><p class="summary-total"><?= money($total) ?></p><p class="muted-copy">O estoque é confirmado novamente ao concluir a encomenda.</p></div><a class="button button-primary" href="/checkout">Continuar para retirada <span aria-hidden="true">→</span></a></section>
  <?php endif ?>
</section>
