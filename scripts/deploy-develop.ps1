# Script de deploy para desenvolvimento - PowerShell
# Uso: .\scripts\deploy-develop.ps1

Write-Host "🚀 Iniciando deploy para DESENVOLVIMENTO..." -ForegroundColor Green

# Verifica se estamos na branch develop
$currentBranch = git rev-parse --abbrev-ref HEAD
if ($currentBranch -ne "develop") {
    Write-Host "⚠️  Você não está na branch develop. Branch atual: $currentBranch" -ForegroundColor Yellow
    $reply = Read-Host "Deseja continuar? (y/N)"
    if ($reply -ne "y" -and $reply -ne "Y") {
        Write-Host "Deploy cancelado." -ForegroundColor Red
        exit 1
    }
}

# Verifica se há mudanças não commitadas
$status = git status --porcelain
if ($status) {
    Write-Host "Existem mudanças não commitadas. Faça commit antes do deploy." -ForegroundColor Red
    git status
    exit 1
}

# Verifica sincronização com remoto
Write-Host "Verificando sincronização com remoto..." -ForegroundColor Blue
git fetch origin develop

$ahead = git rev-list origin/develop..HEAD --count
if ([int]$ahead -gt 0) {
    Write-Host "Fazendo push das mudanças locais..." -ForegroundColor Blue
    git push origin develop
}

# Executa deploy usando Deployer
Write-Host "Executando deploy..." -ForegroundColor Blue
& .\vendor\bin\dep deploy:develop develop -v

if ($LASTEXITCODE -eq 0) {
    Write-Host "Deploy para desenvolvimento concluído com sucesso!" -ForegroundColor Green
    Write-Host "Aplicação disponível em: http://10.1.7.75:8001" -ForegroundColor Cyan
} else {
    Write-Host "Deploy falhou!" -ForegroundColor Red
    exit 1
}