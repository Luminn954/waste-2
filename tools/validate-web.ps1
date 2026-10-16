param(
    [switch]$CheckApi
)

$ErrorActionPreference = 'Stop'
$webRoot = Split-Path -Parent $PSScriptRoot
$backendRoot = Join-Path $webRoot 'backend-php'
$php = Get-Command php -ErrorAction SilentlyContinue
$phpExe = if ($php) { $php.Source } elseif (Test-Path 'C:\xampp\php\php.exe') { 'C:\xampp\php\php.exe' } else { $null }

if (-not $phpExe) {
    throw 'PHP tidak ditemukan. Tambahkan PHP ke PATH atau pasang XAMPP di C:\xampp.'
}

$phpFiles = Get-ChildItem -LiteralPath $backendRoot -Filter '*.php' -File -Recurse
foreach ($file in $phpFiles) {
    & $phpExe -l $file.FullName
    if ($LASTEXITCODE -ne 0) {
        throw "Sintaks PHP gagal: $($file.FullName)"
    }
}

$node = Get-Command node -ErrorAction SilentlyContinue
if (-not $node) {
    throw 'Node.js tidak ditemukan; diperlukan hanya untuk pemeriksaan sintaks JavaScript.'
}

$frontendScript = Join-Path $webRoot 'frontend\js\app.js'
& $node.Source --check $frontendScript
if ($LASTEXITCODE -ne 0) {
    throw "Sintaks JavaScript gagal: $frontendScript"
}

Write-Host "Lolos: $($phpFiles.Count) file PHP dan JavaScript frontend."

if ($CheckApi) {
    $healthUrl = 'http://localhost:8080/Waste2/backend-php/index.php/health'
    $health = Invoke-RestMethod -Uri $healthUrl -Method Get -TimeoutSec 10
    if ($health.status -ne 'ok' -or $health.database -ne 'connected') {
        throw "Health API gagal: $($health | ConvertTo-Json -Compress)"
    }
    Write-Host "Lolos: API dan database merespons di $healthUrl."
}
