<aside class="sidebar" aria-label="Atalhos">
  <div class="side-label">Sua visita</div>
  <nav class="side-nav" aria-label="Menu do cliente">
    <a href="/"<?= $name === 'catalog' ? ' class="active" aria-current="page"' : '' ?>><span class="dot" aria-hidden="true"></span>Sabores do mês</a>
    <a href="/carrinho"<?= $name === 'cart' ? ' class="active" aria-current="page"' : '' ?>><span class="dot" aria-hidden="true"></span>Seu carrinho</a>
    <a href="/pedidos"<?= $name === 'orders' ? ' class="active" aria-current="page"' : '' ?>><span class="dot" aria-hidden="true"></span>Meus pedidos</a>
    <?php if (currentUser()): ?><a href="/fidelidade"<?= $name === 'loyalty' ? ' class="active" aria-current="page"' : '' ?>><span class="dot" aria-hidden="true"></span>Pontos e brindes</a><a href="/privacidade"<?= $name === 'privacy' ? ' class="active" aria-current="page"' : '' ?>><span class="dot" aria-hidden="true"></span>Meus dados</a><?php endif ?>
  </nav>
  <p class="sidebar-note">Preparado em pequenos lotes para chegar à sua mesa no ponto certo.</p>
</aside>
