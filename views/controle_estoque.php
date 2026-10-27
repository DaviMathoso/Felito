<?php
$controleActive = 'estoque';
$controleTitle = 'Estoque do controle';
$controleLead = 'Saldo por local, sabor e tamanho, produção em lotes e histórico de movimentações.';
$qtyCell = static fn(int $qty): string => $qty < 0 ? '<span class="negative">' . $qty . '</span>' : (string) $qty;
$dot = static fn(string $color): string => '<span class="location-dot" style="background: ' . e($color) . '"></span>';
?>
<section class="content-layout manager-layout">
  <?php require BASE_PATH . '/views/controle_nav.php'; ?>
  <nav class="tab-nav" aria-label="Seções do estoque"><?php foreach ($tabs as $key => $label): ?><a<?= $tab === $key ? ' class="active" aria-current="page"' : '' ?> href="/gestor/controle/estoque?aba=<?= e($key) ?>"><?= e($label) ?></a><?php endforeach ?></nav>

<?php if ($tab === 'visao'): ?>
  <div class="stat-grid">
    <?php foreach ($totals as $total): ?>
      <article class="panel stat-card"><span class="eyebrow"><?= $dot($total['color']) ?><?= e($total['name']) ?><?= (int) $total['is_default'] === 1 ? ' · padrão' : '' ?><?= (int) $total['is_active'] === 0 ? ' · inativo' : '' ?></span><strong><?= (int) $total['qty'] ?> un.</strong><small>Valor em estoque: <?= e(money($total['total_value'])) ?></small></article>
    <?php endforeach ?>
  </div>

  <section class="panel filter-panel"><form method="get" action="/gestor/controle/estoque" class="filter-form"><input type="hidden" name="aba" value="visao"><label for="loc-filter">Mostrar estoque de</label><select id="loc-filter" name="local"><option value="0">Todos os locais</option><?php foreach ($locations as $location): ?><option value="<?= (int) $location['id'] ?>"<?= $locationFilter === (int) $location['id'] ? ' selected' : '' ?>><?= e($location['name']) ?></option><?php endforeach ?></select><button class="button button-outline button-small" type="submit">Filtrar</button></form></section>

  <section class="panel manager-form-panel"><div class="eyebrow"><span class="dot"></span>Saldo por sabor e tamanho</div>
    <?php if (!$grid): ?><p class="muted-copy">Nenhum saldo registrado. Lance a primeira produção na aba Produção.</p><?php else: ?>
    <div class="table-scroll"><table class="data-table"><thead><tr><th>Sabor</th><?php foreach ($sizes as $size): ?><th class="num"><?= e($size['name']) ?></th><?php endforeach ?></tr></thead><tbody>
      <?php foreach ($grid as $row): ?><tr><td><?= e($row['name']) ?></td><?php foreach ($sizes as $size): $qty = (int) ($row['sizes'][(int) $size['id']] ?? 0); ?><td class="num"><?= $qty === 0 ? '—' : $qtyCell($qty) ?></td><?php endforeach ?></tr><?php endforeach ?>
    </tbody></table></div>
    <details><summary class="muted-copy">Ver detalhe por local e valor unitário</summary><div class="table-scroll"><table class="data-table"><thead><tr><th>Local</th><th>Sabor</th><th>Tamanho</th><th class="num">Saldo</th><th class="num">Valor unit.</th><th class="num">Valor total</th></tr></thead><tbody>
      <?php foreach ($matrix as $row): ?><tr><td><?= $dot($row['color']) ?><?= e($row['location_name']) ?></td><td><?= e($row['flavor_name']) ?></td><td><?= e($row['size_name']) ?></td><td class="num"><?= $qtyCell((int) $row['qty']) ?></td><td class="num"><?= e(money($row['unit_value'])) ?></td><td class="num"><?= e(money($row['total_value'])) ?></td></tr><?php endforeach ?>
    </tbody></table></div></details>
    <?php endif ?>
  </section>

  <div class="manager-grid">
    <section class="panel manager-form-panel"><div class="eyebrow"><span class="dot"></span>Contagem</div><h2>Ajustar saldo</h2><p class="muted-copy">Informe o saldo contado. O histórico guarda o saldo anterior.</p>
      <form method="post" action="/gestor/controle/estoque" class="form-stack"><?= csrfField() ?><input type="hidden" name="aba" value="visao"><input type="hidden" name="action" value="adjust">
        <label for="adj-loc">Local</label><select id="adj-loc" name="location_id"><?php foreach ($activeLocations as $location): ?><option value="<?= (int) $location['id'] ?>"><?= e($location['name']) ?></option><?php endforeach ?></select>
        <label for="adj-flavor">Sabor</label><select id="adj-flavor" name="flavor_id" required><option value="">Selecione</option><?php foreach ($flavors as $item): ?><option value="<?= (int) $item['id'] ?>"><?= e($item['name']) ?></option><?php endforeach ?></select>
        <label for="adj-size">Tamanho</label><select id="adj-size" name="size_id" required><option value="">Selecione</option><?php foreach ($sizes as $item): ?><option value="<?= (int) $item['id'] ?>"><?= e($item['name']) ?></option><?php endforeach ?></select>
        <label for="adj-qty">Saldo correto</label><input id="adj-qty" name="qty" type="number" min="0" max="999999" required>
        <label for="adj-note">Motivo (opcional)</label><input id="adj-note" name="note" type="text" maxlength="150" placeholder="Ex.: contagem do freezer">
        <button class="button button-primary" type="submit">Ajustar saldo</button>
      </form>
    </section>

    <section class="panel manager-form-panel"><div class="eyebrow"><span class="dot"></span>Locais de venda</div><h2><?= $editingLocation ? 'Editar ' . e($editingLocation['name']) : 'Locais' ?></h2>
      <div class="table-scroll"><table class="data-table"><tbody>
        <?php foreach ($locations as $location): ?>
          <tr><td><?= $dot($location['color']) ?><?= e($location['name']) ?><?= (int) $location['is_default'] === 1 ? ' <span class="tag">Padrão</span>' : '' ?><?= (int) $location['is_active'] === 0 ? ' <span class="tag">Inativo</span>' : '' ?></td>
            <td class="actions">
              <a class="button button-outline button-small" href="/gestor/controle/estoque?aba=visao&amp;editar_local=<?= (int) $location['id'] ?>">Editar</a>
              <?php if ((int) $location['is_default'] === 0): ?><form method="post" action="/gestor/controle/estoque" class="inline-form"><?= csrfField() ?><input type="hidden" name="aba" value="visao"><input type="hidden" name="action" value="loc_default"><input type="hidden" name="location_id" value="<?= (int) $location['id'] ?>"><button class="button button-outline button-small" type="submit">Tornar padrão</button></form><?php endif ?>
              <form method="post" action="/gestor/controle/estoque" class="inline-form"><?= csrfField() ?><input type="hidden" name="aba" value="visao"><input type="hidden" name="action" value="loc_toggle"><input type="hidden" name="location_id" value="<?= (int) $location['id'] ?>"><button class="button button-outline button-small" type="submit"><?= (int) $location['is_active'] === 1 ? 'Desativar' : 'Ativar' ?></button></form>
            </td></tr>
        <?php endforeach ?>
      </tbody></table></div>
      <form method="post" action="/gestor/controle/estoque" class="form-stack"><?= csrfField() ?><input type="hidden" name="aba" value="visao"><input type="hidden" name="action" value="loc_save"><?php if ($editingLocation): ?><input type="hidden" name="location_id" value="<?= (int) $editingLocation['id'] ?>"><?php endif ?>
        <label for="loc-name"><?= $editingLocation ? 'Nome do local' : 'Novo local' ?></label><input id="loc-name" name="location_name" type="text" maxlength="80" required value="<?= e($editingLocation['name'] ?? '') ?>">
        <span class="field-help">Cor de identificação</span>
        <div class="color-swatches"><?php foreach ($colors as $index => $color): ?><label><input type="radio" name="color" value="<?= e($color) ?>"<?= ($editingLocation['color'] ?? $colors[0]) === $color ? ' checked' : '' ?>><?= $dot($color) ?></label><?php endforeach ?></div>
        <button class="button button-outline button-small button-auto" type="submit"><?= $editingLocation ? 'Salvar local' : 'Adicionar local' ?></button>
      </form>
    </section>
  </div>

