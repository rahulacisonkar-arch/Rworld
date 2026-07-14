# ============================================================
#  ARTEE VPN — Automated NetBird Rebranding Script
#  Powershell Script for cloning, renaming, and building NetBird
#  source code into custom "Artee VPN" binaries/images
# ============================================================

$NetbirdRepo = "https://github.com/netbirdio/netbird.git"
$SourceDir = "$PSScriptRoot\netbird-src"
$TempDir = "$PSScriptRoot\temp_rebrand"

Write-Host "==============================================" -ForegroundColor Cyan
Write-Host "   ARTEE VPN REBRANDING TOOL" -ForegroundColor Cyan
Write-Host "==============================================" -ForegroundColor Cyan
Write-Host ""

# 1. Download and Extract NetBird Source Code
if (Test-Path $SourceDir) {
    Write-Host "[*] Source directory already exists. Skipping download." -ForegroundColor Yellow
} else {
    $ZipUrl = "https://github.com/netbirdio/netbird/archive/refs/heads/main.zip"
    $ZipFile = "$PSScriptRoot\netbird-main.zip"
    
    Write-Host "[*] Downloading Netbird source code zip..." -ForegroundColor Green
    Invoke-WebRequest -Uri $ZipUrl -OutFile $ZipFile -UseBasicParsing

    Write-Host "[*] Extracting source code..." -ForegroundColor Green
    Expand-Archive -Path $ZipFile -DestinationPath $PSScriptRoot -Force

    # Rename extracted directory to match expected name
    Rename-Item -Path "$PSScriptRoot\netbird-main" -NewName "netbird-src" -Force
    Remove-Item -Path $ZipFile -Force
}

if (-not (Test-Path $SourceDir)) {
    Write-Error "Failed to download and extract Netbird repository."
    exit
}

# 2. Perform Rebranding Replacements
Write-Host "[*] Replacing NetBird branding with Artee VPN..." -ForegroundColor Green

# Target file extensions to scan and modify
$extensions = @("*.go", "*.json", "*.md", "*.yaml", "*.yml", "*.txt", "*.sh", "*.bat", "Dockerfile*")

foreach ($ext in $extensions) {
    Write-Host "Scanning $ext files..." -ForegroundColor Gray
    Get-ChildItem -Path $SourceDir -Filter $ext -Recurse | ForEach-Object {
        $file = $_.FullName
        $content = Get-Content -Path $file -Raw -ErrorAction SilentlyContinue
        if ($content) {
            $modified = $false
            if ($content -match "NetBird") {
                $content = $content -replace "NetBird", "Artee VPN"
                $modified = $true
            }
            if ($content -match "netbird") {
                $content = $content -replace "netbird", "arteevpn"
                $modified = $true
            }
            if ($content -match "Netbird") {
                $content = $content -replace "Netbird", "ArteeVPN"
                $modified = $true
            }
            if ($modified) {
                Set-Content -Path $file -Value $content -Force
            }
        }
    }
}

# 3. Rename Directories and Files if applicable
Write-Host "[*] Renaming files containing netbird..." -ForegroundColor Green
Get-ChildItem -Path $SourceDir -Recurse | Where-Object { $_.Name -like "*netbird*" } | ForEach-Object {
    $oldPath = $_.FullName
    $newName = $_.Name -replace "netbird", "arteevpn"
    $newPath = Join-Path $_.Parent.FullName $newName
    if ($oldPath -ne $newPath) {
        Rename-Item -Path $oldPath -NewName $newName -Force -ErrorAction SilentlyContinue
    }
}

Write-Host "==============================================" -ForegroundColor Green
Write-Host " Rebranding Completed!" -ForegroundColor Green
Write-Host " You can now compile the source code using Docker:" -ForegroundColor Green
Write-Host " Build Management: docker build -t artee-management -f $SourceDir/management/Dockerfile ." -ForegroundColor Yellow
Write-Host " Build Signal:     docker build -t artee-signal -f $SourceDir/signal/Dockerfile ." -ForegroundColor Yellow
Write-Host " Build Client:     docker build -t artee-client -f $SourceDir/client/Dockerfile ." -ForegroundColor Yellow
Write-Host "==============================================" -ForegroundColor Green
