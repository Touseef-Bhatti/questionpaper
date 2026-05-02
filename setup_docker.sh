#!/bin/bash
# Docker Fresh Setup Script
cd /d/AhmadLearninghub/questionpaper

echo "=== STEP 1: Complete cleanup ==="
docker compose --env-file config/.env down -v --remove-orphans 2>&1 | tee cleanup1.log
docker stop $(docker ps -aq 2>/dev/null) 2>/dev/null || true
docker rm $(docker ps -aq 2>/dev/null) 2>/dev/null || true
docker rmi paper_app:latest 2>/dev/null || true
docker system prune -f 2>&1 | tee cleanup2.log

echo "=== STEP 2: Verify cleanup ==="
docker ps -a 2>&1 | tee docker_ps_before.log
docker images | grep paper 2>&1 | tee docker_images_before.log

echo "=== STEP 3: Build fresh image ==="
docker compose --env-file config/.env build --no-cache 2>&1 | tee docker_build.log

echo "=== STEP 4: Start all services ==="
docker compose --env-file config/.env up -d 2>&1 | tee docker_up.log

echo "=== STEP 5: Wait for startup ==="
sleep 10

echo "=== STEP 6: Check container status ==="
docker compose --env-file config/.env ps 2>&1 | tee docker_ps_after.log

echo "=== STEP 7: Check app logs ==="
docker logs --tail 50 paper_app 2>&1 | tee app_logs.log

echo "=== STEP 8: Check port availability ==="
netstat -tlnp 2>/dev/null | grep 8001 2>&1 | tee ports_check.log

echo "=== SETUP COMPLETE ==="
