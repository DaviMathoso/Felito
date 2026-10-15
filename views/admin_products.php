<?php $title = 'Produtos — gestão Felito'; ?>
<section class="content-layout manager-layout">
  <div class="manager-heading"><div><div class="eyebrow"><span class="dot"></span>Painel do gestor</div><h1>Produtos</h1><p>Cadastre e atualize os sabores disponíveis em cada lote.</p></div><nav class="manager-nav" aria-label="Menu de gestão"><a href="/gestor">Pedidos</a><a class="active" href="/gestor/produtos">Produtos</a><a href="/gestor/estoque">Estoque</a></nav></div>
  <div class="manager-grid">
    <section class="panel manager-form-panel"><div class="eyebrow"><span class="dot"></span><?= $product ? 'Editar produto' : 'Novo sabor' ?></div><h2><?= $product ? e($product['name']) : 'Adicionar ao catálogo' ?></h2>
      <form method="post" action="/gestor/produtos" class="form-stack">
        <?= csrfField() ?><input type="hidden" name="action" value="save"><?php if ($product): ?><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>"><?php endif ?>
        <label for="name">Nome do sabor</label><input id="name" name="name" type="text" maxlength="120" required value="<?= e($product['name'] ?? '') ?>">
        <label for="description">Descrição</label><textarea id="description" name="description" rows="4" maxlength="2000" required><?= e($product['description'] ?? '') ?></textarea>
        <label for="size_label">Apresentação</label><input id="size_label" name="size_label" type="text" maxlength="80" placeholder="Pote de 500 ml" required value="<?= e($product['size_label'] ?? '') ?>">
        <div class="form-two"><div><label for="price">Preço (R$)</label><input id="price" name="price" type="number" min="0.01" max="999999.99" step="0.01" inputmode="decimal" required value="<?= e($product['price'] ?? '') ?>"></div><div><label for="min_stock">Estoque mínimo</label><input id="min_stock" name="min_stock" type="number" min="0" max="999999" required value="<?= e($product['min_stock'] ?? '4') ?>"></div></div>
        <?php if (!$product): ?><label for="stock_qty">Estoque inicial (unidades)</label><input id="stock_qty" name="stock_qty" type="number" min="0" max="999999" value="0"><p class="field-help">Esse valor é registrado no histórico como estoque inicial.</p><?php endif ?>
        <label class="check-row"><input type="checkbox" name="is_featured" value="1"<?= !empty($product['is_featured']) ? ' checked' : '' ?>><span>Destaque do mês</span></label>
        <label class="check-row"><input type="checkbox" name="is_active" value="1"<?= !$product || !empty($product['is_active']) ? ' checked' : '' ?>><span>Exibir no catálogo</span></label>
        <button class="button button-primary button-full" type="submit"><?= $product ? 'Salvar alterações' : 'Cadastrar sabor' ?></button>
        <?php if ($product): ?><a class="text-link centered" href="/gestor/produtos">Cancelar edição</a><?php endif ?>
      </form>
    </section>
    <section class="panel manager-list-panel"><div class="section-heading compact-heading"><div><div class="eyebrow">Catálogo e disponibilidade</div><h2>Produtos cadastrados</h2></div><span class="result-count"><?= count($products) ?> itens</span></div>
      <div class="admin-product-list">
        <?php foreach ($products as $item): ?><article class="admin-product-row"><span class="mini-art art-<?= (int) $item['id'] % 3 ?>" aria-hidden="true"></span><div class="admin-product-copy"><h3><?= e($item['name']) ?></h3><p><?= e($item['size_label']) ?> · <?= money($item['price']) ?></p><div class="product-meta"><span><?= (int) $item['stock_qty'] ?> disponíveis</span><span>mínimo <?= (int) $item['min_stock'] ?></span><span class="availability <?= (int) $item['stock_qty'] <= (int) $item['min_stock'] ? 'warning' : '' ?>"><?= $item['is_active'] ? 'Ativo' : 'Oculto' ?></span></div></div><div class="admin-product-actions"><a class="button button-outline button-small" href="/gestor/produtos?editar=<?= (int) $item['id'] ?>">Editar</a><?php if ($item['is_active']): ?><form method="post" action="/gestor/produtos" class="inline-form" onsubmit="return confirm('Ocultar este produto do catálogo? O histórico e o estoque serão preservados.')"><?= csrfField() ?><input type="hidden" name="action" value="deactivate"><input type="hidden" name="product_id" value="<?= (int) $item['id'] ?>"><button class="text-button danger-text" type="submit">Ocultar</button></form><?php endif ?></div></article><?php endforeach ?>
      </div>
    </section>
  </div>
</section>
