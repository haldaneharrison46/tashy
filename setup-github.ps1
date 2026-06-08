# ============================================================
#  Tashy Kollections — GitHub Setup Script
#  Run from PowerShell inside the project folder:
#    cd "F:\tashy\tashy"
#    .\setup-github.ps1
# ============================================================

$GH_USER    = "haldaneharrison46"
$REPO_NAME  = "shanshan-beauty-supplies-php"
$REPO_DESC  = "Tashy Kollections – PHP/MySQL e-commerce site for tashykollections.com"
$BRANCH     = "main"

# ── Ask for PAT ──────────────────────────────────────────────
Write-Host ""
Write-Host "=== GitHub Repository Setup ===" -ForegroundColor Cyan
Write-Host ""
Write-Host "You need a GitHub Personal Access Token with 'repo' scope." -ForegroundColor Yellow
Write-Host "Create one at: https://github.com/settings/tokens/new" -ForegroundColor Yellow
Write-Host "  > Expiration: 90 days (or custom)"
Write-Host "  > Scope: check 'repo'"
Write-Host ""
$PAT = Read-Host -Prompt "Paste your GitHub PAT here" -AsSecureString
$PAT_PLAIN = [Runtime.InteropServices.Marshal]::PtrToStringAuto(
    [Runtime.InteropServices.Marshal]::SecureStringToBSTR($PAT)
)

# ── Create GitHub repo via API ────────────────────────────────
Write-Host ""
Write-Host "Creating GitHub repository '$REPO_NAME'..." -ForegroundColor Cyan

$headers = @{
    Authorization = "Bearer $PAT_PLAIN"
    Accept        = "application/vnd.github+json"
    "X-GitHub-Api-Version" = "2022-11-28"
}
$body = @{
    name        = $REPO_NAME
    description = $REPO_DESC
    private     = $false
    auto_init   = $false
} | ConvertTo-Json

try {
    $response = Invoke-RestMethod -Uri "https://api.github.com/user/repos" `
        -Method POST -Headers $headers -Body $body -ContentType "application/json"
    Write-Host "Repository created: $($response.html_url)" -ForegroundColor Green
    $REPO_URL = "https://${GH_USER}:${PAT_PLAIN}@github.com/${GH_USER}/${REPO_NAME}.git"
} catch {
    $status = $_.Exception.Response.StatusCode.value__
    if ($status -eq 422) {
        Write-Host "Repository already exists — will push to existing repo." -ForegroundColor Yellow
        $REPO_URL = "https://${GH_USER}:${PAT_PLAIN}@github.com/${GH_USER}/${REPO_NAME}.git"
    } else {
        Write-Host "Error creating repo: $_" -ForegroundColor Red
        exit 1
    }
}

# ── Create .gitignore ─────────────────────────────────────────
$gitignore = @"
# Secrets — NEVER commit real credentials
config/db.php
.env
*.env

# PHP
*.log
/vendor/

# OS
.DS_Store
Thumbs.db

# IDE
.vscode/
.idea/
*.swp
"@
$gitignore | Out-File -FilePath ".gitignore" -Encoding utf8 -Force

# Keep a safe db.php template in the repo (no real creds)
$dbTemplate = @'
<?php
// ── Database — fill in your IONOS credentials ──────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_db_name');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');

// ── Site settings ───────────────────────────────────────────
define('SITE_NAME',          'Tashy Kollections');
define('SITE_URL',           'https://tashykollections.com');
define('SITE_EMAIL',         'hello@tashykollections.com');
define('CURRENCY',           'JMD');
define('CURRENCY_SYMBOL',    'J$');
define('TAX_RATE',           0.15);
define('FREE_SHIPPING_THRESHOLD', 5000);

// ── PDO singleton ───────────────────────────────────────────
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }
    return $pdo;
}

// Auto-start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
'@
# Write template (safe version without real creds)
$dbTemplate | Out-File -FilePath "config/db.php.example" -Encoding utf8 -Force
# Remove real db.php from tracking (keep local copy)
if (Test-Path "config/db.php") {
    Write-Host "Note: config/db.php exists locally but will NOT be committed (in .gitignore)" -ForegroundColor Yellow
}

# ── Git init & push ───────────────────────────────────────────
Write-Host ""
Write-Host "Initialising git and pushing to GitHub..." -ForegroundColor Cyan

git init
git config user.name  "Haldane Harrison"
git config user.email "haldaneharrison46@gmail.com"
git branch -M $BRANCH

# Stage all (gitignore will exclude secrets)
git add -A
git commit -m "Initial commit: Tashy Kollections PHP/MySQL site

- Homepage, shop, product detail pages
- User auth (register/login/account/orders)
- Cart + checkout with Jamaican parish delivery
- Admin panel: dashboard, products, orders, users
- JSON API: cart, search, wishlist
- Brand CSS (Black & Rose Gold) + 4 themes
- MySQL schema with seed data"

git remote add origin $REPO_URL
git push -u origin $BRANCH

Write-Host ""
Write-Host "=====================================================" -ForegroundColor Green
Write-Host " SUCCESS! Repository is live at:" -ForegroundColor Green
Write-Host "  https://github.com/$GH_USER/$REPO_NAME" -ForegroundColor Cyan
Write-Host "=====================================================" -ForegroundColor Green
Write-Host ""
Write-Host "NEXT STEPS — Deploy to IONOS (tashykollections.com):" -ForegroundColor Yellow
Write-Host "1. Log in to IONOS Control Panel -> Hosting -> your domain"
Write-Host "2. Go to  Git  (under 'Deployment')"
Write-Host "3. Paste your repo URL: https://github.com/$GH_USER/$REPO_NAME"
Write-Host "4. Set branch: main  |  Deploy path: /  or /htdocs/"
Write-Host "5. Click Deploy"
Write-Host ""
Write-Host "6. In IONOS phpMyAdmin, run:  database/schema.sql"
Write-Host "7. Copy config/db.php.example -> config/db.php on the server"
Write-Host "   and fill in your IONOS MySQL credentials."
Write-Host ""
