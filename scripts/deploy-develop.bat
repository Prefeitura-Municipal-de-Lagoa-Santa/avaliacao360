@echo off
REM Script de deploy para desenvolvimento - Windows
REM Uso: scripts\deploy-develop.bat

echo 🚀 Iniciando deploy para DESENVOLVIMENTO...

REM Verifica se estamos na branch develop
for /f "tokens=*" %%i in ('git rev-parse --abbrev-ref HEAD') do set CURRENT_BRANCH=%%i

if not "%CURRENT_BRANCH%"=="develop" (
    echo ⚠️  Você não está na branch develop. Branch atual: %CURRENT_BRANCH%
    set /p REPLY="Deseja continuar? (y/N): "
    if not "%REPLY%"=="y" if not "%REPLY%"=="Y" (
        echo Deploy cancelado.
        exit /b 1
    )
)

REM Verifica se há mudanças não commitadas
git status --porcelain > temp_status.txt
for /f %%i in ("temp_status.txt") do set SIZE=%%~zi
del temp_status.txt
if %SIZE% gtr 0 (
    echo ❌ Existem mudanças não commitadas. Faça commit antes do deploy.
    git status
    exit /b 1
)

echo 📤 Fazendo push das mudanças...
git push origin develop

echo 🔄 Executando deploy...
.\vendor\bin\dep deploy:develop develop -v

echo ✅ Deploy para desenvolvimento concluído com sucesso!
echo 🌐 Aplicação disponível em: http://seu-servidor-develop:8001