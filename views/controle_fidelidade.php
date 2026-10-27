<?php
use App\Services\FidelidadeService;

$controleActive = 'fidelidade';
$controleTitle = 'Fidelidade';
$controleLead = 'Selos por compra, brindes conquistados e resgates dos clientes.';
$stampValue = number_format((float) $config['stamp_value'], 2, ',', '');
$keep = http_build_query(['q' => $search, 'ordem' => $sort]);
$meter = static function (int $stamps, int $perGift): string {
    $html = '<span class="stamp-meter" aria-hidden="true">';
    $filled = $stamps % $perGift;
    if ($stamps > 0 && $filled === 0) $filled = $perGift;
    for ($i = 1; $i <= min($perGift, 20); $i++) $html .= '<i' . ($i <= $filled ? ' class="on"' : '') . '></i>';
    return $html . '</span>';
};
?>
<section class="content-layout manager-layout">
  <?php require BASE_PATH . '/views/controle_nav.php'; ?>

  <div class="manager-grid">
    <section class="panel manager-form-panel"><div class="eyebrow"><span class="dot"></span>Regras</div><h2>Como os selos funcionam</h2>
      <form method="post" action="/gestor/controle/fidelidade" class="form-stack">
        <?= csrfField() ?><input type="hidden" name="action" value="config"><input type="hidden" name="q" value="<?= e($search) ?>"><input type="hidden" name="ordem" value="<?= e($sort) ?>">
        <label for="stamp_value">Valor para ganhar 1 selo (R$)</label><input id="stamp_value" name="stamp_value" type="text" inputmode="decimal" required value="<?= e($stampValue) ?>">
        <label for="stamps_per_gift">Selos para ganhar 1 brinde</label><input id="stamps_per_gift" name="stamps_per_gift" type="number" min="1" max="1000" required value="<?= (int) $config['stamps_per_gift'] ?>">
        <label for="period_type">Período considerado</label><select id="period_type" name="period_type"><?php foreach (FidelidadeService::PERIODS as $code => $label): ?><option value="<?= e($code) ?>"<?= $config['period_type'] === $code ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach ?></select>
        <label for="window_days">Dias da janela (quando o período é "Últimos X dias")</label><input id="window_days" name="window_days" type="number" min="1" max="3650" value="<?= (int) $config['window_days'] ?>">
        <span class="field-help">Cada pedido gera selos pelo seu total (por exemplo, R$ 47 com selo de R$ 15 = 3 selos). Pedidos cancelados não pontuam e cada item em "Resgate" abate os selos de um brinde.</span>
        <button class="button button-primary" type="submit">Salvar regras</button>
      </form>
    </section>

    <section class="panel manager-form-panel">
      <div class="toolbar"><div><div class="eyebrow"><span class="dot"></span>Clientes</div><h2><?= count($standings) ?> no programa</h2></div>
        <form method="get" action="/gestor/controle/fidelidade"><input name="q" type="search" maxlength="80" placeholder="Buscar cliente" value="<?= e($search) ?>" aria-label="Buscar cliente"><select name="ordem" aria-label="Ordenar"><?php foreach (FidelidadeService::SORTS as $code => $label): ?><option value="<?= e($code) ?>"<?= $sort === $code ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach ?></select><button class="button button-outline button-small" type="submit">Aplicar</button></form></div>

      <?php if ($detail): $summary = $detail['summary']; ?>
        <div class="panel cart-box"><div class="eyebrow"><span class="dot"></span>Detalhe</div><h3><?= e($summary['name']) ?></h3>
          <p class="muted-copy"><?= (int) $summary['stamps'] ?> selo(s) agora · <?= (int) $summary['earned'] ?> ganhos no período · <?= (int) $summary['redeemed'] ?> resgate(s) lançados</p>
          <?= $meter((int) $summary['stamps'], (int) $config['stamps_per_gift']) ?>
          <?php if ($detail['gifts']): ?>
            <div class="table-scroll"><table class="data-table"><thead><tr><th>Voucher</th><th>Criado em</th><th>Situação</th><th></th></tr></thead><tbody>
            <?php foreach ($detail['gifts'] as $gift): ?>
              <tr><td><?= e($gift['voucher']) ?></td><td><?= e(localDateTime((string) $gift['created_at'], 'd/m/Y')) ?></td>
                <td><?= $gift['status'] === 'pendente' ? '<span class="status status-ctl-a_fazer">Pendente</span>' : '<span class="status status-ctl-entregue">Resgatado' . ($gift['redeemed_at'] ? ' em ' . e(localDateTime((string) $gift['redeemed_at'], 'd/m/Y')) : '') . '</span>' ?></td>
                <td class="actions"><form method="post" action="/gestor/controle/fidelidade" class="inline-form"><?= csrfField() ?><input type="hidden" name="q" value="<?= e($search) ?>"><input type="hidden" name="ordem" value="<?= e($sort) ?>"><input type="hidden" name="cliente" value="<?= (int) $summary['id'] ?>"><input type="hidden" name="gift_id" value="<?= (int) $gift['id'] ?>"><input type="hidden" name="action" value="<?= $gift['status'] === 'pendente' ? 'redeem' : 'undo' ?>"><button class="button button-outline button-small" type="submit"><?= $gift['status'] === 'pendente' ? 'Marcar resgatado' : 'Desfazer' ?></button></form></td></tr>
            <?php endforeach ?></tbody></table></div>
          <?php else: ?><p class="muted-copy">Ainda não há brindes conquistados.</p><?php endif ?>
          <?php if ($detail['redemptions']): ?><p class="muted-copy">Resgates lançados em vendas: <?php foreach ($detail['redemptions'] as $item): ?>#<?= (int) $item['order_no'] ?> (<?= e($item['flavor_name']) ?> <?= e($item['size_name']) ?>)  <?php endforeach ?></p><?php endif ?>
          <a class="button button-outline button-small button-auto" href="/gestor/controle/fidelidade?<?= e($keep) ?>">Fechar detalhe</a>
        </div>
      <?php endif ?>

      <?php if (!$standings): ?><p class="muted-copy">Nenhum cliente encontrado.</p><?php else: ?>
      <div class="table-scroll"><table class="data-table">
        <thead><tr><th>Cliente</th><th>Selos</th><th class="num">Brindes</th><th class="num">Pendentes</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($standings as $row): ?>
          <tr><td><?= e($row['name']) ?><?php if ($row['phone'] !== ''): ?><span class="cell-sub"><?= e($row['phone']) ?></span><?php endif ?></td>
            <td><strong><?= (int) $row['stamps'] ?></strong> <?= $meter((int) $row['stamps'], (int) $config['stamps_per_gift']) ?></td>
            <td class="num"><?= (int) $row['gifts_total'] ?></td>
            <td class="num"><?= (int) $row['gifts_pending'] > 0 ? '<span class="positive">' . (int) $row['gifts_pending'] . '</span>' : '0' ?></td>
            <td class="actions"><a class="button button-outline button-small" href="/gestor/controle/fidelidade?<?= e($keep) ?>&amp;cliente=<?= (int) $row['id'] ?>">Detalhes</a></td></tr>
        <?php endforeach ?>
        </tbody>
      </table></div>
      <?php endif ?>
    </section>
  </div>
</section>
