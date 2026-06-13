# ============================================================
#  Tashy Kollections — Deploy to  tashykollections.online/tashy
#  Uploads the site into the  /tashy  sub-folder of the SAME IONOS
#  webspace (httpdocs/tashy), so it is reachable at:
#       https://tashykollections.online/tashy
#
#  This only writes inside httpdocs/tashy — it never touches the
#  root site. config/db.php (which sets SITE_URL to the .online/tashy
#  address) IS uploaded; dev tooling (.git, *.ps1, etc.) is excluded.
#
#  RUN:
#       cd "F:\tashy\tashy"
#       .\deploy-tashy.ps1
#
#  ⚠️  CONFIRM these two values match the webspace that serves
#      tashykollections.online (IONOS panel → Hosting → FTP & SSH).
#      If .online lives on a different webspace than the one below,
#      update $SSH_HOST / $SSH_USER / $REMOTE_DIR accordingly.
# ============================================================

# ── IONOS credentials (same webspace as the main site) ───────
$SSH_HOST   = "access-5020587559.webspace-host.com"
$SSH_USER   = "a1645260"
$SSH_PASS   = "Shanshan123$"
$SSH_PORT   = 22
# Web root of the webspace + the /tashy sub-folder:
$REMOTE_DIR = "/var/www/vhosts/tashykollections.com/httpdocs/tashy"

Write-Host ""
Write-Host "=== Deploy → tashykollections.online/tashy ===" -ForegroundColor Cyan
Write-Host "Host  : $SSH_HOST" -ForegroundColor Gray
Write-Host "Remote: $REMOTE_DIR" -ForegroundColor Gray
Write-Host ""

# ── Require WinSCP (clean, filtered sync) ────────────────────
$winscpPath = "C:\Program Files (x86)\WinSCP\WinSCP.com"
if (-not (Test-Path $winscpPath)) { $winscpPath = "C:\Program Files\WinSCP\WinSCP.com" }
if (-not (Test-Path $winscpPath)) {
    Write-Host "WinSCP not found. Install it from https://winscp.net then re-run." -ForegroundColor Red
    exit 1
}

Write-Host "Uploading via WinSCP (SFTP)…" -ForegroundColor Cyan

# Exclude dev tooling and VCS from the web-served folder.
# (config/ is intentionally NOT excluded — the site needs config/db.php.)
$mask = "| .git/; .github/; .deploy-now/; _localdev/; .claude/; *.ps1; deploy.log; .gitignore"

$winscpScript = @"
open sftp://${SSH_USER}:${SSH_PASS}@${SSH_HOST}:${SSH_PORT}/ -hostkey=*
option batch abort
option confirm off
synchronize remote -delete -criteria=size -filemask="$mask" "." "$REMOTE_DIR"
close
exit
"@

$scriptFile = [System.IO.Path]::GetTempFileName() + ".txt"
$winscpScript | Out-File -FilePath $scriptFile -Encoding ascii

& "$winscpPath" /script="$scriptFile" /log="deploy.log"
$code = $LASTEXITCODE
Remove-Item $scriptFile -Force

if ($code -eq 0) {
    Write-Host ""
    Write-Host "====================================" -ForegroundColor Green
    Write-Host " Done. Live at:" -ForegroundColor Green
    Write-Host " https://tashykollections.online/tashy" -ForegroundColor Cyan
    Write-Host "====================================" -ForegroundColor Green
} else {
    Write-Host "Upload failed (exit $code). Check deploy.log." -ForegroundColor Red
}
