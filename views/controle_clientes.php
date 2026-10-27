<?php
$controleActive = 'clientes';
$controleTitle = 'Clientes';
$controleLead = 'Cadastro de clientes do controle de vendas, com total vendido e valores em aberto.';
$keep = $search !== '' ? '?q=' . urlencode($search) : '';
?>
<section class="content-layout manager-layout">
  <?php require BASE_PATH . '/views/controle_nav.php'; ?>

  <div class="manager-grid">
    <section class="panel manager-form-panel"><div class="eyebrow"><span class="dot"></span><?= $editing ? 'Editar cliente' : 'Novo cliente' ?></div><h2><?= $editing ? e($editing['name']) : 'Cadastrar' ?></h2>
      <form method="post" action="/gestor/controle/clientes" class="form-stack">
        <?= csrfField() ?><input type="hidden" name="action" value="save"><input type="hidden" name="q" value="<?= e($search) ?>"><?php if ($editing): ?><input type="hidden" name="client_id" value="<?= (int) $editing['id'] ?>"><?php endif ?>
        <label for="client_name">Nome</label><input id="client_name" name="client_name" type="text" maxlength="120" required value="<?= e($editing['name'] ?? '') ?>">
        <label for="client_phone">Telefone</label><input id="client_phone" name="client_phone" type="tel" maxlength="30" placeholder="(21) 99999-9999" value="<?= e($editing['phone'] ?? '') ?>">
        <?php if ($editing): ?><span class="field-help">Se o novo nome já existir em outro cadastro, os dois são unidos e os lançamentos passam para o cadastro existente.</span><?php endif ?>
        <button class="button button-primary" type="submit"><?= $editing ? 'Salvar cliente' : 'Cadastrar cliente' ?></button>
        <?php if ($editing): ?><a class="button button-outline button-small" href="/gestor/controle/clientes<?= e($keep) ?>">Cancelar edição</a><?php endif ?>
      </form>
    </section>

    <section class="panel manager-form-panel">
      <div class="toolbar"><div><div class="eyebrow"><span class="dot"></span>Lista</div><h2><?= count($clients) ?> cliente(s)</h2></div>
        <form method="get" action="/gestor/controle/clientes"><input name="q" type="search" maxlength="80" placeholder="Buscar por nome ou telefone" value="<?= e($search) ?>" aria-label="Buscar cliente"><button class="button button-outline button-small" type="submit">Buscar</button></form></div>

      <?php if ($account): ?>
        <div class="panel cart-box"><div class="eyebrow"><span class="dot"></span>Conta de <?= e($account['client']['name']) ?></div>
          <?php if ($account['message']): ?>
            <textarea class="message-box" readonly rows="6" onclick="this.select()"><?= e($account['message']) ?></textarea>
            <div class="form-row-end">
              <button class="button button-outline button-small" type="button" onclick="var t=this.parentNode.previousElementSibling;t.select();navigator.clipboard&amp;&amp;navigator.clipboard.writeText(t.value);this.textContent='Copiado!'">Copiar mensagem</button>
              <?php if ($account['whatsapp']): ?><a class="button button-outline button-small" href="<?= e($account['whatsapp']) ?>" target="_blank" rel="noopener noreferrer">Enviar pelo WhatsApp</a><?php endif ?>
            </div>
          <?php else: ?><p class="muted-copy">Este cliente não tem valores em aberto.</p><?php endif ?>
        </div>
      <?php endif ?>

      <?php if (!$clients): ?><p class="muted-copy">Nenhum cliente encontrado.</p><?php else: ?>
      <div class="table-scroll"><table class="data-table">
        <thead><tr><th>Cliente</th><th class="num">Pedidos</th><th class="num">Total vendido</th><th class="num">Em aberto</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($clients as $client): $link = \App\Services\ClienteService::whatsappLink((string) $client['phone']); ?>
          <tr>
            <td><?= e($client['name']) ?><span class="cell-sub"><?php if ($link): ?><a href="<?= e($link) ?>" target="_blank" rel="noopener noreferrer"><?= e($client['phone']) ?></a><?php else: ?><?= e($client['phone'] !== '' ? $client['phone'] : 'Sem telefone') ?><?php endif ?></span></td>
            <td class="num"><?= (int) $client['orders_count'] ?></td>
            <td class="num"><?= e(money($client['total_spent'])) ?></td>
            <td class="num"><?= (float) $client['open_amount'] > 0 ? '<span class="negative">' . e(money($client['open_amount'])) . '</span>' : '—' ?></td>
            <td class="actions">
              <a class="button button-outline button-small" href="/gestor/controle/clientes?editar=<?= (int) $client['id'] ?><?= $search !== '' ? '&amp;q=' . e(urlencode($search)) : '' ?>">Editar</a>
              <a class="button button-outline button-small" href="/gestor/controle/clientes?conta=<?= (int) $client['id'] ?><?= $search !== '' ? '&amp;q=' . e(urlencode($search)) : '' ?>">Conta</a>
              <?php if ((int) $client['orders_count'] === 0): ?><form method="post" action="/gestor/controle/clientes" class="inline-form" onsubmit="return confirm('Remover este cliente?')"><?= csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="q" value="<?= e($search) ?>"><input type="hidden" name="client_id" value="<?= (int) $client['id'] ?>"><button class="button button-danger button-small" type="submit">Remover</button></form><?php endif ?>
            </td>
          </tr>
        <?php endforeach ?>
        </tbody>
      </table></div>
      <?php endif ?>
    </section>
  </div>
</section>