<?php elseif ($tab === 'producao'):
    $bh = ($batch['head'] ?? []) + ['batch_no' => '', 'batch_date' => '', 'location_id' => 0, 'total_cost' => '', 'note' => ''];
    $bhLocation = (int) $bh['location_id'] ?: (int) $defaultLocation;
    $bhDate = $bh['batch_date'] !== '' ? $bh['batch_date'] : date('Y-m-d');
    $flavorNames = array_column($flavors, 'name', 'id');
    $sizeNames = array_column($sizes, 'name', 'id');
?>
  <form method="post" action="/gestor/controle/estoque" class="panel cart-box"><?= csrfField() ?><input type="hidden" name="aba" value="producao">
    <div><div class="eyebrow"><span class="dot"></span>Novo lote</div><h2>Registrar produção</h2><p class="muted-copy">Ao confirmar, as unidades entram no estoque do local escolhido. O custo total é rateado por volume e embalagem de cada tamanho.</p></div>
    <div class="form-four">
      <div class="field-group"><label for="batch_no">Nº do lote</label><input id="batch_no" name="batch_no" type="number" min="1" placeholder="<?= (int) $nextBatchNo ?>" value="<?= e($bh['batch_no']) ?>"></div>
      <div class="field-group"><label for="batch_date">Data</label><input id="batch_date" name="batch_date" type="date" value="<?= e($bhDate) ?>"></div>
      <div class="field-group"><label for="b-loc">Local que recebe</label><select id="b-loc" name="location_id"><?php foreach ($activeLocations as $location): ?><option value="<?= (int) $location['id'] ?>"<?= $bhLocation === (int) $location['id'] ? ' selected' : '' ?>><?= e($location['name']) ?></option><?php endforeach ?></select></div>
      <div class="field-group"><label for="total_cost">Custo total do lote (R$)</label><input id="total_cost" name="total_cost" type="text" inputmode="decimal" placeholder="Opcional" value="<?= e($bh['total_cost']) ?>"></div>
    </div>
    <div class="field-group"><label for="b-note">Observação</label><input id="b-note" name="note" type="text" maxlength="200" value="<?= e($bh['note']) ?>"></div>

    <div class="form-row-end">
      <div class="field-group"><label for="b-flavor">Sabor</label><select id="b-flavor" name="flavor_id"><option value="">Selecione</option><?php foreach ($flavors as $item): ?><option value="<?= (int) $item['id'] ?>"><?= e($item['name']) ?></option><?php endforeach ?></select></div>
      <div class="field-group"><label for="b-size">Tamanho</label><select id="b-size" name="size_id"><option value="">Selecione</option><?php foreach ($sizes as $item): ?><option value="<?= (int) $item['id'] ?>"><?= e($item['name']) ?></option><?php endforeach ?></select></div>
      <div class="field-group"><label for="b-qty">Quantidade</label><input id="b-qty" name="qty" type="number" min="1" max="99999" value="1"></div>
      <div class="field-group"><label for="b-price">Preço de venda (opcional)</label><input id="b-price" name="unit_price" type="text" inputmode="decimal" placeholder="Usa o catálogo"></div>
      <button class="button button-outline button-small" type="submit" name="action" value="add">Adicionar ao lote</button>
    </div>

    <?php if ($batch['lines']): ?>
      <div class="table-scroll"><table class="data-table"><thead><tr><th>Sabor</th><th>Tamanho</th><th class="num">Qtd</th><th class="num">Preço de venda</th><th class="num">Custo unitário</th><th></th></tr></thead><tbody>
      <?php foreach ($batch['lines'] as $index => $line):
          $typed = \App\Core\Money::parse($line['unit_price'] ?? '');
          $price = $typed ?? ($batchPrices[$line['flavor_id'] . '|' . $line['size_id']] ?? null);
          $cost = $batchCosts[(int) $line['size_id']] ?? null; ?>
        <tr><td><?= e($flavorNames[(int) $line['flavor_id']] ?? '—') ?></td><td><?= e($sizeNames[(int) $line['size_id']] ?? '—') ?></td><td class="num"><?= (int) $line['qty'] ?></td><td class="num"><?= $price === null ? '—' : e(money($price)) ?></td><td class="num"><?= $cost === null ? '—' : e(money($cost)) ?></td><td class="actions"><button class="button button-danger button-small" type="submit" name="action" value="remove-<?= (int) $index ?>" formnovalidate>Remover</button></td></tr>
      <?php endforeach ?>
      </tbody></table></div>
    <?php else: ?><p class="muted-copy">Nenhum item no lote ainda.</p><?php endif ?>
    <div class="form-row-end">
      <button class="button button-primary button-auto" type="submit" name="action" value="confirm"<?= $batch['lines'] ? '' : ' disabled' ?>>Confirmar lote e dar entrada</button>
      <?php if ($batch['lines']): ?><button class="button button-outline button-small" type="submit" name="action" value="clear" formnovalidate onclick="return confirm('Limpar os itens do lote?')">Limpar itens</button><?php endif ?>
    </div>
  </form>

  <section class="panel manager-form-panel"><div class="eyebrow"><span class="dot"></span>Histórico</div><h2>Lotes registrados</h2>
    <?php if (!$batches): ?><p class="muted-copy">Nenhum lote registrado ainda.</p><?php endif ?>
    <?php foreach ($batches as $item): ?>
      <details class="client-group"><summary><span>Lote <?= (int) $item['batch_no'] ?> · <?= e(date('d/m/Y', strtotime((string) $item['batch_date']))) ?> · <?= $dot($item['location_color']) ?><?= e($item['location_name']) ?></span><span><?= (int) $item['units'] ?> un. · custo <?= e(money($item['total_cost'])) ?></span></summary>
        <div class="group-body">
          <div class="table-scroll"><table class="data-table"><thead><tr><th>Sabor</th><th>Tamanho</th><th class="num">Qtd</th><th class="num">Custo unit.</th></tr></thead><tbody>
            <?php foreach ($item['items'] as $line): ?><tr><td><?= e($line['flavor_name']) ?></td><td><?= e($line['size_name']) ?></td><td class="num"><?= (int) $line['qty'] ?></td><td class="num"><?= $line['unit_cost'] === null ? '—' : e(money($line['unit_cost'])) ?></td></tr><?php endforeach ?>
          </tbody></table></div>
          <?php if ($item['note'] !== ''): ?><p class="muted-copy">Obs.: <?= e($item['note']) ?></p><?php endif ?>
          <form method="post" action="/gestor/controle/estoque" class="form-row-end"><?= csrfField() ?><input type="hidden" name="aba" value="producao"><input type="hidden" name="action" value="batch_update"><input type="hidden" name="batch_id" value="<?= (int) $item['id'] ?>">
            <div class="field-group"><label>Nº<input name="batch_no" type="number" min="1" required value="<?= (int) $item['batch_no'] ?>"></label></div>
            <div class="field-group"><label>Data<input name="batch_date" type="date" required value="<?= e($item['batch_date']) ?>"></label></div>
            <div class="field-group"><label>Custo total (R$)<input name="total_cost" type="text" inputmode="decimal" value="<?= e(number_format((float) $item['total_cost'], 2, ',', '')) ?>"></label></div>
            <div class="field-group"><label>Observação<input name="note" type="text" maxlength="200" value="<?= e($item['note']) ?>"></label></div>
            <button class="button button-outline button-small" type="submit">Salvar dados e recalcular custo</button>
          </form>
          <form method="post" action="/gestor/controle/estoque" class="inline-form" onsubmit="return confirm('Excluir o lote e retirar do estoque as unidades que ele adicionou?')"><?= csrfField() ?><input type="hidden" name="aba" value="producao"><input type="hidden" name="action" value="batch_delete"><input type="hidden" name="batch_id" value="<?= (int) $item['id'] ?>"><button class="button button-danger button-small" type="submit">Excluir lote</button></form>
        </div>
      </details>
    <?php endforeach ?>
  </section>

