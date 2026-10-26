# Sistema anterior do cliente e lacunas do Felito atual

Análise de 26/10/2026. Referência do código atual: commit `edadefa`, com os arquivos antigos recebidos na pasta `docs/Felito-main-cliente/Felito-main`.

## Conclusão

O sistema anterior era um painel de operação de vendas e produção: o operador cadastrava compradores, lançava vendas, acompanhava entregas e cobranças, produzia lotes e distribuía estoque entre locais. O Felito atual oferece uma loja online com catálogo, carrinho e conta do comprador, acompanhada de um painel administrativo mais restrito.

Portanto, o atual ainda não substitui integralmente a operação suportada pelo anterior. As principais lacunas são venda assistida pelo gestor, cadastro de clientes sem conta de acesso, contas a receber, estoque por local, transferências e produção com custos.

O fluxo descrito abaixo foi reconstruído pelo código. Os arquivos não comprovam quais recursos o cliente efetivamente utilizava, com que frequência ou com quais configurações. Não foram consultados o Firestore, o armazenamento do navegador do cliente nem a instalação publicada do Felito atual. Não houve execução funcional dos sistemas; esta é uma análise estática de funcionalidades, rotas, regras e modelo de dados.

## 1. Como o sistema anterior permitia trabalhar

### 1.1 Acesso e cadastro de compradores

O operador entrava com e-mail e senha pelo Firebase Authentication. Depois do login, a tela principal apresentava pedidos e atalhos para Estoque, Vendas, Fidelidade, Financeiro, Clientes e Produtos.

Os compradores eram registros de nome e telefone, separados da conta que acessava o painel. Era possível cadastrá-los durante a venda, pesquisar por nome/telefone, editar seus dados e consultar valores a receber. O comprador não precisava criar e-mail e senha para uma venda ser lançada.

**Evidências:** `index.html:18`, `app.js:296`, `app.js:349`, `app.js:1710`, `app.js:2207` do sistema anterior.

### 1.2 Lançamento e manutenção de vendas

Em **Lançar Novo Pedido**, o operador selecionava comprador, local de venda, tamanho, sabor, quantidade, preço unitário e situação. Adicionava itens a um carrinho e finalizava o lançamento. O preço era sugerido pelo catálogo e podia ser alterado no atendimento.

O sistema atribuía um número de pedido, registrava a data e baixava estoque do local de venda. A tabela mostrava cada item em sua própria linha. Cada linha podia ter sua situação alterada; também havia edição de produto, quantidade, preço, cliente, local e número, além de exclusão. Alterações e exclusões de itens possuíam rotinas de recomposição de estoque.

Situações disponíveis: **A Fazer, À Entregar, Entregue, Pago, Cancelado e Resgate**. O mesmo campo misturava andamento da entrega, pagamento e natureza da operação. Não eram estados independentes.

**Evidências:** `index.html:180`, `app.js:691`, `app.js:810`, `app.js:928`, `app.js:1142`, `app.js:1187`, `app.js:2516`.

### 1.3 Cobranças e pendências

Em **Financeiro**, havia visões de valores a receber, itens a entregar e todas as pendências. Os lançamentos eram agrupados por comprador, com total em aberto e ações para copiar uma cobrança e marcar os itens listados como pagos. Um item entregue continuava a receber enquanto não fosse marcado como Pago.

A mensagem de cobrança agrupava sabores e tamanhos e incluía o total e uma instrução Pix. Havia também links para abrir o WhatsApp do comprador. Isso representa apoio manual à cobrança, sem evidência de envio automático ou confirmação bancária de pagamento.

**Evidências:** `app.js:772`, `app.js:804`, `app.js:1840`, `app.js:1885`, `app.js:1913`, `app.js:1932`, `app.js:2025`.

### 1.4 Produção, locais e movimentação

Em **Estoque**, a navegação visível desta versão contém **Visão Geral, Produção e Movimentação**.

