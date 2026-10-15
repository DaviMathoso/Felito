# Felito — sorvetes artesanais sob encomenda

Loja local de sorvetes com catálogo, carrinho, cadastro de clientes e painel do gestor. A aplicação usa PHP 8.4 e MariaDB 11.4; todo o ambiente roda em Docker.

## Iniciar

Requer Docker Desktop iniciado. Opcionalmente, copie `.env.example` para `.env` e altere a senha local do gestor. Depois, no PowerShell, execute:

```powershell
docker compose up -d --build --wait
```

- Loja: http://localhost:8080
- Painel do gestor: http://localhost:8080/gestor
- Gestor local inicial: `gestor@felito.local` / `FelitoLocal123!`

O gestor é criado uma vez no primeiro início. Se `.env` definir `FELITO_ADMIN_PASSWORD`, use essa senha. A senha configurada na primeira execução fica no banco e não é redefinida nos próximos reinícios.

O catálogo começa com três sabores e estoque de demonstração. A aplicação fica publicada somente em `127.0.0.1`; o MariaDB não publica uma porta no computador.

Se a porta 8080 estiver ocupada, defina outra antes de iniciar:

```powershell
$env:FELITO_PORT = "8081"
docker compose up -d --build --wait
```

## Usar a loja

Escolha os sabores, ajuste as quantidades no carrinho e informe uma data de retirada ao finalizar. O cadastro de cliente é exigido nessa etapa. A encomenda aparece em **Meus pedidos** e o telefone cadastrado é usado para confirmar a retirada. O pagamento é combinado diretamente com a equipe; a aplicação não processa cobranças.

No painel do gestor, atualize cada pedido na ordem Recebido → Em preparo → Pronto para retirada → Entregue. Também é possível cancelar um pedido ainda não entregue; o estoque retorna automaticamente. Em **Produtos**, cadastre e edite sabores. Em **Estoque**, registre entradas ou ajustes para um saldo contado; movimentações e baixas de venda ficam no histórico.

## Gerenciar o ambiente

```powershell
docker compose ps
docker compose logs -f web db
docker compose down
```

`docker compose down` remove os containers e preserva os dados no volume `mariadb_data`. O Compose anterior usava `mysql_data`; ele permanece intacto e não é montado no MariaDB. O schema e os sabores de demonstração são inicializados no primeiro uso do novo volume. Alterar `database/schema.sql` depois da inicialização não recria as tabelas.

Para recriar o banco de desenvolvimento, pare a aplicação e remova somente o volume novo:

```powershell
docker compose down
docker volume ls --filter name=mariadb_data
docker volume rm felito_mariadb_data
docker compose up -d --build --wait
```

Antes de remover o volume, confirme o nome exibido por `docker volume ls`; dados locais de usuários, encomendas e estoque serão apagados junto com ele.

## Organização do código

```text
public/                  rotas e arquivos públicos
app/Controllers/          requisições de loja e painel
app/Services/             regras de pedidos, produtos e estoque
app/Core/Database.php     conexão PDO com MariaDB
views/                    páginas renderizadas no servidor
public/assets/style.css   estilo da loja e do painel
database/schema.sql       tabelas e sabores iniciais
docker/                   inicialização do gestor
compose.yaml              serviços PHP/Apache e MariaDB
```

`database/schema.sql` é o schema ativo da aplicação. Os artefatos SQL antigos do protótipo foram mantidos como referências locais e não são importados pelo Compose.

## Hospedagem gratuita no InfinityFree

Execute `powershell -ExecutionPolicy Bypass -File .\scripts\package-infinityfree.ps1` para gerar o ZIP de upload, o schema e o SQL de criação do gestor em uma pasta nova dentro de `output/`. A senha administrativa aleatória fica em um arquivo local separado do ZIP.

Siga [o guia de publicação](docs/infinityfree.md) para criar o banco, ativar HTTPS, enviar os arquivos e configurar o `.env` com os dados do painel. O pacote usa `.htaccess` para manter as URLs atuais, proteger arquivos privados e funcionar dentro de `htdocs`. A rota de referência do protótipo continua exclusiva do Docker local.
