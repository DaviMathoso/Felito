<?php
$user = currentUser();
$cartCount = array_sum(array_map('intval', $_SESSION['cart'] ?? []));
$cartUnit = $cartCount === 1 ? 'item' : 'itens';
$flashes = consumeFlashes();
$pageTitle = $title ?? 'Felito — sorvetes artesanais';
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#efe8d7">
  <link rel="icon" href="/favicon.svg" type="image/svg+xml">
  <title><?= e($pageTitle) ?></title>
  <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
  <div class="blob blob-one" aria-hidden="true"></div>
  <div class="blob blob-two" aria-hidden="true"></div>
  <header class="topbar">
    <a class="brand-wrap" href="/" aria-label="Felito, página inicial"><span class="brand">FELITO</span><span class="chip">Sorvetes artesanais</span></a>
    <nav class="topnav" aria-label="Navegação principal">
      <a href="/"<?= ($_SERVER['REQUEST_URI'] ?? '/') === '/' ? ' aria-current="page"' : '' ?>>Sabores</a>
      <a href="/pedidos">Meus pedidos</a>
      <?php if ($user && $user['role'] === 'admin'): ?><a href="/gestor">Gestão</a><?php endif ?>
    </nav>
    <div class="top-actions">
      <a class="button button-outline button-small" href="/carrinho">Carrinho <span class="cart-count" aria-label="<?= (int) $cartCount ?> <?= e($cartUnit) ?>"><?= (int) $cartCount ?></span></a>
      <?php if ($user): ?>
        <span class="user-greeting">Olá, <?= e(explode(' ', trim($user['name']))[0]) ?></span>
        <form method="post" action="/sair" class="inline-form"><?= csrfField() ?><button class="text-button" type="submit">Sair</button></form>
      <?php else: ?>
        <a class="text-link" href="/entrar">Entrar</a>
      <?php endif ?>
    </div>
  </header>
  <main class="site-main">
    <?php foreach ($flashes as $notice): ?>
      <div class="notice notice-<?= e($notice['type']) ?>" role="status"><?= e($notice['message']) ?></div>
    <?php endforeach ?>
    <?= $content ?>
  </main>
  <footer class="site-footer">
    <div class="footer-main">
      <div><div class="footer-brand">FELITO</div><p>Sorvetes artesanais feitos com ingredientes escolhidos com cuidado e tempo para maturar.</p></div>
      <div><div class="eyebrow">Feito para saborear</div><a href="/">Conheça os sabores</a><a href="/carrinho">Seu carrinho</a></div>
      <div><div class="eyebrow">Retirada</div><p>Data solicitada no pedido. A equipe confirma a disponibilidade pelo telefone cadastrado.</p></div>
    </div>
    <div class="footer-bottom"><span>© <?= date('Y') ?> Felito</span><span>Sorvetes artesanais sob encomenda</span></div>
  </footer>
</body>
</html>
