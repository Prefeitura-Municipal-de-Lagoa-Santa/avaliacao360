@echo off
REM Script de deploy para produção - Windows
REM Uso: scripts\deploy-production.bat

echo 🚀 Iniciando deploy para PRODUÇÃO...

REM Verifica se estamos na branch main
for /f "tokens=*" %%i in ('git rev-parse --abbrev-ref HEAD') do set CURRENT_BRANCH=%%i

if not "%CURRENT_BRANCH%"=="main" (
    echo ❌ Deploy para produção deve ser feito a partir da branch main!
    echo Branch atual: %CURRENT_BRANCH%
    exit /b 1
)

REM Verifica se há mudanças não commitadas
git diff-index --quiet HEAD --
if errorlevel 1 (
    echo ❌ Existem mudanças não commitadas. Faça commit antes do deploy.
    exit /b 1
)

echo ⚠️  ATENÇÃO: Você está prestes a fazer deploy para PRODUÇÃO!
echo Branch: %CURRENT_BRANCH%
for /f "tokens=*" %%i in ('git log -1 --pretty^=format:"%%h - %%s (%%an)"') do echo Último commit: %%i
echo.

set /p REPLY="Tem certeza que deseja continuar com o deploy para PRODUÇÃO? (yes/N): "
if not "%REPLY%"=="yes" (
    echo Deploy cancelado.
    exit /b 1
)

echo 📤 Fazendo push das mudanças...
git push origin main

if exist "phpunit.xml" (
    echo 🧪 Executando testes...
    .\vendor\bin\pest
    if errorlevel 1 (
        echo ❌ Testes falharam! Deploy cancelado.
        exit /b 1
    )
)

echo 🔄 Executando deploy para produção...
.\vendor\bin\dep deploy production -v

echo ✅ Deploy para produção concluído com sucesso!
echo 🌐 Aplicação disponível em: https://avaliacao360.exemplo.com