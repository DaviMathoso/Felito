param(
    [switch]$UpdateOnly,
    [string]$AdminEmail = 'gestor@felito.example',
    [string]$AdminName = 'Gestor Felito'
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$outputRoot = Join-Path $projectRoot ('output/infinityfree-' + (Get-Date -Format 'yyyyMMdd-HHmmss-fff'))
$htdocs = Join-Path $outputRoot 'htdocs'
New-Item -ItemType Directory -Path $htdocs | Out-Null

# Lista explícita: nenhum dado ou segredo local entra no ZIP.
foreach ($directory in @('app', 'config', 'views', 'public')) {
    Copy-Item -LiteralPath (Join-Path $projectRoot $directory) -Destination $htdocs -Recurse
}
Copy-Item -LiteralPath (Join-Path $projectRoot '.htaccess') -Destination $htdocs
if (-not $UpdateOnly) {
    Copy-Item -LiteralPath (Join-Path $projectRoot '.env.infinityfree.example') -Destination (Join-Path $htdocs '.env')
    Copy-Item -LiteralPath (Join-Path $projectRoot 'database/schema.sql') -Destination $outputRoot
}
Copy-Item -LiteralPath (Join-Path $projectRoot 'docs/infinityfree.md') -Destination (Join-Path $outputRoot 'LEIA-ME.md')

if (-not $UpdateOnly) {
    $randomBytes = New-Object byte[] 18
    $randomGenerator = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try { $randomGenerator.GetBytes($randomBytes) } finally { $randomGenerator.Dispose() }
    $adminPassword = [Convert]::ToBase64String($randomBytes)
    $payload = @{ email = $AdminEmail; name = $AdminName; password = $adminPassword } | ConvertTo-Json -Compress
    $generatorPath = Join-Path $PSScriptRoot 'generate-admin-sql.php'
    $previousOutputEncoding = $OutputEncoding
    try {
        $OutputEncoding = New-Object System.Text.UTF8Encoding $false
        if (Get-Command php -ErrorAction SilentlyContinue) {
            $adminSql = $payload | & php $generatorPath
        } else {
            $adminSql = $payload | & docker run --rm -i --network none --read-only --entrypoint php --mount "type=bind,source=$generatorPath,target=/generate.php,readonly" felito-web /generate.php
        }
        if ($LASTEXITCODE -ne 0) { throw 'Falha ao gerar SQL do gestor. Instale PHP com mbstring ou execute docker compose build web.' }
    } finally { $OutputEncoding = $previousOutputEncoding }

    $utf8 = New-Object System.Text.UTF8Encoding $false
    [IO.File]::WriteAllText((Join-Path $outputRoot 'gestor.sql'), ($adminSql -join "`n") + "`n", $utf8)
    [IO.File]::WriteAllText((Join-Path $outputRoot 'ACESSO-GESTOR.txt'), "E-mail: $AdminEmail`nSenha: $adminPassword`n`nGuarde este arquivo localmente. Não envie para htdocs.`n", $utf8)

}

# Compress-Archive pode omitir arquivos ocultos; ZipFile inclui os .htaccess.
Add-Type -AssemblyName System.IO.Compression.FileSystem
Add-Type -AssemblyName System.IO.Compression
$zipPath = Join-Path $outputRoot 'felito-htdocs.zip'
$archive = [IO.Compression.ZipFile]::Open($zipPath, [IO.Compression.ZipArchiveMode]::Create)
try {
    foreach ($item in Get-ChildItem -LiteralPath $htdocs -File -Recurse -Force) {
        # .NET Framework no Windows usa barras invertidas por padrão; ZIP para
        # hospedagem Linux precisa de nomes com / para preservar os diretórios.
        $entryName = $item.FullName.Substring($htdocs.Length + 1).Replace('\', '/')
        [IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive, $item.FullName, $entryName) | Out-Null
    }
} finally { $archive.Dispose() }
Write-Output "Pacote: $zipPath"
if ($UpdateOnly) {
    Write-Output 'Atualizacao: sem .env, SQL ou novas credenciais. Preserve o .env existente e nao importe SQL no banco.'
} else {
    Write-Output "Banco: $(Join-Path $outputRoot 'schema.sql')"
    Write-Output "Gestor: $(Join-Path $outputRoot 'gestor.sql')"
    Write-Output "Credenciais locais: $(Join-Path $outputRoot 'ACESSO-GESTOR.txt')"
    Write-Output 'O ZIP contém um .env de exemplo. Preencha as credenciais do banco na hospedagem.'
}