- **Visão Geral:** saldo por local, sabor e tamanho; total de unidades e valor atribuído ao estoque; ajuste de saldo e cadastro/edição de locais.
- **Produção:** lote numerado, data, local de destino, sabores, tamanhos, quantidades, custo total e observação. Confirmar o lote acrescentava as unidades ao estoque. Havia histórico e edição do cabeçalho do lote.
- **Movimentação:** transferência entre origem e destino, com verificação de saldo; histórico de entradas, vendas, ajustes e transferências.

Os locais iniciais do código são **Casa, Mercado Delicatto e Eventos**. São padrões do programa, não prova dos locais efetivamente usados pelo cliente. Os tamanhos iniciais são **240 mL, 480 mL e 1,5 L**.

O custo unitário da produção era calculado distribuindo o custo informado proporcionalmente ao volume e acrescentando embalagem por tamanho. Isso não constitui controle de ingredientes, receitas ou consumo de matéria-prima.

**Evidências:** `index.html:375`, `app.js:2402`, `app.js:2425`, `app.js:2564`, `app.js:2688`, `app.js:3944`, `app.js:4167`, `app.js:4441`, `app.js:4521`.

### 1.5 Produtos e preços

Havia cadastros independentes de sabores e tamanhos, com ativação/desativação. Uma matriz definia preços por combinação de sabor e tamanho, permitindo preço específico por local. Na ausência deste, o programa buscava o preço global e depois um preço padrão.

**Evidências:** `app.js:44`, `app.js:56`, `app.js:81`, `app.js:254`, `app.js:5175`, `app.js:5312`.

### 1.6 Fidelidade

O programa usava selos, brindes e vouchers por comprador. As configurações previam valor de compra por selo, quantidade de selos por brinde e período de apuração: últimos X dias, mês atual, ano atual ou todo o histórico.

Os padrões são R$ 15 por selo, 10 selos por brinde e janela de 90 dias. Não representam necessariamente a configuração salva do cliente. Também existiam lançamentos de Resgate com valor zero, consulta de voucher e ações de resgatar/cancelar resgate.

Há inconsistências na implementação: a finalização usa divisor fixo 15 e algumas recomputações descontam 10 selos por resgate, embora existam configurações; as rotinas de recomputação também não aplicam de maneira uniforme exclusões por situação. Não se deve copiar essas regras sem consolidar o comportamento desejado.

**Evidências:** `app.js:22`, `app.js:463`, `app.js:499`, `app.js:523`, `app.js:553`, `app.js:1224`, `app.js:1489`, `app.js:1646`, `app.js:1695`.

### 1.7 Relatórios e dados

O relatório de vendas trazia visão geral, valores e unidades por mês, comparação entre períodos, ticket médio, participação por sabor/tamanho e ranking de compradores. Havia um resumo mensal separado por sabor e por sabor/tamanho.

O relatório de vendas exclui Cancelado e Resgate; o resumo mensal separado soma os lançamentos do mês sem a mesma exclusão. Os valores rotulados como receita são valores dos lançamentos selecionados, não necessariamente dinheiro recebido.

Havia importação/exportação de pedidos e compradores em CSV/JSON, armazenamento local e sincronização com Firestore. Pedidos, clientes, fidelidade, estoque por local, transferências, catálogo e lotes eram distribuídos entre documentos/coleções diferentes.

**Evidências:** `app.js:1321`, `app.js:1336`, `app.js:1440`, `app.js:1463`, `app.js:1471`, `app.js:2363`, `app.js:4696`, `app.js:4849`, `app.js:4865`, `app.js:4957`, `app.js:5028`, `app.js:5102`.

## 2. Comparação com o código atual

“Ausente” significa que não foi encontrado fluxo equivalente nas rotas, controladores, serviços, telas e schema atuais. “Parcial” significa que existe uma base, mas ela não cobre o mesmo processo.

