# Felito — esqueleto do projeto (PHP + MySQL)

Requer PHP 8.1+. Rodar local:

    cp .env.example .env        # preencha o banco
    mysql -u root -p felito < database/schema.sql
    php -S localhost:8000 -t public

## Como o código é organizado

    public/index.php      porta de entrada + tabela de rotas
    app/Controllers/      "garçom": recebe a requisição e escolhe a tela
    app/Services/         "cozinha": as REGRAS (reservar, Pix, estoque)
    app/Core/Database.php conexão única com o MySQL
    views/                HTML das telas
    database/schema.sql   tabelas
    config/               leitura do .env

Fluxo:  Rota -> Controller -> Service -> Banco  (e volta para a View)

## Onde cada módulo mexe (1 módulo = 1 cartão no Trello)

| Módulo | Controller | Service | RFs |
|---|---|---|---|
| Catálogo / Pedido | CatalogController, OrderController | — | RF01–RF04 |
| Cadastro / LGPD | AuthController | — | RF05–RF06, RNF08–RNF12 |
| Estoque | (painel) | StockService | RF07–RF08, RF14–RF15 |
| Reserva / Cupom / Fidelidade | OrderController | OrderService | RF09–RF13 |
| Pagamento Pix | OrderController, webhook | PaymentService | RF16–RF18 |

## Combinados do time

- Regra de negócio só em `Services/`. Controller não calcula nada.
- Sempre usar `e()` ao mostrar texto do usuário na view e `prepare()` nas consultas SQL.
- Nunca commitar o `.env`.
- Cron na Hostinger usa UTC (Brasília = UTC−3).
