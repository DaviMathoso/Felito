<?php $title = 'Sabores artesanais — Felito'; ?>
<div class="layout catalog-layout">
  <aside class="sidebar" aria-label="Atalhos">
    <div class="side-label">Sua visita</div>
    <nav class="side-nav">
      <a class="active" href="/" aria-current="page"><span class="dot"></span>Sabores do mês</a>
      <a href="/carrinho"><span class="dot"></span>Seu carrinho</a>
      <a href="/pedidos"><span class="dot"></span>Meus pedidos</a>
    </nav>
    <p class="sidebar-note">Preparado em pequenos lotes para chegar à sua mesa no ponto certo.</p>
  </aside>
  <section class="panel">
    <div class="hero">
      <div class="hero-main">
        <div class="eyebrow"><span class="dot"></span>Formulação artesanal</div>
        <h1>Um bom sorvete pede tempo.</h1>
        <p>Ingredientes selecionados, receitas feitas em pequenos lotes e sabores para deixar o dia mais gostoso. Escolha seu favorito e encomende para retirar.</p>
      </div>
      <div class="hero-meta"><span>Produção artesanal</span><span>Encomendas para retirada</span><span>Feito com carinho, lote a lote</span></div>
    </div>
    <div class="section-heading"><div><div class="eyebrow"><span class="dot"></span>Da nossa cozinha</div><h2>Sabores do mês</h2></div><span class="result-count"><?= count($products) ?> opções</span></div>
    <?php if (!$products): ?>
      <div class="empty-state"><h2>Estamos preparando novos sabores.</h2><p>Volte em breve para conhecer o próximo lote.</p></div>
    <?php else: ?>
      <div class="product-grid">
        <?php foreach ($products as $index => $product): ?>
          <article class="product-card">
            <div class="product-art art-<?= $index % 3 ?>" aria-hidden="true"><span class="art-orbit"></span><span class="art-label">FELITO · LOTE ARTESANAL</span>
              <?php if ($product['is_featured']): ?><span class="badge">Destaque do mês</span><?php endif ?>
            </div>
            <div class="product-copy">
              <div class="product-heading"><h3><?= e($product['name']) ?></h3><span class="price"><?= money($product['price']) ?></span></div>
              <p><?= e($product['description']) ?></p>
              <div class="product-meta"><span><?= e($product['size_label']) ?></span><span>·</span><span><?= (int) $product['stock_qty'] ?> <?= (int) $product['stock_qty'] === 1 ? 'unidade disponível' : 'unidades disponíveis' ?></span></div>
              <form method="post" action="/carrinho" class="product-action">
                <?= csrfField() ?><input type="hidden" name="action" value="add"><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                <?php if ((int) $product['stock_qty'] > 0): ?><button class="button button-primary" type="submit">Adicionar ao carrinho <span aria-hidden="true">+</span></button>
                <?php else: ?><button class="button button-disabled" type="button" disabled aria-disabled="true">Indisponível neste lote</button><?php endif ?>
              </form>
            </div>
          </article>
        <?php endforeach ?>
      </div>
    <?php endif ?>
    <div class="principles">
      <div class="principle"><div class="eyebrow">01 / Pequenos lotes</div><p>Produzimos em quantidades cuidadosas para manter a qualidade de cada receita.</p></div>
      <div class="principle"><div class="eyebrow">02 / Ingredientes de verdade</div><p>Sabor simples e marcante, sem deixar a matéria-prima de lado.</p></div>
      <div class="principle"><div class="eyebrow">03 / Retirada combinada</div><p>Informe o melhor dia. A equipe confirma a data com você pelo telefone.</p></div>
    </div>
  </section>
</div>
