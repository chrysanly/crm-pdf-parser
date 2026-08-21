<#
    Starts the resume-parsing sidecar (FastAPI, port 8001).

    Creates sidecar/.venv and installs requirements.txt on first run, then runs
    uvicorn with --reload. Safe to re-run: an existing venv is reused, and
    requirements are only reinstalled when requirements.txt is newer than the
    stamp written by the last install.

    Usage:  powershell -ExecutionPolicy Bypass -File scripts/sidecar.ps1 [-Port 8001]
            powershell -ExecutionPolicy Bypass -File scripts/sidecar.ps1 -Test
    Or:     double-click scripts/start-sidecar.bat
#>
param(
    [int]$Port = 8001,
    [switch]$NoReload,
    [switch]$Test
)

$ErrorActionPreference = 'Stop'

$sidecar = Join-Path (Split-Path -Parent $PSScriptRoot) 'sidecar'
$venv    = Join-Path $sidecar '.venv'
$python  = Join-Path $venv 'Scripts\python.exe'
$reqs    = Join-Path $sidecar 'requirements.txt'
$stamp   = Join-Path $venv '.requirements-stamp'

if (-not (Test-Path $reqs)) {
    Write-Error "No requirements.txt at $reqs - is this the crm-pdf-parser repo?"
}

if (-not (Test-Path $python)) {
    Write-Host '==> Creating virtualenv (first run)...' -ForegroundColor Cyan
    $launcher = Get-Command py -ErrorAction SilentlyContinue
    if ($launcher) { & py -3.13 -m venv $venv } else { & python -m venv $venv }
}

$needsInstall = -not (Test-Path $stamp)
if (-not $needsInstall) {
    $needsInstall = (Get-Item $reqs).LastWriteTimeUtc -gt (Get-Item $stamp).LastWriteTimeUtc
}

if ($needsInstall) {
    Write-Host '==> Installing sidecar requirements...' -ForegroundColor Cyan
    & $python -m pip install --upgrade pip --quiet
    & $python -m pip install -r $reqs
    if ($LASTEXITCODE -ne 0) { Write-Error 'pip install failed.' }
    Set-Content -Path $stamp -Value 'ok' -Encoding utf8
}

if ($Test) {
    # pytest resolves the `app` package relative to the working directory.
    Push-Location $sidecar
    try { & $python -m pytest -q } finally { Pop-Location }
    exit $LASTEXITCODE
}

# Windows lets a second process bind a port that is already being listened on,
# so an orphaned worker from an earlier run keeps answering requests with STALE
# code while the new server looks perfectly healthy. This has cost real debugging
# time twice; clear the port before binding it.
$stale = Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue |
    Select-Object -ExpandProperty OwningProcess -Unique

foreach ($processId in $stale) {
    $process = Get-Process -Id $processId -ErrorAction SilentlyContinue

    if ($null -ne $process) {
        Write-Host "==> Stopping stale listener on port $Port (PID $processId)" -ForegroundColor Yellow
        Stop-Process -Id $processId -Force -ErrorAction SilentlyContinue
    }
}

if ($stale) { Start-Sleep -Milliseconds 400 }

$uvicornArgs = @('-m', 'uvicorn', 'app.main:app', '--port', $Port)
if (-not $NoReload) { $uvicornArgs += '--reload' }

Write-Host "==> Sidecar on http://127.0.0.1:$Port  (health: /health, Ctrl+C to stop)" -ForegroundColor Green
Push-Location $sidecar
try { & $python @uvicornArgs } finally { Pop-Location }