<?php else: ?>
  <div class="manager-grid">
    <section class="panel manager-form-panel"><div class="eyebrow"><span class="dot"></span>Entre locais</div><h2>Transferir</h2>
      <form method="post" action="/gestor/controle/estoque" class="form-stack"><?= csrfField() ?><input type="hidden" name="aba" value="movimentacao"><input type="hidden" name="action" value="transfer">
        <label for="t-from">De</label><select id="t-from" name="from_id"><?php foreach ($activeLocations as $location): ?><option value="<?= (int) $location['id'] ?>"><?= e($location['name']) ?></option><?php endforeach ?></select>
        <label for="t-to">Para</label><select id="t-to" name="to_id"><?php foreach ($activeLocations as $location): ?><option value="<?= (int) $location['id'] ?>"><?= e($location['name']) ?></option><?php endforeach ?></select>
        <label for="t-flavor">Sabor</label><select id="t-flavor" name="flavor_id" required><option value="">Selecione</option><?php foreach ($flavors as $item): ?><option value="<?= (int) $item['id'] ?>"><?= e($item['name']) ?></option><?php endforeach ?></select>
        <label for="t-size">Tamanho</label><select id="t-size" name="size_id" required><option value="">Selecione</option><?php foreach ($sizes as $item): ?><option value="<?= (int) $item['id'] ?>"><?= e($item['name']) ?></option><?php endforeach ?></select>
        <label for="t-qty">Quantidade</label><input id="t-qty" name="qty" type="number" min="1" max="999999" required>
        <label for="t-note">Observação</label><input id="t-note" name="note" type="text" maxlength="150">
        <button class="button button-primary" type="submit">Transferir</button>
      </form>
    </section>
    <section class="panel manager-form-panel"><div class="eyebrow"><span class="dot"></span>Sem lote</div><h2>Entrada manual</h2><p class="muted-copy">Para produção com custo e vários itens, use a aba Produção.</p>
      <form method="post" action="/gestor/controle/estoque" class="form-stack"><?= csrfField() ?><input type="hidden" name="aba" value="movimentacao"><input type="hidden" name="action" value="entry">
        <label for="e-loc">Local</label><select id="e-loc" name="location_id"><?php foreach ($activeLocations as $location): ?><option value="<?= (int) $location['id'] ?>"><?= e($location['name']) ?></option><?php endforeach ?></select>
        <label for="e-flavor">Sabor</label><select id="e-flavor" name="flavor_id" required><option value="">Selecione</option><?php foreach ($flavors as $item): ?><option value="<?= (int) $item['id'] ?>"><?= e($item['name']) ?></option><?php endforeach ?></select>
        <label for="e-size">Tamanho</label><select id="e-size" name="size_id" required><option value="">Selecione</option><?php foreach ($sizes as $item): ?><option value="<?= (int) $item['id'] ?>"><?= e($item['name']) ?></option><?php endforeach ?></select>
        <label for="e-qty">Quantidade</label><input id="e-qty" name="qty" type="number" min="1" max="999999" required>
        <label for="e-value">Valor unitário (opcional)</label><input id="e-value" name="unit_value" type="text" inputmode="decimal">
        <label for="e-note">Observação</label><input id="e-note" name="note" type="text" maxlength="150">
        <button class="button button-primary" type="submit">Registrar entrada</button>
      </form>
    </section>
  </div>

  <section class="panel manager-form-panel"><div class="toolbar"><div><div class="eyebrow"><span class="dot"></span>Histórico</div><h2>Últimas movimentações</h2></div>
    <form method="get" action="/gestor/controle/estoque"><input type="hidden" name="aba" value="movimentacao"><select name="tipo" aria-label="Tipo"><option value="">Todos os tipos</option><?php foreach ($moveLabels as $code => $label): ?><option value="<?= e($code) ?>"<?= $typeFilter === $code ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach ?></select><select name="local" aria-label="Local"><option value="0">Todos os locais</option><?php foreach ($locations as $location): ?><option value="<?= (int) $location['id'] ?>"<?= $locationFilter === (int) $location['id'] ? ' selected' : '' ?>><?= e($location['name']) ?></option><?php endforeach ?></select><button class="button button-outline button-small" type="submit">Filtrar</button></form></div>
    <?php if (!$history): ?><p class="muted-copy">Nenhuma movimentação encontrada.</p><?php else: ?>
    <div class="table-scroll"><table class="data-table"><thead><tr><th>Quando</th><th>Tipo</th><th>Item</th><th class="num">Qtd</th><th>Origem → destino</th><th>Observação</th><th>Por</th></tr></thead><tbody>
      <?php foreach ($history as $row): ?><tr><td><?= e(localDateTime((string) $row['moved_at'])) ?></td><td><?= e($moveLabels[$row['move_type']] ?? $row['move_type']) ?></td><td><?= e($row['flavor_name']) ?> <span class="cell-sub"><?= e($row['size_name']) ?></span></td><td class="num"><?= (int) $row['qty'] ?></td><td><?= e(($row['from_name'] ?? '—') . ' → ' . ($row['to_name'] ?? '—')) ?></td><td><?= e($row['note']) ?></td><td><?= e($row['user_name'] ?? '—') ?></td></tr><?php endforeach ?>
    </tbody></table></div>
    <?php endif ?>
  </section>
<?php endif ?>
</section>
