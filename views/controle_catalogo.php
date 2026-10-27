<?php
$controleActive = 'catalogo';
$controleTitle = 'Produtos do controle';
$controleLead = 'Sabores, tamanhos e tabela de preços usados nos lançamentos de venda e na produção.';
?>
<section class="content-layout manager-layout">
  <?php require BASE_PATH . '/views/controle_nav.php'; ?>
  <nav class="tab-nav" aria-label="Seções dos produtos"><?php foreach ($tabs as $key => $label): ?><a<?= $tab === $key ? ' class="active" aria-current="page"' : '' ?> href="/gestor/controle/catalogo?aba=<?= e($key) ?>"><?= e($label) ?></a><?php endforeach ?></nav>

<?php if ($tab === 'sabores'): ?>
  <div class="manager-grid">
    <section class="panel manager-form-panel"><div class="eyebrow"><span class="dot"></span><?= $editing ? 'Editar sabor' : 'Novo sabor' ?></div><h2><?= $editing ? e($editing['name']) : 'Adicionar' ?></h2>
      <form method="post" action="/gestor/controle/catalogo" class="form-stack"><?= csrfField() ?><input type="hidden" name="aba" value="sabores"><input type="hidden" name="action" value="flavor_save"><?php if ($editing): ?><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>"><?php endif ?>
        <label for="flavor_name">Nome do sabor</label><input id="flavor_name" name="flavor_name" type="text" maxlength="120" required value="<?= e($editing['name'] ?? '') ?>">
        <?php if ($editing): ?><label class="check-row"><input type="checkbox" name="is_active" value="1"<?= (int) $editing['is_active'] === 1 ? ' checked' : '' ?>><span>Disponível para novos lançamentos</span></label><?php endif ?>
        <button class="button button-primary" type="submit"><?= $editing ? 'Salvar sabor' : 'Adicionar sabor' ?></button>
        <?php if ($editing): ?><a class="button button-outline button-small" href="/gestor/controle/catalogo?aba=sabores">Cancelar edição</a><?php endif ?>
      </form>
    </section>
    <section class="panel manager-form-panel"><div class="eyebrow"><span class="dot"></span>Lista</div><h2><?= count($flavors) ?> sabor(es)</h2>
      <div class="table-scroll"><table class="data-table"><tbody>
        <?php foreach ($flavors as $item): ?><tr><td><?= e($item['name']) ?><?= (int) $item['is_active'] === 0 ? ' <span class="tag">Inativo</span>' : '' ?></td><td class="actions">
          <a class="button button-outline button-small" href="/gestor/controle/catalogo?aba=sabores&amp;editar=<?= (int) $item['id'] ?>">Editar</a>
          <form method="post" action="/gestor/controle/catalogo" class="inline-form"><?= csrfField() ?><input type="hidden" name="aba" value="sabores"><input type="hidden" name="action" value="flavor_toggle"><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><button class="button button-outline button-small" type="submit"><?= (int) $item['is_active'] === 1 ? 'Desativar' : 'Ativar' ?></button></form>
        </td></tr><?php endforeach ?>
      </tbody></table></div>
      <p class="muted-copy">Sabores com histórico não são apagados: desative para tirá-los das listas.</p>
    </section>
  </div>

