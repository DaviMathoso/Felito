<?php
use App\Services\VendaService;

$controleActive = 'relatorios';
$controleTitle = 'Relatórios de vendas';
$controleLead = 'Faturamento, unidades e ranking de sabores e clientes. Cancelados e resgates não entram nas somas.';
$monthLabel = static fn(string $ym): string => $ym === 'all' ? 'Todo o histórico' : substr($ym, 5, 2) . '/' . substr($ym, 0, 4);
$bar = static function (float $value, float $max): string {
    $width = $max > 0 ? max(2.0, round($value / $max * 100, 1)) : 0;
    return '<div class="bar-track"><div class="bar-fill" style="width: ' . $width . '%"></div></div>';
};
?>
<section class="content-layout manager-layout">
  <?php require BASE_PATH . '/views/controle_nav.php'; ?>
  <nav class="tab-nav" aria-label="Seções dos relatórios"><?php foreach ($tabs as $key => $label): ?><a<?= $tab === $key ? ' class="active" aria-current="page"' : '' ?> href="/gestor/controle/relatorios?aba=<?= e($key) ?>"><?= e($label) ?></a><?php endforeach ?></nav>

<?php if ($tab === 'visao'): $o = $overview; $maxRevenue = max(array_column($o['series'], 'revenue') ?: [0]); ?>
  <div class="stat-grid stat-grid-4">
    <article class="panel stat-card"><span class="eyebrow">Faturamento total</span><strong><?= e(money($o['revenue'])) ?></strong><small><?= (int) $o['orders'] ?> pedido(s)</small></article>
    <article class="panel stat-card"><span class="eyebrow">Unidades vendidas</span><strong><?= (int) $o['units'] ?></strong><small>Todo o histórico</small></article>
    <article class="panel stat-card"><span class="eyebrow">Ticket médio</span><strong><?= e(money($o['ticket'])) ?></strong><small>Por pedido</small></article>
    <article class="panel stat-card"><span class="eyebrow">Este mês</span><strong><?= e(money($o['current']['revenue'])) ?></strong><small><?php if ($o['variation'] === null): ?>Sem mês anterior para comparar<?php else: ?><span class="<?= $o['variation'] >= 0 ? 'positive' : 'negative' ?>"><?= $o['variation'] >= 0 ? '+' : '' ?><?= e(number_format($o['variation'], 1, ',', '.')) ?>%</span> sobre o mês anterior<?php endif ?></small></article>
  </div>
  <section class="panel manager-form-panel"><div class="eyebrow"><span class="dot"></span>Últimos 6 meses</div><h2>Faturamento por mês</h2>
    <div class="chart-columns"><?php foreach ($o['series'] as $point): $height = $maxRevenue > 0 ? max(2.0, round($point['revenue'] / $maxRevenue * 150, 1)) : 2; ?>
      <div class="chart-col"><span><?= e(money($point['revenue'])) ?></span><div class="bar" style="height: <?= $height ?>px"></div><span><?= e($monthLabel($point['ym'])) ?></span></div>
    <?php endforeach ?></div>
  </section>
  <section class="panel manager-form-panel"><div class="eyebrow"><span class="dot"></span>Situação dos lançamentos</div>
    <div class="alert-chips"><?php foreach (VendaService::STATUSES as $code => $label): ?><span class="status status-ctl-<?= e($code) ?>"><?= e($label) ?>: <?= (int) ($o['status_counts'][$code] ?? 0) ?></span><?php endforeach ?></div>
  </section>

<?php elseif ($tab === 'mensal'): ?>
  <section class="panel manager-form-panel"><div class="eyebrow"><span class="dot"></span>Histórico</div><h2>Resumo por mês</h2>
    <?php if (!$monthly): ?><p class="muted-copy">Ainda não há vendas registradas.</p><?php else: ?>
    <div class="table-scroll"><table class="data-table"><thead><tr><th>Mês</th><th class="num">Pedidos</th><th class="num">Unidades</th><th class="num">Faturamento</th><th class="num">Ticket médio</th><th>Por tamanho</th></tr></thead><tbody>
      <?php foreach ($monthly as $row): ?><tr><td><?= e($monthLabel($row['ym'])) ?></td><td class="num"><?= (int) $row['orders'] ?></td><td class="num"><?= (int) $row['units'] ?></td><td class="num"><?= e(money($row['revenue'])) ?></td><td class="num"><?= e(money($row['ticket'])) ?></td><td><?php foreach ($row['by_size'] as $size => $units): ?><span class="tag"><?= e($size) ?>: <?= (int) $units ?></span> <?php endforeach ?></td></tr><?php endforeach ?>
    </tbody></table></div>
    <?php endif ?>
  </section>

