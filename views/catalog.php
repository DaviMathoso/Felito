<h1>Sabores do mês</h1>
<?php foreach ($flavors as $f): ?>
  <article>
    <h3><?= e($f['name']) ?></h3>
    <p><?= e($f['description']) ?></p>
    <strong>R$ <?= number_format($f['price'], 2, ',', '.') ?></strong>
  </article>
<?php endforeach ?>
