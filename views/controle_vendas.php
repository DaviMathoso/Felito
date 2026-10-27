<?php
use App\Services\VendaService;

$controleActive = 'vendas';
$controleTitle = 'Vendas';
$controleLead = 'Lançamentos de venda por pedido, com situação, valores e baixa automática no estoque.';
$monthLabel = static fn(string $ym): string => $ym === 'all' ? 'Todos os períodos' : substr($ym, 5, 2) . '/' . substr($ym, 0, 4);
$filterFields = '';
foreach ($filter as $key => $value) $filterFields .= '<input type="hidden" name="' . e($key) . '" value="' . e($value) . '">';
$listTotal = 0.0;
?>
<section class="content-layout manager-layout">
  <?php require BASE_PATH . '/views/controle_nav.php'; ?>

  <div class="stat-grid stat-grid-4">
    <article class="panel stat-card"><span class="eyebrow">Pedidos no período</span><strong><?= (int) $kpis['orders'] ?></strong><small><?= e($monthLabel($filter['month'])) ?></small></article>
    <article class="panel stat-card"><span class="eyebrow">Unidades por tamanho</span><div class="stat-lines"><?php foreach ($kpis['by_size'] as $size): ?><span><b><?= e($size['name']) ?></b><?= (int) $size['qty'] ?></span><?php endforeach ?></div></article>
    <article class="panel stat-card"><span class="eyebrow">Valor vendido</span><strong><?= e(money($kpis['revenue'])) ?></strong><small>Sem cancelados</small></article>
    <article class="panel stat-card"><span class="eyebrow">Pendentes de entrega</span><strong><?= (int) $kpis['pending'] ?></strong><small>A Fazer ou À Entregar</small></article>
  </div>

  <section class="panel filter-panel">
    <form method="get" action="/gestor/controle" class="filter-form">
      <label for="f-month">Período</label>
      <select id="f-month" name="month"><option value="all"<?= $filter['month'] === 'all' ? ' selected' : '' ?>>Todos os períodos</option><?php foreach ($months as $ym): ?><option value="<?= e($ym) ?>"<?= $filter['month'] === $ym ? ' selected' : '' ?>><?= e($monthLabel($ym)) ?></option><?php endforeach ?></select>
      <label for="f-status">Situação</label>
      <select id="f-status" name="status"><option value="">Todas</option><?php foreach (VendaService::STATUSES as $code => $label): ?><option value="<?= e($code) ?>"<?= $filter['status'] === $code ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach ?></select>
      <label for="f-q">Cliente</label>
      <input id="f-q" name="q" type="search" maxlength="80" placeholder="Nome ou telefone" value="<?= e($filter['q']) ?>">
      <label for="f-sort">Ordenar</label>
      <select id="f-sort" name="sort"><?php foreach (['pedido-desc' => 'Pedido (mais novo)', 'pedido-asc' => 'Pedido (mais antigo)', 'recent' => 'Lançados recentemente', 'oldest' => 'Lançados primeiro', 'sabor-asc' => 'Sabor A→Z'] as $code => $label): ?><option value="<?= e($code) ?>"<?= $filter['sort'] === $code ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach ?></select>
      <button class="button button-outline button-small" type="submit">Filtrar</button>
    </form>
    <a class="button button-primary button-small button-auto" href="/gestor/controle/novo">Novo pedido</a>
  </section>

  <?php if (!$rows): ?>
    <section class="panel empty-state"><h2>Nenhum lançamento encontrado</h2><p>Ajuste os filtros ou lance o primeiro pedido deste período.</p><a class="button button-primary button-small button-auto" href="/gestor/controle/novo">Novo pedido</a></section>
  <?php else: ?>
    <section class="panel"><div class="table-scroll"><table class="data-table">
      <thead><tr><th>Pedido</th><th>Data</th><th>Cliente</th><th>Local</th><th>Item</th><th class="num">Qtd</th><th class="num">Unitário</th><th class="num">Total</th><th>Situação</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $row): $total = (float) $row['qty'] * (float) $row['unit_price']; if ($row['status'] !== 'cancelado') $listTotal += $total; ?>
        <tr>
          <td>#<?= e(str_pad((string) (int) $row['order_no'], 3, '0', STR_PAD_LEFT)) ?></td>
          <td><?= e(date('d/m/Y', strtotime((string) $row['sale_date']))) ?></td>
          <td><?= e($row['client_name']) ?><?php if ($row['client_phone'] !== ''): ?><span class="cell-sub"><?= e($row['client_phone']) ?></span><?php endif ?></td>
          <td><span class="location-dot" style="background: <?= e($row['location_color']) ?>"></span><?= e($row['location_name']) ?></td>
          <td><?= e($row['flavor_name']) ?><span class="cell-sub"><?= e($row['size_name']) ?></span></td>
          <td class="num"><?= (int) $row['qty'] ?></td>
          <td class="num"><?= $row['status'] === 'resgate' ? '—' : e(money($row['unit_price'])) ?></td>
          <td class="num"><?= $row['status'] === 'resgate' ? '—' : e(money($total)) ?></td>
          <td>
            <form method="post" action="/gestor/controle" class="inline-status">
              <?= csrfField() ?><?= $filterFields ?><input type="hidden" name="action" value="status"><input type="hidden" name="sale_id" value="<?= (int) $row['id'] ?>">
              <select name="status" aria-label="Situação do pedido <?= (int) $row['order_no'] ?>" onchange="this.form.submit()"><?php foreach (VendaService::STATUSES as $code => $label): ?><option value="<?= e($code) ?>"<?= $row['status'] === $code ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach ?></select>
              <noscript><button class="button button-outline button-small" type="submit">OK</button></noscript>
            </form>
          </td>
          <td class="actions">
            <a class="button button-outline button-small" href="/gestor/controle/editar?id=<?= (int) $row['id'] ?>">Editar</a>
            <form method="post" action="/gestor/controle" class="inline-form" onsubmit="return confirm('Excluir este lançamento? O estoque será ajustado.')"><?= csrfField() ?><?= $filterFields ?><input type="hidden" name="action" value="delete"><input type="hidden" name="sale_id" value="<?= (int) $row['id'] ?>"><button class="button button-danger button-small" type="submit">Excluir</button></form>
          </td>
        </tr>
      <?php endforeach ?>
      </tbody>
      <tfoot><tr><td colspan="7"><?= count($rows) ?> lançamento(s) listados</td><td class="num"><?= e(money($listTotal)) ?></td><td colspan="2"></td></tr></tfoot>
    </table></div></section>
  <?php endif ?>

  <?php if ($summary && ($summary['by_flavor'] || $summary['by_size'])): ?>
    <section class="panel manager-form-panel"><div class="eyebrow"><span class="dot"></span>Resumo mensal</div><h2><?= e($monthLabel($filter['month'])) ?></h2>
      <div class="table-scroll"><table class="data-table"><thead><tr><th>Sabor</th><th class="num">Unidades</th><th class="num">Valor</th></tr></thead><tbody>
        <?php foreach ($summary['by_flavor'] as $item): ?><tr><td><?= e($item['flavor']) ?></td><td class="num"><?= (int) $item['qty'] ?></td><td class="num"><?= e(money($item['value'])) ?></td></tr><?php endforeach ?>
      </tbody></table></div>
      <details><summary class="muted-copy">Ver por sabor e tamanho</summary><div class="table-scroll"><table class="data-table"><thead><tr><th>Sabor</th><th>Tamanho</th><th class="num">Unidades</th><th class="num">Valor</th></tr></thead><tbody>
        <?php foreach ($summary['by_size'] as $item): ?><tr><td><?= e($item['flavor']) ?></td><td><?= e($item['size']) ?></td><td class="num"><?= (int) $item['qty'] ?></td><td class="num"><?= e(money($item['value'])) ?></td></tr><?php endforeach ?>
      </tbody></table></div></details>
    </section>
  <?php endif ?>

  <section class="panel manager-form-panel"><div class="eyebrow"><span class="dot"></span>Planilhas</div><h2>Exportar e importar</h2>
    <p class="muted-copy">Os arquivos seguem o formato do sistema anterior (pedido;data;cliente;telefone;tam;qtd;sabor;valor;status). Importar o mesmo arquivo duas vezes duplica os lançamentos.</p>
    <div class="form-row-end">
      <a class="button button-outline button-small" href="/gestor/controle/exportar?tipo=vendas&amp;<?= e(http_build_query($filter)) ?>">Exportar vendas (CSV)</a>
      <a class="button button-outline button-small" href="/gestor/controle/exportar?tipo=clientes">Exportar clientes (CSV)</a>
    </div>
    <form method="post" action="/gestor/controle/importar" enctype="multipart/form-data" class="form-stack">
      <?= csrfField() ?><input type="hidden" name="tipo" value="vendas">
      <div class="form-two">
        <div class="field-group"><label for="imp-file">Importar vendas (.csv ou .json)</label><input id="imp-file" name="file" type="file" accept=".csv,.json" required></div>
        <div class="field-group"><label for="imp-loc">Local dos lançamentos sem local no arquivo</label><select id="imp-loc" name="location_id"><?php foreach ($locations as $location): ?><option value="<?= (int) $location['id'] ?>"<?= (int) $location['is_default'] === 1 ? ' selected' : '' ?>><?= e($location['name']) ?></option><?php endforeach ?></select></div>
      </div>
      <label class="check-row"><input type="checkbox" name="adjust_stock" value="1"><span>Dar baixa no estoque ao importar (deixe desmarcado para importar só o histórico)</span></label>
      <button class="button button-outline button-small button-auto" type="submit">Importar vendas</button>
    </form>
    <form method="post" action="/gestor/controle/importar" enctype="multipart/form-data" class="form-stack">
      <?= csrfField() ?><input type="hidden" name="tipo" value="clientes">
      <div class="field-group"><label for="imp-clients">Importar clientes (.csv ou .json)</label><input id="imp-clients" name="file" type="file" accept=".csv,.json" required></div>
      <button class="button button-outline button-small button-auto" type="submit">Importar clientes</button>
    </form>
  </section>
</section>
