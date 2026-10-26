# Operação de dados, disponibilidade e incidentes

## Proteção dos dados

- A aplicação exige HTTPS em produção, marca o cookie como `Secure`, `HttpOnly` e `SameSite=Lax`, usa consultas preparadas e armazena senhas com `password_hash()`.
- O banco grava timestamps em UTC; telas convertem os horários para `America/Sao_Paulo`.
- Antes de publicar, confirme com o provedor que volumes e backups do banco são criptografados em repouso e que o tráfego PHP–MariaDB é protegido. Essa capacidade depende do ambiente e não é inferida pelo código.
- Não habilite logs de payloads de formulários, senhas, tokens ou dados de cartão. O Felito não integra pagamento online.
- Revise os documentos `Termos de Uso` e `Política de Privacidade` com o responsável pela loja antes de coletar novos consentimentos.

## Atendimento a titulares

O titular autenticado pode corrigir nome, e-mail e telefone, exportar os próprios dados em JSON e registrar solicitações de acesso, correção, exclusão e portabilidade. O gestor acompanha a fila em `/gestor/privacidade`. Solicitações de exclusão não acionam remoção automática; os registros de pedido permanecem preservados até que a política de retenção e as exceções operacionais sejam definidas.

## Alertas e manutenção

Alertas de estoque baixo são persistidos e exibidos no painel do gestor até que o saldo ultrapasse o mínimo ou o produto seja ocultado. O sistema sincroniza alertas e vence reservas na primeira requisição após o limite de cinco dias. Como a hospedagem compartilhada não oferece cron, não há execução em horário exato quando o site não recebe tráfego. Ambientes com scheduler podem executar `php scripts/maintenance.php` periodicamente; no Docker, `docker compose exec -T web php /var/www/html/scripts/maintenance.php`.

## Incidentes de segurança

1. Registre data/hora, serviço afetado, quem identificou e os sintomas, sem copiar segredos ou dados pessoais desnecessários.
2. Avise o responsável pelo sistema e preserve logs técnicos e evidências de forma restrita.
3. Contenha o acesso afetado (por exemplo, revogue credenciais comprometidas ou suspenda a conta vulnerável) sem apagar evidências.
4. Determine, com o responsável, quais dados e titulares podem ter sido afetados, o período e os sistemas envolvidos.
5. Registre a decisão, as ações e os responsáveis; o responsável avalia as comunicações externas aplicáveis e acompanha a correção antes de encerrar o incidente.

## Disponibilidade e desempenho

O Compose local verifica a saúde do Apache e do MariaDB; isso não representa uma meta de disponibilidade em produção. Antes de anunciar uma meta de 99%, monitore o domínio real, confirme backup e recuperação e documente os limites do provedor. Meça cadastro, login e criação de pedido sob carga representativa; investigue qualquer resposta acima de três segundos. Hospedagem compartilhada não garante capacidade para picos nem redundância.
