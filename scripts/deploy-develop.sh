#!/bin/bash

# Script de deploy para desenvolvimento
# Uso: ./scripts/deploy-develop.sh

set -e

echo "🚀 Iniciando deploy para DESENVOLVIMENTO..."

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Verifica se estamos na branch develop
CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD)
if [ "$CURRENT_BRANCH" != "develop" ]; then
    echo -e "${YELLOW}⚠️  Você não está na branch develop. Branch atual: $CURRENT_BRANCH${NC}"
    read -p "Deseja continuar? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "Deploy cancelado."
        exit 1
    fi
fi

# Verifica se há mudanças não commitadas
if ! git diff-index --quiet HEAD --; then
    echo -e "${RED}❌ Existem mudanças não commitadas. Faça commit antes do deploy.${NC}"
    exit 1
fi

# Faz push das mudanças
echo "📤 Fazendo push das mudanças..."
git push origin develop

# Executa deploy usando Deployer
echo "🔄 Executando deploy..."
./vendor/bin/dep deploy:develop develop -v

echo -e "${GREEN}✅ Deploy para desenvolvimento concluído com sucesso!${NC}"
echo "🌐 Aplicação disponível em: http://seu-servidor-develop:8001"