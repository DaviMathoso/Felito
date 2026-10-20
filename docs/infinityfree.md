# Publicar o Felito no InfinityFree

O projeto está preparado para PHP 8.4 com `pdo_mysql`, `mbstring` e sessões. A publicação usa a raiz de um domínio/subdomínio; não instalar em uma subpasta. Não utiliza Docker, Composer, cron nem processos em segundo plano na hospedagem.

## 1. Gerar o pacote no computador

Na raiz do repositório, execute no PowerShell:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\package-infinityfree.ps1
```

Requer PHP local com mbstring ou a imagem Docker `felito-web` (criada com `docker compose build web`). O script não altera o banco local. Cada execução gera uma pasta nova em `output/infinityfree-<data>/` contendo:

- `felito-htdocs.zip`: somente o conteúdo que deve entrar em `htdocs`, incluindo os dois `.htaccess` e um `.env` de exemplo. Não contém banco, SQL nem senha do gestor.
- `schema.sql`: estrutura das cinco tabelas e três sabores iniciais, para um banco novo.
- `gestor.sql`: criação de uma conta administrativa com hash gerado por `password_hash()`. Não redefine contas existentes.
- `ACESSO-GESTOR.txt`: e-mail e senha aleatória de 24 caracteres. Guardar somente no computador.
- `LEIA-ME.md`: estas instruções.

Opcionalmente informe seu e-mail e nome ao gerar: `-AdminEmail 'seu@email.com' -AdminName 'Seu Nome'`. Não envie a pasta inteira de saída para o servidor. Use sempre o SQL e as credenciais da mesma geração.

## 2. Criar o site, HTTPS e banco

1. Crie a conta e um subdomínio gratuito no painel do InfinityFree.
2. Ative o certificado SSL gratuito e aguarde o HTTPS funcionar sem aviso no navegador.
3. Em **MySQL Databases**, crie um banco novo e anote host, nome completo do banco, usuário e senha. O host é o informado no painel, não `localhost` nem `db`.
4. Abra o phpMyAdmin pelo painel, selecione esse banco vazio e importe `schema.sql`.
5. No mesmo banco, importe `gestor.sql` da mesma pasta de saída.

Não importe o schema em banco com dados: ele não é uma migração e pode falhar em tabelas existentes. A hospedagem começa com os três sabores de demonstração; usuários e pedidos locais não são enviados.

## 3. Enviar e configurar os arquivos

Extraia `felito-htdocs.zip` no computador e envie seu conteúdo para o `htdocs` do domínio escolhido por FTP ou gerenciador de arquivos. O resultado deve ser:

```text
htdocs/
  .env
  .htaccess
  app/
  config/
  views/
  public/
    .htaccess
    index.php
    favicon.svg
    assets/style.css
```

Não crie uma pasta `htdocs/htdocs`. Remova a página de boas-vindas fornecida pelo provedor, se houver. Confira que o cliente FTP enviou os arquivos que começam com ponto.

Edite o `.env` enviado para a hospedagem e substitua os quatro valores de exemplo pelos do painel:

```dotenv
APP_ENV=production
APP_HTTPS_ONLY=true
DB_HOST=HOST_MYSQL_DO_PAINEL
DB_NAME=NOME_DO_BANCO_DO_PAINEL
DB_USER=USUARIO_DO_PAINEL
DB_PASS="SENHA_DO_BANCO_DO_PAINEL"
```

O leitor aceita valores sem aspas ou com aspas simples/duplas ao redor; não faz interpolação nem aceita comentário no fim do valor. `#`, `=` e espaços dentro da senha são preservados. Use uma configuração por linha. Variáveis reais do processo têm prioridade sobre o `.env`, inclusive valores vazios.

As regras encaminham URLs para `public` internamente e bloqueiam arquivos ocultos e diretórios privados. A aplicação exige HTTPS em produção, preservando o método de requisições com redirecionamento 308. Cookies recebem `Secure` em conexões HTTPS. Erros inesperados retornam uma mensagem genérica; detalhes são enviados ao log de erros do PHP. Não são confiados cabeçalhos de proxy enviados pelo visitante.

## 4. Conferir na conta real

- Abra `/`, `/carrinho`, `/entrar`, `/gestor/estoque` e uma URL inexistente. Confirme o layout e o favicon; rotas protegidas devem pedir login e a URL inexistente deve retornar 404.
- Acesse `/.env`, `/config/config.php`, `/app/bootstrap.php` e `/views/layout.php`: devem ser bloqueados (403/404), sem exibir conteúdo. Não publique arquivos `phpinfo()` ou instaladores.
- Cadastre um cliente, adicione um sabor e finalize um pedido. Confira persistência da sessão, total e baixa de estoque.
- Entre com a conta de `ACESSO-GESTOR.txt`; teste edição de produto, entrada/ajuste de estoque, sequência Recebido → Em preparo → Pronto para retirada → Entregue e cancelamento de outro pedido com devolução de estoque.
- Confira os horários de pedidos e movimentações: gravação em UTC e exibição em São Paulo. Teste também no celular.
- Confirme no navegador HTTPS sem aviso e cookie de sessão com `Secure`, `HttpOnly` e `SameSite=Lax`. Em HTTP deve haver redirecionamento para HTTPS.

As telas de cadastro, produtos e estoque exercitam mbstring; a listagem do catálogo e os pedidos exercitam PDO/MySQL. Falhas de extensão ou sessão exigem diagnóstico da conta pelo painel/suporte. A validação local não confirma as configurações ou cotas da conta real.

## Problemas comuns e recuperação

- **Erro 500 em todas as páginas:** confira os dois `.htaccess` e o log PHP/Apache no painel. Não envie `docker/apache.conf`: ele contém configuração de servidor, não regras para hospedagem compartilhada.
- **Falha ao conectar:** confira os quatro valores do banco no painel e o banco selecionado na importação. Não use as credenciais locais do Docker.
- **Página sem estilo ou rotas 404:** confira a estrutura do upload e os dois `.htaccess`, incluindo arquivos ocultos.
- **Loop de HTTPS:** confirme SSL ativo no domínio e a identificação de HTTPS pelo servidor; não desative a exigência de HTTPS no site público como solução permanente.
- **Gestor não entra:** confira se importou `gestor.sql` e está usando as credenciais da mesma pasta gerada. Executar o empacotamento novamente gera outra senha, sem alterar o banco existente.

Para atualizar o site, gere um pacote sem configuração, SQL ou novas credenciais:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\package-infinityfree.ps1 -UpdateOnly
```

Faça backup dos arquivos antes do envio. Substitua os arquivos de aplicação preservando o `.env` real. Não reimporte schema/gestor em atualizações. Em caso de falha, restaure a cópia anterior dos arquivos; os ajustes de hospedagem não alteram a estrutura do banco. Se uma atualização futura exigir migração, exporte o banco pelo phpMyAdmin antes de aplicá-la; `-UpdateOnly` apenas empacota arquivos e não executa migrações.

## Fontes do provedor

- [PHP, MySQL/MariaDB e SSL disponíveis](https://www.infinityfree.com/).
- [Raiz htdocs, reescrita para public e importação pelo phpMyAdmin](https://forum.infinityfree.com/t/how-to-install-a-laravel-site-on-infinityfree/118578).
- [Registro do suporte sobre putenv bloqueado](https://forum.infinityfree.com/t/no-putenv-on-hosting/28182). O projeto não depende dessa função.
