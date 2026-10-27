<?php
$controleTabs = [
    'vendas' => ['/gestor/controle', 'Vendas'],
    'clientes' => ['/gestor/controle/clientes', 'Clientes'],
    'financeiro' => ['/gestor/controle/financeiro', 'Financeiro'],
    'fidelidade' => ['/gestor/controle/fidelidade', 'Fidelidade'],
    'estoque' => ['/gestor/controle/estoque', 'Estoque'],
    'catalogo' => ['/gestor/controle/catalogo', 'Produtos'],
    'relatorios' => ['/gestor/controle/relatorios', 'Relatórios'],
];
?>
<div class="manager-heading"><div><div class="eyebrow"><span class="dot"></span>Painel do gestor · Controle de vendas</div><h1><?= e($controleTitle) ?></h1><p><?= e($controleLead) ?></p></div><nav class="manager-nav" aria-label="Menu do controle de vendas"><a href="/gestor">‹ Loja online</a><?php foreach ($controleTabs as $key => [$href, $label]): ?><a<?= $controleActive === $key ? ' class="active" aria-current="page"' : '' ?> href="<?= e($href) ?>"><?= e($label) ?></a><?php endforeach ?></nav></div>