| Processo | Situação atual | Lacuna para atender ao processo anterior |
|---|---|---|
| Venda lançada pelo operador | Ausente no painel | Criar pedido em nome de um comprador, inclusive venda de balcão/WhatsApp, com preço autorizado no atendimento. O checkout atual usa o usuário autenticado. |
| Cadastro operacional de clientes | Ausente | CRUD e pesquisa de compradores com nome e telefone, sem exigir conta de acesso. Hoje os compradores são usuários com e-mail e senha. |
| Preparação e entrega | Presente com diferenças | O atual tem Recebido → Em preparo → Pronto → Entregue e cancelamento; o anterior opera situação por item. Definir se entrega parcial por item é necessária. |
| Edição de itens vendidos | Ausente | Alterar itens/quantidades após criar o pedido, recalculando valor e estoque com histórico. Hoje o gestor altera andamento ou cancela o pedido. |
| Contas a receber | Ausente | Registrar pagamento, consultar saldo por comprador e dar baixa. O total do pedido atual não identifica se houve recebimento. |
| Cobrança e WhatsApp | Ausente como fluxo equivalente | Extrato de pendências e texto de cobrança copiável. O painel atual mostra telefone/e-mail e links de ligação/e-mail. |
| Sabores, tamanhos e preços | Parcial | Hoje cada produto tem um tamanho textual e um preço. É possível cadastrar produtos separados por tamanho, mas falta a matriz de variações e preços por local. |
| Estoque básico | Presente | Entradas, ajustes, baixa na encomenda, devolução em cancelamento/expiração, histórico e alerta de mínimo já existem. |
| Estoque por local | Ausente | Saldo por local e produto, indicação do local da venda e escolha do local abastecido. Hoje há um saldo único por produto. |
| Transferências | Ausente | Transferir unidades entre locais mantendo o total global e registrando origem/destino. |
| Lotes de produção | Ausente | Registrar lote com data, itens, destino e custo, gerando entrada vinculada. Informar “produção” no motivo de uma entrada não cria um lote estruturado. |
| Custos e valorização do estoque | Ausente | Guardar custos de produção/embalagem e custo unitário por lote; definir critério de valorização. O preço de venda atual não substitui esses dados. |
| Relatórios comerciais | Ausente como módulo equivalente | Vendas por período, sabor/tamanho, ticket médio e ranking de compradores. Os indicadores operacionais de pedidos atuais não cobrem isso. |
| Resumo mensal de vendas | Ausente | Totalizar quantidades e valores vendidos por sabor/tamanho. O fechamento mensal atual é de movimentação de estoque. |
| Fidelidade | Parcial, com regra diferente | Hoje há pontos por real, crédito na entrega, recompensas e resgates. Faltam equivalência com selos, janela de validade, vouchers e atendimento assistido de compradores sem conta. |
| Importação/exportação operacional | Ausente | Importar dados antigos e exportar vendas/clientes. A exportação individual de privacidade existente não equivale ao backup operacional. |
| Atualização automática entre operadores | Ausente como equivalente | O anterior escuta alterações do Firestore; o atual renderiza páginas por requisição, sem esse mecanismo de atualização automática. A necessidade deve ser avaliada pelo uso simultâneo. |

**Fontes atuais:** [rotas](../public/index.php), [painel administrativo](../app/Controllers/AdminController.php), [pedidos e checkout](../app/Controllers/OrderController.php), [cadastro de acesso](../app/Controllers/AuthController.php), [regras de pedidos](../app/Services/OrderService.php), [estoque](../app/Services/StockService.php), [produtos](../app/Services/ProductService.php), [fidelidade](../app/Controllers/LoyaltyController.php), [resgates](../app/Services/LoyaltyService.php), [schema](../database/schema.sql) e telas `views/admin_*.php`.

## 3. Diferenças que precisam orientar a implementação

### Pagamento e entrega devem ser independentes

O antigo conseguia manter uma entrega em aberto para cobrança, mas misturava tudo no mesmo campo. O atual não possui estado de pagamento. A implementação deve permitir representar, por exemplo, uma encomenda **entregue e não paga**, ou **paga e ainda em preparo**, sem perder informação.

Não foi identificado no antigo um módulo completo de despesas, fluxo de caixa, conciliação bancária ou pagamento parcial. Esses itens seriam ampliação de escopo, não reposição comprovada de funções anteriores.

### Cliente comercial deve poder existir sem login

