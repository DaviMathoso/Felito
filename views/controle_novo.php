<?php
use App\Services\VendaService;

$controleActive = 'vendas';
$controleTitle = 'Novo pedido';
$controleLead = 'Monte o pedido item por item e confirme. O estoque do local escolhido é baixado ao finalizar.';
$head = $cart['head'] + ['client_name' => '', 'client_phone' => '', 'location_id' => 0, 'sale_date' => ''];
$locationId = (int) $head['location_id'] ?: (int) $defaultLocation;
$saleDate = $head['sale_date'] !== '' ? $head['sale_date'] : date('Y-m-d');
$flavorNames = [];
foreach ($flavors as $item) $flavorNames[(int) $item['id']] = $item['name'];
$sizeNames = [];
foreach ($sizes as $item) $sizeNames[(int) $item['id']] = $item['name'];
$orderTotal = 0.0;
?>
<section class="content-layout manager-layout">
  <?php require BASE_PATH . '/views/controle_nav.php'; ?>

  <form id="order-form" method="post" action="/gestor/controle/novo" class="panel cart-box">
    <?= csrfField() ?>
    <div><div class="eyebrow"><span class="dot"></span>Passo 1</div><h2>Dados do pedido</h2></div>
    <div class="form-four">
      <div class="field-group"><label for="client_name">Cliente</label><input id="client_name" name="client_name" type="text" maxlength="120" list="client-names" autocomplete="off" value="<?= e($head['client_name']) ?>"><datalist id="client-names"><?php foreach ($clientNames as $clientName): ?><option value="<?= e($clientName) ?>"><?php endforeach ?></datalist></div>
      <div class="field-group"><label for="client_phone">Telefone</label><input id="client_phone" name="client_phone" type="tel" maxlength="30" value="<?= e($head['client_phone']) ?>"></div>
      <div class="field-group"><label for="location_id">Local da venda</label><select id="location_id" name="location_id"><?php foreach ($locations as $location): ?><option value="<?= (int) $location['id'] ?>"<?= $locationId === (int) $location['id'] ? ' selected' : '' ?>><?= e($location['name']) ?></option><?php endforeach ?></select></div>
      <div class="field-group"><label for="sale_date">Data</label><input id="sale_date" name="sale_date" type="date" value="<?= e($saleDate) ?>"></div>
    </div>

    <div><div class="eyebrow"><span class="dot"></span>Passo 2</div><h2>Adicionar item</h2></div>
    <div class="form-row-end">
      <div class="field-group"><label for="flavor_id">Sabor</label><select id="flavor_id" name="flavor_id"><option value="">Selecione</option><?php foreach ($flavors as $item): ?><option value="<?= (int) $item['id'] ?>"><?= e($item['name']) ?></option><?php endforeach ?></select></div>
      <div class="field-group"><label for="size_id">Tamanho</label><select id="size_id" name="size_id"><option value="">Selecione</option><?php foreach ($sizes as $item): ?><option value="<?= (int) $item['id'] ?>"><?= e($item['name']) ?></option><?php endforeach ?></select></div>
      <div class="field-group"><label for="qty">Qtd</label><input id="qty" name="qty" type="number" min="1" max="9999" value="1"></div>
      <div class="field-group"><label for="unit_price">Valor unitário (opcional)</label><input id="unit_price" name="unit_price" type="text" inputmode="decimal" placeholder="Usa o catálogo"></div>
      <div class="field-group"><label for="status">Situação</label><select id="status" name="status"><?php foreach (VendaService::STATUSES as $code => $label): ?><option value="<?= e($code) ?>"><?= e($label) ?></option><?php endforeach ?></select></div>
      <button class="button button-outline button-small" type="submit" name="action" value="add">Adicionar item</button>
    </div>
    <p id="price-hint" class="field-help" aria-live="polite"></p>

    <div><div class="eyebrow"><span class="dot"></span>Passo 3</div><h2>Itens do pedido</h2></div>
    <?php if (!$cart['lines']): ?>
      <p class="muted-copy">Nenhum item adicionado ainda.</p>
    <?php else: ?>
      <div class="table-scroll"><table class="data-table">
        <thead><tr><th>Sabor</th><th>Tamanho</th><th class="num">Qtd</th><th class="num">Unitário</th><th class="num">Total</th><th>Situação</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($cart['lines'] as $index => $line):
            $status = (string) $line['status'];
            $typed = \App\Core\Money::parse($line['unit_price'] ?? '');
            $price = $status === 'resgate' ? 0.0 : ($typed ?? ($prices[$locationId][$line['flavor_id'] . '|' . $line['size_id']] ?? 0.0));
            $orderTotal += $price * (int) $line['qty']; ?>
          <tr>
            <td><?= e($flavorNames[(int) $line['flavor_id']] ?? '—') ?></td>
            <td><?= e($sizeNames[(int) $line['size_id']] ?? '—') ?></td>
            <td class="num"><?= (int) $line['qty'] ?></td>
            <td class="num"><?= $status === 'resgate' ? '—' : e(money($price)) ?></td>
            <td class="num"><?= $status === 'resgate' ? '—' : e(money($price * (int) $line['qty'])) ?></td>
            <td><span class="status status-ctl-<?= e($status) ?>"><?= e(VendaService::label($status)) ?></span></td>
            <td class="actions"><button class="button button-danger button-small" type="submit" name="action" value="remove-<?= (int) $index ?>" formnovalidate>Remover</button></td>
          </tr>
        <?php endforeach ?>
        </tbody>
        <tfoot><tr><td colspan="4">Total do pedido</td><td class="num"><?= e(money($orderTotal)) ?></td><td colspan="2"></td></tr></tfoot>
      </table></div>
    <?php endif ?>

    <div class="form-row-end">
      <button class="button button-primary button-auto" type="submit" name="action" value="finalize"<?= $cart['lines'] ? '' : ' disabled' ?>>Finalizar pedido</button>
      <?php if ($cart['lines']): ?><button class="button button-outline button-small" type="submit" name="action" value="clear" formnovalidate onclick="return confirm('Remover todos os itens do pedido?')">Limpar itens</button><?php endif ?>
      <a class="button button-outline button-small" href="/gestor/controle">Voltar às vendas</a>
    </div>
  </form>
</section>
<script>
(function () {
  var PRICES = <?= json_encode($prices, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
  var form = document.getElementById('order-form');
  var hint = document.getElementById('price-hint');
  if (!form || !hint) return;
  function update() {
    var loc = form.elements.location_id.value, f = form.elements.flavor_id.value, s = form.elements.size_id.value;
    if (!f || !s) { hint.textContent = 'Escolha o sabor e o tamanho para ver o preço do catálogo neste local.'; return; }
    var price = (PRICES[loc] || {})[f + '|' + s];
    hint.textContent = price === undefined
      ? 'Sem preço cadastrado para este local: informe o valor unitário.'
      : 'Preço do catálogo neste local: R$ ' + Number(price).toFixed(2).replace('.', ',');
  }
  ['location_id', 'flavor_id', 'size_id'].forEach(function (name) { form.elements[name].addEventListener('change', update); });
  update();
})();
</script>
