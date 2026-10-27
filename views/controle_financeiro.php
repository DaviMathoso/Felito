<?php
use App\Services\FinanceiroService;
use App\Services\VendaService;

$controleActive = 'financeiro';
$controleTitle = 'Financeiro';
$controleLead = 'Valores a receber e unidades a entregar, agrupados por cliente.';
?>
<section class="content-layout manager-layout">
  <?php require BASE_PATH . '/views/controle_nav.php'; ?>

  <div class="stat-grid">
    <article class="panel stat-card"><span class="eyebrow">Clientes na lista</span><strong><?= count($finance['clients']) ?></strong><small><?= e(FinanceiroService::TABS[$tab]) ?></small></article>
    <article class="panel stat-card"><span class="eyebrow">A receber</span><strong><?= e(money($finance['total_receive'])) ?></strong><small>Pedidos A Fazer, À Entregar ou Entregues</small></article>
    <article class="panel stat-card"><span class="eyebrow">A entregar</span><strong><?= (int) $finance['total_units'] ?></strong><small>Unidades ainda não entregues</small></article>
  </div>

  <section class="panel filter-panel">
    <div class="tab-nav"><?php foreach (FinanceiroService::TABS as $key => $label): ?><a<?= $tab === $key ? ' class="active" aria-current="page"' : '' ?> href="/gestor/controle/financeiro?aba=<?= e($key) ?><?= $search !== '' ? '&amp;q=' . e(urlencode($search)) : '' ?>"><?= e($label) ?></a><?php endforeach ?></div>
    <form method="get" action="/gestor/controle/financeiro" class="filter-form"><input type="hidden" name="aba" value="<?= e($tab) ?>"><label for="fin-q">Cliente</label><input id="fin-q" name="q" type="search" maxlength="80" placeholder="Nome ou telefone" value="<?= e($search) ?>"><button class="button button-outline button-small" type="submit">Buscar</button></form>
  </section>

  <?php if (!$finance['clients']): ?>
    <section class="panel empty-state"><h2>Nada por aqui</h2><p>Nenhum cliente tem lançamentos nesta aba.</p></section>
  <?php endif ?>

  <?php foreach ($finance['clients'] as $client): ?>
    <details class="client-group"<?= count($finance['clients']) === 1 ? ' open' : '' ?>>
      <summary><span><?= e($client['name']) ?><?php if ($client['phone'] !== ''): ?><span class="cell-sub"><?= e($client['phone']) ?></span><?php endif ?></span>
        <span><?php if ($client['to_receive'] > 0): ?><span class="negative"><?= e(money($client['to_receive'])) ?> a receber</span><?php endif ?><?php if ($client['to_deliver'] > 0): ?> <span class="tag"><?= (int) $client['to_deliver'] ?> a entregar</span><?php endif ?></span></summary>
      <div class="group-body">
        <div class="table-scroll"><table class="data-table">
          <thead><tr><th>Pedido</th><th>Data</th><th>Item</th><th class="num">Qtd</th><th class="num">Total</th><th>Situação</th></tr></thead>
          <tbody>
          <?php foreach ($client['lines'] as $line): $total = (float) $line['qty'] * (float) $line['unit_price']; ?>
            <tr>
              <td>#<?= e(str_pad((string) (int) $line['order_no'], 3, '0', STR_PAD_LEFT)) ?></td>
              <td><?= e(date('d/m/Y', strtotime((string) $line['sale_date']))) ?></td>
              <td><?= e($line['flavor_name']) ?> <span class="cell-sub"><?= e($line['size_name']) ?> · <?= e($line['location_name']) ?></span></td>
              <td class="num"><?= (int) $line['qty'] ?></td>
              <td class="num"><?= e(money($total)) ?></td>
              <td>
                <form method="post" action="/gestor/controle/financeiro" class="inline-status"><?= csrfField() ?><input type="hidden" name="action" value="line"><input type="hidden" name="aba" value="<?= e($tab) ?>"><input type="hidden" name="q" value="<?= e($search) ?>"><input type="hidden" name="sale_id" value="<?= (int) $line['id'] ?>">
                  <select name="status" aria-label="Situação" onchange="this.form.submit()"><?php foreach (VendaService::STATUSES as $code => $label): ?><option value="<?= e($code) ?>"<?= $line['status'] === $code ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach ?></select><noscript><button class="button button-outline button-small" type="submit">OK</button></noscript></form>
              </td>
            </tr>
          <?php endforeach ?>
          </tbody>
        </table></div>

        <div class="form-row-end">
          <form method="post" action="/gestor/controle/financeiro" class="inline-form" onsubmit="return confirm('Marcar todos os itens listados de <?= e(addslashes((string) $client['name'])) ?> como pagos?')"><?= csrfField() ?><input type="hidden" name="action" value="bulk"><input type="hidden" name="aba" value="<?= e($tab) ?>"><input type="hidden" name="q" value="<?= e($search) ?>"><input type="hidden" name="client_id" value="<?= (int) $client['id'] ?>"><input type="hidden" name="status" value="pago"><button class="button button-primary button-small button-auto" type="submit">Dar baixa (pago)</button></form>
          <form method="post" action="/gestor/controle/financeiro" class="inline-status"><?= csrfField() ?><input type="hidden" name="action" value="bulk"><input type="hidden" name="aba" value="<?= e($tab) ?>"><input type="hidden" name="q" value="<?= e($search) ?>"><input type="hidden" name="client_id" value="<?= (int) $client['id'] ?>">
            <select name="status" aria-label="Alterar todos para"><?php foreach (VendaService::STATUSES as $code => $label): ?><option value="<?= e($code) ?>"><?= e($label) ?></option><?php endforeach ?></select><button class="button button-outline button-small" type="submit">Alterar todos</button></form>
          <?php if ($client['message']): ?>
            <button class="button button-outline button-small" type="button" data-message="<?= e($client['message']) ?>" onclick="navigator.clipboard&amp;&amp;navigator.clipboard.writeText(this.dataset.message);this.textContent='Copiado!'">Copiar cobrança</button>
          <?php endif ?>
          <?php if ($client['whatsapp']): ?><a class="button button-outline button-small" href="<?= e($client['whatsapp']) ?>" target="_blank" rel="noopener noreferrer">WhatsApp</a><?php endif ?>
        </div>
      </div>
    </details>
  <?php endforeach ?>
</section>
