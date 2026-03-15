# Run Docker Compose using config/.env.local for variable substitution.
# Usage: .\docker-up.ps1   or   .\docker-up.ps1 -Build
param([switch]$Build)
$envFile = Join-Path $PSScriptRoot "config\.env.local"
if (-not (Test-Path $envFile)) {
    Write-Error "Config file not found: $envFile"
    exit 1
}
if ($Build) {
    docker compose --env-file $envFile up -d --build
} else {
    docker compose --env-file $envFile up -d
}
