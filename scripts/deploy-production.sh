#!/bin/bash

# Script de deploy para produção
# Uso: ./scripts/deploy-production.sh

set -e

echo "🚀 Iniciando deploy para PRODUÇÃO..."

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Verifica se estamos na branch main
CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD)
if [ "$CURRENT_BRANCH" != "main" ]; then
    echo -e "${RED}❌ Deploy para produção deve ser feito a partir da branch main!${NC}"
    echo -e "${YELLOW}Branch atual: $CURRENT_BRANCH${NC}"
    exit 1
fi

# Verifica se há mudanças não commitadas
if ! git diff-index --quiet HEAD --; then
    echo -e "${RED}❌ Existem mudanças não commitadas. Faça commit antes do deploy.${NC}"
    exit 1
fi

# Confirmação de deploy para produção
echo -e "${YELLOW}⚠️  ATENÇÃO: Você está prestes a fazer deploy para PRODUÇÃO!${NC}"
echo -e "${BLUE}🔍 Verificações de segurança:${NC}"
echo "   - Branch: $CURRENT_BRANCH"
echo "   - Último commit: $(git log -1 --pretty=format:'%h - %s (%an)')"
echo ""

read -p "Tem certeza que deseja continuar com o deploy para PRODUÇÃO? (yes/N): " -r
if [[ ! $REPLY =~ ^yes$ ]]; then
    echo "Deploy cancelado."
    exit 1
fi

# Faz push das mudanças
echo "📤 Fazendo push das mudanças..."
git push origin main

# Executa testes antes do deploy (se existirem)
if [ -f "phpunit.xml" ]; then
    echo "🧪 Executando testes..."
    ./vendor/bin/pest || {
        echo -e "${RED}❌ Testes falharam! Deploy cancelado.${NC}"
        exit 1
    }
fi

# Executa deploy usando Deployer
echo "🔄 Executando deploy para produção..."
./vendor/bin/dep deploy production -v

# Tag da versão (opcional)
echo ""
read -p "Deseja criar uma tag para esta versão? (y/N): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    read -p "Digite a versão (ex: v1.2.3): " VERSION
    if [ ! -z "$VERSION" ]; then
        git tag -a "$VERSION" -m "Deploy para produção - $VERSION"
        git push origin "$VERSION"
        echo -e "${GREEN}✅ Tag $VERSION criada e enviada!${NC}"
    fi
fi

echo -e "${GREEN}✅ Deploy para produção concluído com sucesso!${NC}"
echo "🌐 Aplicação disponível em: https://avaliacao360.exemplo.com"