<?php else: ?>
  <section class="panel filter-panel"><form method="get" action="/gestor/controle/relatorios" class="filter-form"><input type="hidden" name="aba" value="<?= e($tab) ?>"><label for="rep-month">Período</label><select id="rep-month" name="mes"><option value="all">Todo o histórico</option><?php foreach ($months as $ym): ?><option value="<?= e($ym) ?>"<?= $month === $ym ? ' selected' : '' ?>><?= e($monthLabel($ym)) ?></option><?php endforeach ?></select><button class="button button-outline button-small" type="submit">Aplicar</button></form></section>

  <?php if ($tab === 'produtos'):
      $maxFlavor = max(array_map(static fn($r) => (float) $r['revenue'], $products['flavors']) ?: [0]);
      $totalRevenue = array_sum(array_map(static fn($r) => (float) $r['revenue'], $products['flavors'])); ?>
    <section class="panel manager-form-panel"><div class="eyebrow"><span class="dot"></span>Sabores</div><h2>Ranking por faturamento</h2>
      <?php if (!$products['flavors']): ?><p class="muted-copy">Sem vendas no período.</p><?php endif ?>
      <div class="bar-list"><?php foreach ($products['flavors'] as $row): ?><div class="bar-row"><span><?= e($row['name']) ?></span><?= $bar((float) $row['revenue'], $maxFlavor) ?><span><?= e(money($row['revenue'])) ?> · <?= (int) $row['units'] ?> un.<?= $totalRevenue > 0 ? ' · ' . e(number_format((float) $row['revenue'] / $totalRevenue * 100, 1, ',', '')) . '%' : '' ?></span></div><?php endforeach ?></div>
    </section>
    <section class="panel manager-form-panel"><div class="eyebrow"><span class="dot"></span>Tamanhos</div><h2>Vendas por tamanho</h2>
      <div class="table-scroll"><table class="data-table"><thead><tr><th>Tamanho</th><th class="num">Unidades</th><th class="num">Faturamento</th></tr></thead><tbody>
        <?php foreach ($products['sizes'] as $row): ?><tr><td><?= e($row['name']) ?></td><td class="num"><?= (int) $row['units'] ?></td><td class="num"><?= e(money($row['revenue'])) ?></td></tr><?php endforeach ?>
      </tbody></table></div>
    </section>
  <?php else:
      $maxClient = max(array_map(static fn($r) => (float) $r['revenue'], $clients) ?: [0]);
      $clientTotal = array_sum(array_map(static fn($r) => (float) $r['revenue'], $clients)); ?>
    <section class="panel manager-form-panel"><div class="eyebrow"><span class="dot"></span>Clientes</div><h2>Maiores compradores (até 30)</h2>
      <?php if (!$clients): ?><p class="muted-copy">Sem vendas no período.</p><?php else: ?>
      <div class="table-scroll"><table class="data-table"><thead><tr><th>Cliente</th><th></th><th class="num">Pedidos</th><th class="num">Unidades</th><th class="num">Faturamento</th><th class="num">Ticket médio</th><th class="num">% do ranking</th></tr></thead><tbody>
        <?php foreach ($clients as $row): ?><tr><td><?= e($row['name']) ?></td><td style="min-width: 110px"><?= $bar((float) $row['revenue'], $maxClient) ?></td><td class="num"><?= (int) $row['orders'] ?></td><td class="num"><?= (int) $row['units'] ?></td><td class="num"><?= e(money($row['revenue'])) ?></td><td class="num"><?= e(money((int) $row['orders'] > 0 ? (float) $row['revenue'] / (int) $row['orders'] : 0)) ?></td><td class="num"><?= $clientTotal > 0 ? e(number_format((float) $row['revenue'] / $clientTotal * 100, 1, ',', '')) . '%' : '—' ?></td></tr><?php endforeach ?>
      </tbody></table></div>
      <?php endif ?>
    </section>
  <?php endif ?>
<?php endif ?>
</section>
