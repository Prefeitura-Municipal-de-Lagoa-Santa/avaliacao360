# 🚀 Sistema de Deploy - Avaliação 360

Este documento explica como usar o sistema de deploy configurado para a aplicação Avaliação 360 com Laravel 12 + Vue + Inertia usando containers Docker.

## 📋 Pré-requisitos

### No Servidor de Destino

1. **Docker** e **Docker Compose** instalados
2. **Git** configurado
3. **Usuário de deploy** com:
   - Permissões para Docker
   - Acesso SSH com chave pública
   - Diretórios de deploy criados

### No Ambiente Local

1. **Composer** instalado
2. **Node.js** 20+ instalado
3. **Git** configurado
4. Acesso SSH aos servidores

## 🔧 Configuração Inicial

### 1. Configurar Servidores no deploy.php

Edite o arquivo `deploy.php` e configure os hostnames dos seus servidores:

```php
// Servidor de Desenvolvimento
host('develop')
    ->setHostname('192.168.1.100') // Substitua pelo IP do seu servidor
    ->setRemoteUser('deploy')
    ->setPort(22)
    ->set('deploy_path', '/var/www/avaliacao360-develop')

// Servidor de Produção  
host('production')
    ->setHostname('192.168.1.200') // Substitua pelo IP do seu servidor
    ->setRemoteUser('deploy')
    ->setPort(22)
    ->set('deploy_path', '/var/www/avaliacao360-production')
```

### 2. Configurar Variáveis de Ambiente

#### Desenvolvimento (.env.develop)
- Configure conexão com banco de dados
- Ajuste configurações de cache/Redis
- Configure LDAP se necessário

#### Produção (.env.production)
- Configure banco de dados de produção
- Configure Redis com senha
- Configure SMTP para emails
- Configure LDAP de produção
- Ative configurações de segurança

### 3. Preparar Servidores

Em cada servidor, criar estrutura básica:

```bash
# Criar diretório de deploy
sudo mkdir -p /var/www/avaliacao360-develop
sudo chown deploy:deploy /var/www/avaliacao360-develop

# Para produção
sudo mkdir -p /var/www/avaliacao360-production  
sudo chown deploy:deploy /var/www/avaliacao360-production
```

## 🚀 Como Fazer Deploy

### Deploy para Desenvolvimento

#### Usando Windows (PowerShell/CMD):
```batch
scripts\deploy-develop.bat
```

#### Usando Linux/macOS:
```bash
chmod +x scripts/deploy-develop.sh
./scripts/deploy-develop.sh
```

#### Manualmente com Deployer:
```bash
./vendor/bin/dep deploy:develop develop -v
```

### Deploy para Produção

#### Usando Windows (PowerShell/CMD):
```batch
scripts\deploy-production.bat
```

#### Usando Linux/macOS:
```bash
chmod +x scripts/deploy-production.sh
./scripts/deploy-production.sh
```

#### Manualmente com Deployer:
```bash
./vendor/bin/dep deploy production -v
```

## 📁 Estrutura do Deploy

```
/var/www/avaliacao360-{env}/
├── current/              # Link simbólico para release atual
├── releases/             # Releases anteriores (mantém 3)
│   ├── 20240101120000/
│   ├── 20240101130000/
│   └── 20240101140000/
└── shared/               # Arquivos compartilhados
    ├── storage/
    ├── .env
    └── bootstrap/cache/
```

## 🔍 Comandos Úteis

### Verificar Status dos Containers
```bash
./vendor/bin/dep docker:status develop
./vendor/bin/dep docker:status production
```

### Ver Logs dos Containers
```bash
./vendor/bin/dep docker:logs develop
./vendor/bin/dep docker:logs production
```

### Acessar Shell do Container
```bash
./vendor/bin/dep docker:shell develop
./vendor/bin/dep docker:shell production
```

### Rollback para Release Anterior
```bash
./vendor/bin/dep rollback develop
./vendor/bin/dep rollback production
```

## 🐳 Containers Configurados

### Desenvolvimento
- **App**: Aplicação principal (porta 8001)
- **DB**: MySQL 8.0 (porta 3307)
- **Redis**: Cache e sessões (porta 6380)
- **Queue**: Worker de filas

### Produção
- **App**: Aplicação principal (porta 8000)
- **Redis**: Cache otimizado para produção
- **Queue**: Worker de filas
- **Scheduler**: Agendador de tarefas

## 🔒 Segurança

### Desenvolvimento
- Debug habilitado
- Logs detalhados
- Banco local

### Produção
- Debug desabilitado
- Logs otimizados
- HTTPS obrigatório
- Cookies seguros
- Banco externo

## 🚨 Troubleshooting

### Deploy Falha por Permissões
```bash
# No servidor, ajustar permissões
sudo chown -R deploy:deploy /var/www/avaliacao360-{env}
sudo chmod -R 755 /var/www/avaliacao360-{env}
```

### Container não Inicia
```bash
# Verificar logs
docker-compose -f docker-compose.{env}.yml logs

# Reconstruir imagem
docker-compose -f docker-compose.{env}.yml build --no-cache
```

### Banco de Dados não Conecta
1. Verificar configurações no `.env.{env}`
2. Verificar se serviço está rodando
3. Verificar conectividade de rede

### Assets não Carregam
```bash
# Executar build local
npm run build

# Re-fazer deploy
./vendor/bin/dep deploy {env} -v
```

## 📊 Monitoramento

### Healthcheck
O container inclui healthcheck automático:
- URL: `http://servidor/health`
- Intervalo: 30 segundos
- Timeout: 10 segundos

### Logs
- Aplicação: `/var/www/html/storage/logs/`
- Nginx: Container logs via `docker logs`
- PHP-FPM: Container logs via `docker logs`

## 🔄 Processo de Deploy Detalhado

1. **Preparação**: Cria nova release
2. **Código**: Clona repositório
3. **Dependências**: Instala composer packages
4. **Build**: Compila assets frontend
5. **Upload**: Envia assets para servidor
6. **Docker**: Para containers antigos
7. **Build**: Constrói nova imagem Docker
8. **Containers**: Inicia novos containers
9. **Migração**: Executa migrações de BD
10. **Cache**: Otimiza cache de produção
11. **Ativação**: Ativa nova release
12. **Limpeza**: Remove releases antigas

## 📞 Suporte

Para problemas ou dúvidas sobre o deploy:

1. Verifique logs com `docker:logs`
2. Verifique status com `docker:status`
3. Consulte documentação do Deployer
4. Verifique configurações de ambiente