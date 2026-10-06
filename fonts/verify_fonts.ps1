# verify_fonts.ps1
Write-Host "=== Font file counts ===" -ForegroundColor Cyan
$dirs = @("inter","poppins","outfit","plus-jakarta-sans","fredoka")
foreach ($d in $dirs) {
    $p = "D:\AhmadLearninghub\questionpaper\fonts\$d"
    $c = (Get-ChildItem $p -Filter "*.woff2" -ErrorAction SilentlyContinue | Measure-Object).Count
    Write-Host "  $d : $c woff2 files"
}

Write-Host ""
Write-Host "=== Font Awesome files ===" -ForegroundColor Cyan
Get-ChildItem "D:\AhmadLearninghub\questionpaper\fonts\fontawesome" -Recurse -File | ForEach-Object {
    $rel = $_.FullName.Replace("D:\AhmadLearninghub\questionpaper\","")
    $kb = [math]::Round($_.Length / 1KB, 1)
    Write-Host "  $rel ($kb KB)"
}

Write-Host ""
Write-Host "=== Remaining active CDN references ===" -ForegroundColor Cyan

$phpCss = Get-ChildItem "D:\AhmadLearninghub\questionpaper" -Recurse -Include "*.php","*.css" -ErrorAction SilentlyContinue | Where-Object { $_.FullName -notmatch "vendor|node_modules|\.git" }

$googleHits = Select-String -Path $phpCss.FullName -Pattern "fonts\.googleapis\.com" -ErrorAction SilentlyContinue | Where-Object { $_.Line -notmatch "^\s*/?\*" -and $_.Line -notmatch "^\s*<!--" }
$faHits = Select-String -Path $phpCss.FullName -Pattern "cdnjs\.cloudflare\.com.*font-awesome" -ErrorAction SilentlyContinue

$gc = ($googleHits | Measure-Object).Count
$fc = ($faHits | Measure-Object).Count

Write-Host "  Active Google Fonts CDN refs: $gc"
Write-Host "  Active Font Awesome CDN refs: $fc"

if ($gc -gt 0) {
    Write-Host "  Remaining Google Fonts:" -ForegroundColor Yellow
    $googleHits | ForEach-Object { Write-Host "    $($_.Filename):$($_.LineNumber)" }
}
if ($fc -gt 0) {
    Write-Host "  Remaining FA CDN:" -ForegroundColor Yellow
    $faHits | ForEach-Object { Write-Host "    $($_.Filename):$($_.LineNumber)" }
}

Write-Host ""
Write-Host "=== local-fonts.css path verification ===" -ForegroundColor Cyan
$css = Get-Content "D:\AhmadLearninghub\questionpaper\css\local-fonts.css" -Raw
$matches = [regex]::Matches($css, "url\('\.\./([^']+)'\)")
$missingCount = 0
$totalCount = 0
foreach ($m in $matches) {
    $totalCount++
    $relPath = $m.Groups[1].Value
    $fullPath = Join-Path "D:\AhmadLearninghub\questionpaper" $relPath
    if (-not (Test-Path $fullPath)) {
        Write-Host "  [MISSING] $relPath" -ForegroundColor Red
        $missingCount++
    }
}
if ($missingCount -eq 0) {
    Write-Host "  All $totalCount referenced font files exist on disk!" -ForegroundColor Green
} else {
    Write-Host "  $missingCount of $totalCount font files missing!" -ForegroundColor Red
}

