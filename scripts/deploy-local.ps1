# Script de deploy local para desenvolvimento - PowerShell
# Usa Docker local em vez de servidor remoto
# Uso: .\scripts\deploy-local.ps1

Write-Host "🚀 Iniciando deploy LOCAL para teste..." -ForegroundColor Green

# Verifica se estamos na branch develop
$currentBranch = git rev-parse --abbrev-ref HEAD
if ($currentBranch -ne "develop") {
    Write-Host "Você não está na branch develop. Branch atual: $currentBranch" -ForegroundColor Yellow
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

# Para containers existentes
Write-Host "Parando containers existentes..." -ForegroundColor Blue
docker-compose -f docker-compose.develop.yml down

# Build dos assets
Write-Host "Compilando assets frontend..." -ForegroundColor Blue
npm install
npm run build

# Build e start dos containers
Write-Host "Construindo e iniciando containers..." -ForegroundColor Blue
docker-compose -f docker-compose.develop.yml build --no-cache
docker-compose -f docker-compose.develop.yml up -d

# Aguarda containers estarem prontos
Write-Host "Aguardando containers ficarem prontos..." -ForegroundColor Blue
Start-Sleep -Seconds 10

# Verifica se containers estão rodando
Write-Host "Verificando status dos containers..." -ForegroundColor Blue
docker-compose -f docker-compose.develop.yml ps

# Executa migrações
Write-Host "Executando migrações..." -ForegroundColor Blue
docker-compose -f docker-compose.develop.yml exec -T app php artisan migrate --force

# Gera chave se necessário
Write-Host "Verificando chave da aplicação..." -ForegroundColor Blue
docker-compose -f docker-compose.develop.yml exec -T app php artisan key:generate

# Cache para desenvolvimento
Write-Host "Limpando cache..." -ForegroundColor Blue
docker-compose -f docker-compose.develop.yml exec -T app php artisan config:clear
docker-compose -f docker-compose.develop.yml exec -T app php artisan route:clear
docker-compose -f docker-compose.develop.yml exec -T app php artisan view:clear

# Storage link
Write-Host "Criando link do storage..." -ForegroundColor Blue
docker-compose -f docker-compose.develop.yml exec -T app php artisan storage:link

Write-Host "Deploy local concluído com sucesso!" -ForegroundColor Green
Write-Host "Aplicação disponível em: http://localhost:8001" -ForegroundColor Cyan
Write-Host ""
Write-Host "Para verificar logs:" -ForegroundColor Yellow
Write-Host "docker-compose -f docker-compose.develop.yml logs -f" -ForegroundColor White