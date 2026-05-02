# Docker Complete Fresh Setup Script
# This script will completely clean and rebuild your Docker environment

Write-Host "============================================"
Write-Host "FRESH DOCKER SETUP - Ahmad Learning Hub"
Write-Host "============================================" -ForegroundColor Green

Write-Host "`n[1/7] Stopping all containers..."
docker compose --env-file config/.env down -v --remove-orphans
docker stop $(docker ps -aq) 2>$null | Out-Null
docker rm $(docker ps -aq) 2>$null | Out-Null

Write-Host "[2/7] Removing old images..."
docker rmi paper_app:latest 2>$null | Out-Null
docker system prune -af --volumes 2>$null | Out-Null

Write-Host "[3/7] Verifying cleanup..."
$containerCount = (docker ps -aq | Measure-Object).Count
$imageCount = (docker images | grep paper | Measure-Object).Count
Write-Host "Remaining containers: $containerCount"
Write-Host "Remaining paper images: $imageCount"

Write-Host "`n[4/7] Building fresh image (no cache)..."
docker compose --env-file config/.env build --no-cache

Write-Host "`n[5/7] Starting all services..."
docker compose --env-file config/.env up -d

Write-Host "`n[6/7] Waiting for services to initialize..." 
Start-Sleep -Seconds 10

Write-Host "`n[7/7] Final status check..."
Write-Host "`nContainer Status:" -ForegroundColor Cyan
docker compose --env-file config/.env ps

Write-Host "`nApplication Logs:" -ForegroundColor Cyan
docker logs --tail 30 paper_app

Write-Host "`n============================================"
Write-Host "SETUP COMPLETE" -ForegroundColor Green
Write-Host "============================================"
Write-Host "`nAccess your application at:"
Write-Host "  - App: http://localhost:8001" -ForegroundColor Yellow
Write-Host "  - phpMyAdmin: http://localhost:8080" -ForegroundColor Yellow
Write-Host "  - MailHog: http://localhost:8025" -ForegroundColor Yellow
Write-Host "`nIf app is still not accessible, run:" -ForegroundColor Cyan
Write-Host "  docker logs --tail 50 paper_app" -ForegroundColor Gray