Comprador e conta de acesso precisam ser vinculáveis sem serem obrigatoriamente o mesmo registro. Isso permite importar nome/telefone, registrar atendimento presencial e, posteriormente, associar uma conta da loja ao cadastro correto.

### Reserva de encomenda não deve ser confundida com venda concluída

O atual expira reservas após cinco dias, inclusive quando estão em preparo ou prontas. Essa política existe no código atual, mas não foi localizada como equivalente no antigo. Ao adicionar vendas administrativas, é necessário distinguir reserva de venda efetiva e revisar a aplicação da expiração a cada fluxo.

O antigo também baixava vendas usando `Math.max(0, saldo - quantidade)`, permitindo ocultar insuficiência ao truncar o saldo em zero. O atual bloqueia estoque insuficiente com transação. Essa proteção deve ser preservada ao acrescentar locais e produção.

### Nem todo código antigo corresponde a uma função acessível

Existe uma função `renderCustosTab()` com histórico de custos e painéis de margem. Porém, a navegação e o despachante de estoque desta versão não oferecem a aba Custos. O cálculo e a apresentação de custo unitário na Produção têm ligação com a interface; o painel separado de margens deve ser classificado como código legado sem acesso identificado pela navegação normal.

O botão **Copiar catálogo** aparece no HTML e tem uma referência JavaScript, mas não foi encontrado seu tratador de clique. Assim, não há base para afirmar que esse botão funcionava na cópia recebida. Custos de embalagem são carregados e usados no cálculo, mas não foi localizada uma tela para alterá-los.

## 4. Migração dos dados anteriores

Os arquivos recebidos contêm o programa, mas não um conjunto de registros comerciais exportados. A configuração do Firebase não equivale a exportar o banco. Para migrar a operação será necessário obter os dados efetivos por uma exportação autorizada ou pelo navegador utilizado pelo responsável.

O JSON exportado pelo botão antigo contém apenas `items`, `cart`, `nextOrder`, `stock`, `clients` e `fidelity`. Ele não inclui `stockV2`, locais, transferências, lotes de produção, catálogo mestre, configurações de fidelidade nem custos de embalagem. O CSV de pedidos também não exporta o local de venda.

Portanto, importar somente o backup JSON antigo pode perder parte relevante da operação. O levantamento deve abranger `pedidos`, documentos de `meta`, `producao_lotes` e dados locais ainda não sincronizados, conciliando diferenças antes de qualquer carga.

O planejamento da migração deve preservar identificadores antigos, números/datas dos pedidos, situação de entrega, pagamentos, vínculos entre compradores e itens, saldos por local e benefícios pendentes. A conversão de selos para pontos exige regra explícita. A mistura de pagamento e entrega no status antigo impede deduzir automaticamente todos os estados que o sistema novo poderá representar.

## 5. Ordem sugerida de implementação

1. **Base comercial e atendimento:** cadastro de clientes separado do login; pedido pelo gestor; edição controlada de itens; pagamento separado da entrega; recebíveis e cobrança por cliente. Validar uma venda assistida até entrega e baixa financeira.
2. **Locais e distribuição:** saldos por produto/local, local de venda, preço por local e transferências. Validar que uma transferência não altera o total global e que a venda baixa o local correto.
3. **Produção e custos:** lotes com entrada vinculada, custos e embalagem, histórico e correções rastreáveis. Validar o cálculo de custo e os efeitos de uma correção sem duplicar entrada.
4. **Gestão comercial e fidelidade:** relatórios de vendas/recebimentos, filtros por período e cliente, equivalência de benefícios, vouchers se necessários e exportações operacionais.
5. **Migração e aceite:** preparar e conferir os dados desde o início; executar a carga definitiva após o modelo estar pronto, conferindo totais de vendas, recebíveis, estoque e benefícios. Validar com o responsável os fluxos reconstruídos antes de desativar o anterior.

As prioridades acima são uma proposta baseada no impacto operacional observado no código. A frequência real de uso dos módulos e a disponibilidade dos registros antigos ainda precisam ser confirmadas com o responsável.
