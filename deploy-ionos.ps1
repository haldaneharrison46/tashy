# ============================================================
#  Tashy Kollections — IONOS Deployment Script
#  Uploads all PHP site files to tashykollection.org via SFTP
#
#  BEFORE RUNNING:
#  1. Fill in $SSH_HOST below (from IONOS panel → Hosting → FTP & SSH)
#  2. Open PowerShell and run:
#       cd "F:\tashy\tashy"
#       .\deploy-ionos.ps1
# ============================================================

# ── YOUR IONOS CREDENTIALS ────────────────────────────────────
$SSH_HOST  = "access-5020587559.webspace-host.com"
$SSH_USER  = "a1645260"
$SSH_PASS  = "Shanshan123$"
$SSH_PORT  = 22
$REMOTE_DIR = "/var/www/vhosts/tashykollection.org/httpdocs"

Write-Host ""
Write-Host "=== Tashy Kollections — IONOS Deploy ===" -ForegroundColor Cyan
Write-Host "Host : $SSH_HOST" -ForegroundColor Gray
Write-Host "User : $SSH_USER" -ForegroundColor Gray
Write-Host "Remote: $REMOTE_DIR" -ForegroundColor Gray
Write-Host ""

if ($SSH_HOST -eq "YOUR_SSH_HOST_HERE") {
    Write-Host "ERROR: Please set the SSH_HOST variable at the top of this script." -ForegroundColor Red
    Write-Host "Find it in: IONOS panel → Hosting → FTP & SSH → SSH Access" -ForegroundColor Yellow
    exit 1
}

# ── Check for WinSCP ─────────────────────────────────────────
$winscpPath = "C:\Program Files (x86)\WinSCP\WinSCP.com"
if (-not (Test-Path $winscpPath)) {
    $winscpPath = "C:\Program Files\WinSCP\WinSCP.com"
}

if (Test-Path $winscpPath) {
    Write-Host "Using WinSCP for SFTP upload..." -ForegroundColor Cyan

    # Build WinSCP script
    $winscpScript = @"
open sftp://${SSH_USER}:${SSH_PASS}@${SSH_HOST}:${SSH_PORT}/ -hostkey=*
option batch abort
option confirm off
synchronize remote -delete "." "$REMOTE_DIR" -criteria=size
close
exit
"@
    $scriptFile = [System.IO.Path]::GetTempFileName() + ".txt"
    $winscpScript | Out-File -FilePath $scriptFile -Encoding ascii

    & "$winscpPath" /script="$scriptFile" /log="deploy.log"
    Remove-Item $scriptFile -Force

    if ($LASTEXITCODE -eq 0) {
        Write-Host ""
        Write-Host "====================================" -ForegroundColor Green
        Write-Host " Upload complete! Site is live at:" -ForegroundColor Green
        Write-Host " https://tashykollection.org" -ForegroundColor Cyan
        Write-Host "====================================" -ForegroundColor Green
    } else {
        Write-Host "Upload failed. Check deploy.log for details." -ForegroundColor Red
    }
    exit
}

# ── Fallback: built-in Windows SSH + scp ─────────────────────
Write-Host "WinSCP not found. Trying built-in Windows scp..." -ForegroundColor Yellow
Write-Host "(Install WinSCP from https://winscp.net for a better experience)" -ForegroundColor Gray
Write-Host ""

# Use Windows scp for each file
$localRoot  = Split-Path -Parent $MyInvocation.MyCommand.Path
$allFiles   = Get-ChildItem -Path $localRoot -Recurse -File |
              Where-Object { $_.FullName -notmatch '\\\.git\\' -and
                             $_.Name -ne 'deploy-ionos.ps1' -and
                             $_.Name -ne 'setup-github.ps1' -and
                             $_.Name -ne 'deploy.log' }

Write-Host "Files to upload: $($allFiles.Count)"
$env:SSHPASS = $SSH_PASS

$uploaded = 0
foreach ($file in $allFiles) {
    $rel     = $file.FullName.Substring($localRoot.Length).TrimStart('\').Replace('\','/')
    $remPath = "$REMOTE_DIR/$rel"
    $remDir  = ($remPath -split '/')[0..($remPath.Split('/').Count - 2)] -join '/'

    # Create remote dir (best effort)
    echo "y" | ssh -o "StrictHostKeyChecking=no" -p $SSH_PORT "${SSH_USER}@${SSH_HOST}" "mkdir -p '$remDir'" 2>$null

    scp -o "StrictHostKeyChecking=no" -P $SSH_PORT $file.FullName "${SSH_USER}@${SSH_HOST}:${remPath}"
    $uploaded++
    Write-Progress -Activity "Uploading" -Status "$rel" -PercentComplete (($uploaded / $allFiles.Count) * 100)
}

Write-Host ""
Write-Host "Uploaded $uploaded files." -ForegroundColor Green
Write-Host "Site should be live at https://tashykollection.org" -ForegroundColor Cyan
Write-Host ""
Write-Host "REMEMBER: Also upload config/db.php if not already on server." -ForegroundColor Yellow
