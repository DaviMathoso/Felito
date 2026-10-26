# Validação da hospedagem em ambiente local

O Compose deste diretório reproduz `htdocs` como raiz pública, `.htaccess`, HTTPS e `putenv()` desabilitado. O MariaDB é separado do ambiente normal, usa outro fuso horário e mantém dados apenas em tmpfs. Não é uma conta real do InfinityFree.

Requer Docker, Node.js e um pacote gerado por `scripts/package-infinityfree.ps1`. Na raiz do projeto, defina `$packageRoot` como o caminho absoluto da pasta desse pacote e execute no PowerShell:

```powershell
$checkRoot = Join-Path (Get-Location) 'output/hosting-check'
New-Item -ItemType Directory -Path $checkRoot -Force | Out-Null
$checkHtdocs = Join-Path $checkRoot 'htdocs'
Expand-Archive -LiteralPath (Join-Path $packageRoot 'felito-htdocs.zip') -DestinationPath $checkHtdocs
$utf8 = New-Object System.Text.UTF8Encoding $false
[IO.File]::WriteAllText((Join-Path $checkHtdocs '.env'), "APP_ENV=production`nAPP_HTTPS_ONLY=true`nDB_HOST=db`nDB_NAME=felito_hosting`nDB_USER=hosting`nDB_PASS=hosting_test_only`n", $utf8)
$composeEnv = 'FELITO_HOSTING_HTDOCS=' + $checkHtdocs.Replace('\','/') + "`nFELITO_HOSTING_ADMIN_SQL=" + (Join-Path $packageRoot 'gestor.sql').Replace('\','/') + "`n"
[IO.File]::WriteAllText((Join-Path $checkRoot 'compose.env'), $composeEnv, $utf8)
docker compose --env-file output/hosting-check/compose.env -p felito-hosting-check -f tests/hosting/compose.yaml up -d --wait
node tests/hosting/smoke.mjs (Join-Path $packageRoot 'ACESSO-GESTOR.txt')
docker cp tests/hosting/config.php felito-hosting-check-web-1:/tmp/felito-config-check.php
docker compose --env-file output/hosting-check/compose.env -p felito-hosting-check -f tests/hosting/compose.yaml exec -T -e DB_HOST=processo web php /tmp/felito-config-check.php
```

O teste HTTP cobre regras de acesso, sessões, cookies, CSRF, HTTPS 308, parâmetros de URL, cadastro/login, permissões, edição de produto, pedido, baixa/devolução/ajuste de estoque, status, horários e erro de banco com mensagem genérica. Altera somente dados do banco descartável e restaura o `.env` da cópia após a falha controlada.

Abra `https://localhost:8443` para testes manuais. O certificado é autoassinado, exclusivo do teste; seu aviso é esperado. Em produção use o certificado válido do provedor.

O smoke também valida reserva de cinco dias e expiração/restauração, consentimento, exportação/solicitações de dados, pontos e resgates, fechamento mensal e alertas persistentes. Cadastro, login e validação de pedido precisam responder abaixo de 3 segundos no ambiente de teste; essa medição não substitui monitoramento do provedor em produção.

Ao terminar, pare somente este ambiente:

```powershell
docker compose --env-file output/hosting-check/compose.env -p felito-hosting-check -f tests/hosting/compose.yaml down
```

O `down` descarta o banco de testes em memória. Antes de repetir `Expand-Archive`, use outra pasta de trabalho ou mova a cópia anterior de `htdocs`. Nunca aponte este teste para a pasta de um site publicado nem use credenciais reais.
