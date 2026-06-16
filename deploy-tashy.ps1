param(
    [string]$KeyPath,                       # OpenSSH private key for key auth
    [string]$Password,                      # SFTP password (or set $env:TASHY_SFTP_PASS)
    [string]$User      = "a1229881",        # root chroot (/) user — can reach /shan
    [string]$Server    = "access-5020587559.webspace-host.com",
    [int]   $Port      = 22,
    [string]$RemoteDir = "/shan",           # tashykollections.online lives here
    [switch]$Mirror                          # add -Mirror to also DELETE remote files missing locally
)
# ============================================================
#  Tashy Kollections - Deploy to  tashykollections.online
#  .online is served from the  /shan  folder of the SAME IONOS webspace
#  that serves tashykollections.com (root /). Verified 2026-06-16 map:
#       /       -> tashykollections.com     (DB dbs15760212)
#       /shan   -> tashykollections.online  (DB dbs15760212, shared)
#       /tashy  -> tashykollections.org      (DB dbs15785127) -- do NOT deploy here
#  Reach /shan with the ROOT chroot user (a1229881). The a1645260 user is
#  chrooted to /tashy (=.org) and CANNOT update .online.
#
#  SAFETY: config/db.php is excluded (each folder has its own correct
#  SITE_URL/DB) and remote files are NOT deleted unless you pass -Mirror
#  (so admin-uploaded product images in assets/images are preserved).
#
#  AUTH - two supported methods (key is what .com's GitHub deploy uses):
#    1) SSH KEY (recommended):
#         .\deploy-tashy.ps1                       # uses ~/.ssh/id_ed25519
#         .\deploy-tashy.ps1 -KeyPath "C:\path\to\key"
#       The matching PUBLIC key must be registered for this webspace's SSH
#       user in the IONOS panel (Hosting -> SSH access). This is the SAME key
#       stored as the GitHub secret IONOS_SSH_KEY that deploys .com.
#    2) PASSWORD (only if SFTP password auth is enabled for the user):
#         .\deploy-tashy.ps1 -Password "yourSftpPassword"
#         $env:TASHY_SFTP_PASS = "yourSftpPassword"; .\deploy-tashy.ps1
#       NOTE: the IONOS *DB* password is NOT the SFTP password.
#
#  Only writes inside httpdocs/tashy. config/db.php IS uploaded (the site
#  needs it); dev tooling (.git, *.ps1, etc.) is excluded.
# ============================================================

$ErrorActionPreference = "Stop"

# -- Resolve auth method --------------------------------------
$pass = if ($Password) { $Password } elseif ($env:TASHY_SFTP_PASS) { $env:TASHY_SFTP_PASS } else { $null }
$key  = if ($KeyPath) { $KeyPath } else { Join-Path $env:USERPROFILE ".ssh\id_ed25519" }

# Prefer key auth unless a password was explicitly supplied (and no -KeyPath given).
if ($KeyPath -or (-not $pass -and (Test-Path $key))) {
    $authMode = "key"
} elseif ($pass) {
    $authMode = "password"
} else {
    Write-Host "No credentials found." -ForegroundColor Red
    Write-Host "Provide ONE of:" -ForegroundColor Yellow
    Write-Host "  * An SSH key at $key  (or pass -KeyPath ...)  - recommended" -ForegroundColor Gray
    Write-Host "  * A password:  .\deploy-tashy.ps1 -Password '...'  (or `$env:TASHY_SFTP_PASS)" -ForegroundColor Gray
    Write-Host ""
    Write-Host "Tip: the key that deploys .com is stored in GitHub as IONOS_SSH_KEY." -ForegroundColor Gray
    Write-Host "     Save that private key to $key and register its .pub in the IONOS panel." -ForegroundColor Gray
    exit 1
}

Write-Host ""
Write-Host "=== Deploy -> tashykollections.online/tashy ===" -ForegroundColor Cyan
Write-Host "Host  : $Server" -ForegroundColor Gray
Write-Host "User  : $User" -ForegroundColor Gray
Write-Host "Remote: $RemoteDir" -ForegroundColor Gray
Write-Host "Auth  : $authMode" -ForegroundColor Gray
Write-Host ""

# -- Require WinSCP (clean, filtered sync) --------------------
$winscpPath = "C:\Program Files (x86)\WinSCP\WinSCP.com"
if (-not (Test-Path $winscpPath)) { $winscpPath = "C:\Program Files\WinSCP\WinSCP.com" }
if (-not (Test-Path $winscpPath)) {
    Write-Host "WinSCP not found. Install it from https://winscp.net then re-run." -ForegroundColor Red
    exit 1
}

# -- Build the WinSCP 'open' line for the chosen auth method --
if ($authMode -eq "key") {
    if (-not (Test-Path $key)) {
        Write-Host "SSH key not found: $key" -ForegroundColor Red
        Write-Host "Pass one with:  .\deploy-tashy.ps1 -KeyPath `"C:\path\to\key`"" -ForegroundColor Yellow
        exit 1
    }
    # WinSCP scripting needs a PuTTY .ppk - convert the OpenSSH key once.
    $ppkPath = "$key.ppk"
    if (-not (Test-Path $ppkPath)) {
        Write-Host "Converting key to PuTTY format (.ppk) for WinSCP..." -ForegroundColor Gray
        & "$winscpPath" /keygen "$key" /output="$ppkPath" 2>&1 | Out-String | Write-Host
        if (-not (Test-Path $ppkPath)) {
            Write-Host "Key conversion failed. If the key has a passphrase, convert it with PuTTYgen first." -ForegroundColor Red
            exit 1
        }
    }
    $openLine = "open sftp://${User}@${Server}:${Port}/ -privatekey=`"$ppkPath`" -hostkey=*"
} else {
    # URL-encode the password so special characters are safe in the URL.
    $encPass = [uri]::EscapeDataString($pass)
    $openLine = "open sftp://${User}:${encPass}@${Server}:${Port}/ -hostkey=*"
}

Write-Host "Uploading via WinSCP (SFTP)..." -ForegroundColor Cyan

# Exclude dev tooling, VCS, AND config/ (each folder keeps its own db.php).
$mask = "| config/; .git/; .github/; .deploy-now/; _localdev/; .claude/; *.ps1; *.ppk; deploy.log; .gitignore"

# Upload-only by default; -Mirror enables -delete (removes remote files
# missing locally — caution: also deletes admin-uploaded images).
$deleteFlag = if ($Mirror) { "-delete " } else { "" }

$winscpScript = @"
$openLine
option batch abort
option confirm off
synchronize remote $deleteFlag-criteria=size -filemask="$mask" "." "$RemoteDir"
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
    Write-Host ""
    Write-Host "Upload failed (exit $code). See deploy.log." -ForegroundColor Red
    Write-Host "Most common causes:" -ForegroundColor Yellow
    if ($authMode -eq "password") {
        Write-Host "  * 'Access denied' -> the SFTP password is wrong, OR password auth is" -ForegroundColor Gray
        Write-Host "    disabled for this user. Use key auth instead (recommended):" -ForegroundColor Gray
        Write-Host "    save the IONOS_SSH_KEY private key to $key and re-run." -ForegroundColor Gray
    } else {
        Write-Host "  * 'Access denied' -> this key's PUBLIC half isn't registered for $User" -ForegroundColor Gray
        Write-Host "    in the IONOS panel (Hosting -> SSH access). Register it, then re-run." -ForegroundColor Gray
        Write-Host "  * Wrong key file -> pass the right one with -KeyPath." -ForegroundColor Gray
    }
    exit $code
}
