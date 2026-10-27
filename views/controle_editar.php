<?php
use App\Services\VendaService;

$controleActive = 'vendas';
$controleTitle = 'Editar lançamento';
$controleLead = 'Ao salvar, o estoque é estornado e baixado de novo quando local, produto ou quantidade mudam.';
$v = static fn(string $key, mixed $fallback = ''): string => (string) ($old[$key] ?? $fallback);
$priceDefault = number_format((float) $sale['unit_price'], 2, ',', '');
?>
<section class="content-layout manager-layout">
  <?php require BASE_PATH . '/views/controle_nav.php'; ?>
  <section class="panel manager-form-panel">
    <div class="eyebrow"><span class="dot"></span>Pedido #<?= e(str_pad((string) (int) $sale['order_no'], 3, '0', STR_PAD_LEFT)) ?></div>
    <h2><?= e($sale['client_name']) ?></h2>
    <form method="post" action="/gestor/controle/editar" class="form-stack">
      <?= csrfField() ?><input type="hidden" name="sale_id" value="<?= (int) $sale['id'] ?>">
      <div class="form-three">
        <div class="field-group"><label for="order_no">Nº do pedido</label><input id="order_no" name="order_no" type="number" min="1" required value="<?= e($v('order_no', $sale['order_no'])) ?>"></div>
        <div class="field-group"><label for="sale_date">Data</label><input id="sale_date" name="sale_date" type="date" required value="<?= e($v('sale_date', $sale['sale_date'])) ?>"></div>
        <div class="field-group"><label for="location_id">Local</label><select id="location_id" name="location_id"><?php foreach ($locations as $item): ?><option value="<?= (int) $item['id'] ?>"<?= (int) $v('location_id', $sale['location_id']) === (int) $item['id'] ? ' selected' : '' ?>><?= e($item['name']) ?><?= (int) $item['is_active'] === 0 ? ' (inativo)' : '' ?></option><?php endforeach ?></select></div>
      </div>
      <div class="form-two">
        <div class="field-group"><label for="client_name">Cliente</label><input id="client_name" name="client_name" type="text" maxlength="120" required value="<?= e($v('client_name', $sale['client_name'])) ?>"></div>
        <div class="field-group"><label for="client_phone">Telefone</label><input id="client_phone" name="client_phone" type="tel" maxlength="30" value="<?= e($v('client_phone', $sale['client_phone'])) ?>"></div>
      </div>
      <div class="form-four">
        <div class="field-group"><label for="flavor_id">Sabor</label><select id="flavor_id" name="flavor_id"><?php foreach ($flavors as $item): ?><option value="<?= (int) $item['id'] ?>"<?= (int) $v('flavor_id', $sale['flavor_id']) === (int) $item['id'] ? ' selected' : '' ?>><?= e($item['name']) ?><?= (int) $item['is_active'] === 0 ? ' (inativo)' : '' ?></option><?php endforeach ?></select></div>
        <div class="field-group"><label for="size_id">Tamanho</label><select id="size_id" name="size_id"><?php foreach ($sizes as $item): ?><option value="<?= (int) $item['id'] ?>"<?= (int) $v('size_id', $sale['size_id']) === (int) $item['id'] ? ' selected' : '' ?>><?= e($item['name']) ?><?= (int) $item['is_active'] === 0 ? ' (inativo)' : '' ?></option><?php endforeach ?></select></div>
        <div class="field-group"><label for="qty">Quantidade</label><input id="qty" name="qty" type="number" min="1" max="9999" required value="<?= e($v('qty', $sale['qty'])) ?>"></div>
        <div class="field-group"><label for="unit_price">Valor unitário</label><input id="unit_price" name="unit_price" type="text" inputmode="decimal" value="<?= e($v('unit_price', $priceDefault)) ?>"></div>
      </div>
      <div class="field-group"><label for="status">Situação</label><select id="status" name="status"><?php foreach (VendaService::STATUSES as $code => $label): ?><option value="<?= e($code) ?>"<?= $v('status', $sale['status']) === $code ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach ?></select><span class="field-help">Em "Resgate" o valor vira R$ 0,00. "Cancelado" devolve as unidades ao estoque.</span></div>
      <div class="form-row-end"><button class="button button-primary button-auto" type="submit">Salvar alterações</button><a class="button button-outline button-small" href="/gestor/controle?month=<?= e(substr((string) $sale['sale_date'], 0, 7)) ?>">Cancelar</a></div>
    </form>
  </section>
</section>