<?php elseif ($tab === 'tamanhos'): ?>
  <div class="manager-grid">
    <section class="panel manager-form-panel"><div class="eyebrow"><span class="dot"></span><?= $editing ? 'Editar tamanho' : 'Novo tamanho' ?></div><h2><?= $editing ? e($editing['name']) : 'Adicionar' ?></h2>
      <form method="post" action="/gestor/controle/catalogo" class="form-stack"><?= csrfField() ?><input type="hidden" name="aba" value="tamanhos"><input type="hidden" name="action" value="size_save"><?php if ($editing): ?><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>"><?php endif ?>
        <label for="size_name">Nome (ex.: 480 mL)</label><input id="size_name" name="size_name" type="text" maxlength="60" required value="<?= e($editing['name'] ?? '') ?>">
        <label for="volume_ml">Volume em mL</label><input id="volume_ml" name="volume_ml" type="number" min="1" max="100000" required value="<?= e($editing['volume_ml'] ?? '') ?>">
        <label for="packaging_cost">Custo da embalagem (R$)</label><input id="packaging_cost" name="packaging_cost" type="text" inputmode="decimal" value="<?= e(isset($editing['packaging_cost']) ? number_format((float) $editing['packaging_cost'], 2, ',', '') : '0,00') ?>">
        <span class="field-help">O volume e a embalagem entram no cálculo do custo unitário de cada lote.</span>
        <?php if ($editing): ?><label class="check-row"><input type="checkbox" name="is_active" value="1"<?= (int) $editing['is_active'] === 1 ? ' checked' : '' ?>><span>Disponível para novos lançamentos</span></label><?php endif ?>
        <button class="button button-primary" type="submit"><?= $editing ? 'Salvar tamanho' : 'Adicionar tamanho' ?></button>
        <?php if ($editing): ?><a class="button button-outline button-small" href="/gestor/controle/catalogo?aba=tamanhos">Cancelar edição</a><?php endif ?>
      </form>
    </section>
    <section class="panel manager-form-panel"><div class="eyebrow"><span class="dot"></span>Lista</div><h2><?= count($sizes) ?> tamanho(s)</h2>
      <div class="table-scroll"><table class="data-table"><thead><tr><th>Tamanho</th><th class="num">Volume</th><th class="num">Embalagem</th><th></th></tr></thead><tbody>
        <?php foreach ($sizes as $item): ?><tr><td><?= e($item['name']) ?><?= (int) $item['is_active'] === 0 ? ' <span class="tag">Inativo</span>' : '' ?></td><td class="num"><?= (int) $item['volume_ml'] ?> mL</td><td class="num"><?= e(money($item['packaging_cost'])) ?></td><td class="actions">
          <a class="button button-outline button-small" href="/gestor/controle/catalogo?aba=tamanhos&amp;editar=<?= (int) $item['id'] ?>">Editar</a>
          <form method="post" action="/gestor/controle/catalogo" class="inline-form"><?= csrfField() ?><input type="hidden" name="aba" value="tamanhos"><input type="hidden" name="action" value="size_toggle"><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><button class="button button-outline button-small" type="submit"><?= (int) $item['is_active'] === 1 ? 'Desativar' : 'Ativar' ?></button></form>
        </td></tr><?php endforeach ?>
      </tbody></table></div>
    </section>
  </div>

<?php else: ?>
  <section class="panel filter-panel"><form method="get" action="/gestor/controle/catalogo" class="filter-form"><input type="hidden" name="aba" value="precos"><label for="price-loc">Tabela de preços de</label><select id="price-loc" name="local"><option value="0"<?= $priceLocation === 0 ? ' selected' : '' ?>>Todos os locais (preço global)</option><?php foreach ($locations as $location): ?><option value="<?= (int) $location['id'] ?>"<?= $priceLocation === (int) $location['id'] ? ' selected' : '' ?>><?= e($location['name']) ?></option><?php endforeach ?></select><button class="button button-outline button-small" type="submit">Abrir</button></form></section>
  <form method="post" action="/gestor/controle/catalogo" class="panel manager-form-panel"><?= csrfField() ?><input type="hidden" name="aba" value="precos"><input type="hidden" name="action" value="prices_save"><input type="hidden" name="price_location" value="<?= (int) $priceLocation ?>">
    <div class="eyebrow"><span class="dot"></span><?= $priceLocation === 0 ? 'Preço global' : 'Preço específico do local' ?></div>
    <p class="muted-copy"><?= $priceLocation === 0 ? 'Vale para todos os locais que não têm preço próprio.' : 'Deixe em branco para usar o preço global. O valor global aparece como sugestão em cada campo.' ?></p>
    <div class="table-scroll"><table class="data-table"><thead><tr><th>Sabor</th><?php foreach ($sizes as $size): ?><th><?= e($size['name']) ?></th><?php endforeach ?></tr></thead><tbody>
      <?php foreach ($flavors as $flavor): ?><tr><td><?= e($flavor['name']) ?></td>
        <?php foreach ($sizes as $size): $key = $flavor['id'] . '|' . $size['id'];
            $value = isset($own[$key]) ? number_format($own[$key], 2, ',', '') : '';
            $hint = $priceLocation !== 0 && isset($global[$key]) ? number_format($global[$key], 2, ',', '') : '—'; ?>
          <td><input name="price[<?= e($key) ?>]" type="text" inputmode="decimal" size="7" placeholder="<?= e($hint) ?>" aria-label="Preço de <?= e($flavor['name']) ?> <?= e($size['name']) ?>" value="<?= e($value) ?>"></td>
        <?php endforeach ?></tr><?php endforeach ?>
    </tbody></table></div>
    <button class="button button-primary button-auto" type="submit">Salvar tabela de preços</button>
  </form>
<?php endif ?>
</section>
