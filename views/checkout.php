<?php $title = 'Data de retirada — Felito'; $old = $old ?? []; $byId = []; foreach ($products as $product) $byId[(int) $product['id']] = $product; $total = 0.0; ?>
<section class="content-layout">
  <div class="page-heading"><div><div class="eyebrow"><span class="dot"></span>Último passo</div><h1>Combine sua retirada</h1><p>Informe quando prefere buscar sua encomenda.</p></div><a class="text-link" href="/carrinho">← Voltar ao carrinho</a></div>
  <div class="checkout-grid">
    <section class="panel"><div class="eyebrow"><span class="dot"></span>Confirme os detalhes</div><h2>Quando podemos preparar?</h2><p class="muted-copy">A data que você indicar é uma solicitação. A equipe confirma a disponibilidade pelo telefone cadastrado.</p>
      <form method="post" action="/checkout" class="form-stack">
        <?= csrfField() ?>
        <label for="pickup_date">Data desejada para retirada</label><input id="pickup_date" name="pickup_date" type="date" min="<?= e(date('Y-m-d')) ?>" required value="<?= e($old['pickup_date'] ?? date('Y-m-d')) ?>">
        <label for="note">Observação <span class="optional">(opcional)</span></label><textarea id="note" name="note" rows="4" maxlength="500" placeholder="Algo que a equipe precisa saber?"><?= e($old['note'] ?? '') ?></textarea><p class="field-help">Até 500 caracteres.</p>
        <button class="button button-primary button-full" type="submit">Enviar encomenda</button>
        <p class="field-help centered">O pagamento será combinado com a equipe na confirmação da retirada.</p>
      </form>
    </section>
    <aside class="panel summary-panel summary-aside"><div class="eyebrow">Seu pedido</div><div class="summary-lines">
      <?php foreach ($byId as $id => $product): $qty = (int) ($cart[$id] ?? 0); $subtotal = (float) $product['price'] * $qty; $total += $subtotal; ?>
        <div class="summary-line"><span><?= $qty ?> × <?= e($product['name']) ?></span><strong><?= money($subtotal) ?></strong></div>
      <?php endforeach ?>
    </div><div class="summary-total-row"><span>Total</span><strong><?= money($total) ?></strong></div><p class="muted-copy">A quantidade será confirmada conforme o estoque do lote.</p></aside>
  </div>
</section>
