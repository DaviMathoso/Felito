# Publicação no InfinityFree

## Atualização em 20/10/2026

- Pacote: `output/infinityfree-20261020-110931-149/felito-htdocs.zip`, gerado com `-UpdateOnly`: 26 arquivos, sem `.env`, SQL ou novas credenciais.
- Enviado e extraído no `/htdocs` da conta existente pelo gerenciador aberto. Mudanças em relação ao pacote anterior: `public/assets/style.css`, `views/catalog.php`, `views/layout.php` e o novo `views/customer_menu.php`.
- Cópia anterior de `public` e `views` preservada no servidor em `/backup-felito-20261020-1109`, fora de `htdocs`.
- `.env` existente preservado: o gerenciador continuou exibindo 142 bytes e modificação em 15/10. Nenhum SQL, migração, cadastro ou pedido de teste foi executado nesta atualização.
- Verificados no site público: catálogo com os três sabores existentes, carrinho vazio com menu lateral e acesso a Meus pedidos redirecionando para login quando anônimo. A listagem autenticada de pedidos não foi testada nesta atualização.
- Validação local: sintaxe dos três arquivos PHP envolvidos e HTTP 200 no catálogo/carrinho, com o novo menu e sem avisos PHP no corpo.
- Evidência visual: `output/infinityfree-20261020-110931-149/site-atualizado.png`.

## Publicação inicial em 15/10/2026

- Loja: https://felito.freedev.app/
- Gestão: https://felito.freedev.app/gestor
- Banco: `if0_43187108_felito`, no host `sql102.infinityfree.com`.
- Pacote publicado: `output/infinityfree-20261015-172845-029/felito-htdocs.zip`.
- Acesso administrativo atual: `output/infinityfree-20261015-172845-029/ACESSO-GESTOR.txt` (somente local, ignorado pelo Git).

As credenciais de pacotes gerados anteriormente não correspondem ao gestor publicado. A senha do banco foi configurada somente no `.env` protegido do servidor, sem inclusão no repositório ou ZIP.

## Evidências na conta real

- Banco novo criado e schema importado com sucesso: cinco tabelas, três produtos e três movimentos iniciais.
- SQL do gestor importado e login administrativo confirmado.
- Catálogo e arquivos de estilo servidos por HTTPS sem aviso de certificado.
- Carrinho e pedido #1 persistidos, com baixa de uma unidade de Baunilha.
- Pedido avançado para Em preparo e depois cancelado; a devolução foi registrada e os estoques voltaram a 12, 10 e 8 unidades.
- Horários de pedido e movimentações exibidos em São Paulo.
- Acesso direto a `/config/config.php` recusado com página 403 do provedor. A tentativa de abrir `/.env` não retornou conteúdo, mas o navegador reportou resposta vazia; não foi possível medir o status HTTP dessa tentativa.
- Sessão administrativa encerrada ao final. O pedido #1 permanece cancelado no histórico, identificado como teste de publicação.

O cadastro de um novo cliente, a entrega final e a edição de produtos foram validados no ambiente local da implementação; não foram repetidos nesta publicação. Os dados do Docker local não foram enviados nem alterados.

Captura do site público: `output/publicacao-infinityfree/site-publicado.jpg`.

## Correção identificada durante o envio

O ZIP anterior usava barras invertidas nos nomes internos, produzidas pelo .NET Framework no Windows. O gerenciador da hospedagem extraía apenas os arquivos da raiz. O empacotador agora cria entradas ZIP com `/`, e a extração das quatro pastas foi conferida no servidor. O pacote publicado contém 26 arquivos.
