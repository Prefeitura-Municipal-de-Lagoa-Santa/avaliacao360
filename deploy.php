<?php
namespace Deployer;

require 'recipe/laravel.php';

// ==========================================
// CONFIGURAÇÕES GERAIS
// ==========================================

set('application', 'Laravel Avaliação');
set('repository', 'git@github.com:Prefeitura-Municipal-de-Lagoa-Santa/avaliacao360.git');
set('keep_releases', 3);
set('writable_mode', 'chmod');
set('writable_chmod_mode', '0775');
set('use_relative_symlink', false);

// ==========================================
// CONFIGURAÇÃO DO SERVIDOR
// ==========================================

host('producao')
    ->set('remote_user', 'seu_usuario')              // 🔴 ALTERE AQUI
    ->set('hostname', '192.168.1.100')               // 🔴 ALTERE AQUI
    ->set('port', 22)
    ->set('deploy_path', '/var/www/laravel-app')    // 🔴 ALTERE AQUI
    ->set('branch', 'main');

host('develop')
    ->set('remote_user', 'deploy')
    ->set('hostname', '10.1.7.75') 
    ->set('port', 22)
    ->set('deploy_path', '/var/www/avaliacao')
    ->set('branch', 'develop');

// ==========================================
// ARQUIVOS E PASTAS COMPARTILHADAS
// ==========================================

add('shared_files', [
    '.env',
]);

add('shared_dirs', [
    'storage',
]);

add('writable_dirs', [
    'bootstrap/cache',
    'storage',
    'storage/app',
    'storage/app/public',
    'storage/framework',
    'storage/framework/cache',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs',
]);

// ==========================================
// TASKS CUSTOMIZADAS PARA DOCKER
// ==========================================

desc('Parar containers Docker');
task('docker:down', function () {
    run('cd {{deploy_path}}/current && docker compose down || true');
});

desc('Build da imagem Docker');
task('docker:build', function () {
    run('cd {{deploy_path}}/current && docker compose build --no-cache');
});

desc('Instalar dependências Node.js');
task('npm:install', function () {
    run('cd {{deploy_path}}/current && docker compose run --rm app npm ci');
});

desc('Compilar assets com Vite');
task('npm:build', function () {
    run('cd {{deploy_path}}/current && docker compose run --rm app npm run build');
});

desc('Subir containers Docker');
task('docker:up', function () {
    run('cd {{deploy_path}}/current && docker compose up -d');
});

desc('Aguardar containers iniciarem');
task('docker:wait', function () {
    sleep(5);
    info('⏳ Aguardando containers iniciarem...');
});

desc('Executar migrations');
task('artisan:migrate', function () {
    run('cd {{deploy_path}}/current && docker compose exec -T app php artisan migrate --force');
});

desc('Criar link simbólico do storage');
task('artisan:storage-link', function () {
    run('cd {{deploy_path}}/current && docker compose exec -T app php artisan storage:link || true');
});

desc('Cachear configurações Laravel');
task('artisan:cache', function () {
    run('cd {{deploy_path}}/current && docker compose exec -T app php artisan config:cache');
    run('cd {{deploy_path}}/current && docker compose exec -T app php artisan route:cache');
    run('cd {{deploy_path}}/current && docker compose exec -T app php artisan view:cache');
});

desc('Limpar caches Laravel');
task('artisan:clear', function () {
    run('cd {{deploy_path}}/current && docker compose exec -T app php artisan cache:clear || true');
    run('cd {{deploy_path}}/current && docker compose exec -T app php artisan config:clear || true');
});

desc('Limpar recursos Docker não utilizados');
task('docker:cleanup', function () {
    run('docker system prune -f');
});

desc('Verificar status dos containers');
task('docker:status', function () {
    run('cd {{deploy_path}}/current && docker compose ps');
});

desc('Ver logs da aplicação');
task('logs', function () {
    run('cd {{deploy_path}}/current && docker compose logs --tail=50 app');
});

desc('Modo manutenção ON');
task('maintenance:on', function () {
    run('cd {{deploy_path}}/current && docker compose exec -T app php artisan down --retry=60');
});

desc('Modo manutenção OFF');
task('maintenance:off', function () {
    run('cd {{deploy_path}}/current && docker compose exec -T app php artisan up');
});

// ==========================================
// FLUXO DE DEPLOY PRINCIPAL
// ==========================================

task('deploy', [
    'deploy:prepare',           // Cria estrutura de pastas
    'deploy:vendors',           // composer install
    'npm:install',              // npm ci
    'npm:build',                // npm run build
    'docker:down',              // Para containers antigos
    'docker:build',             // Build nova imagem
    'deploy:publish',           // Atualiza symlink current
    'docker:up',                // Sobe novos containers
    'docker:wait',              // Aguarda inicialização
    'artisan:migrate',          // Roda migrations
    'artisan:storage-link',     // Link do storage
    'artisan:cache',            // Cacheia configs
    'docker:cleanup',           // Limpa Docker
    'deploy:cleanup',           // Remove releases antigas
])->desc('Deploy completo da aplicação');

// ==========================================
// DEPLOY COM MODO MANUTENÇÃO
// ==========================================

task('deploy:safe', [
    'maintenance:on',           // Ativa manutenção
    'deploy',                   // Deploy normal
    'maintenance:off',          // Desativa manutenção
])->desc('Deploy com modo de manutenção');

// ==========================================
// DEPLOY RÁPIDO (sem build)
// ==========================================

task('deploy:quick', [
    'deploy:prepare',
    'deploy:vendors',
    'docker:down',
    'deploy:publish',
    'docker:up',
    'docker:wait',
    'artisan:migrate',
    'artisan:cache',
])->desc('Deploy rápido (sem rebuild de assets)');

// ==========================================
// ROLLBACK CUSTOMIZADO
// ==========================================

task('rollback', [
    'deploy:rollback',          // Rollback do Deployer
    'docker:down',              // Para containers
    'docker:up',                // Sobe containers da versão anterior
    'docker:wait',
    'artisan:cache',            // Recacheia
])->desc('Reverter para versão anterior');

// ==========================================
// CALLBACKS
// ==========================================

after('deploy:failed', function () {
    warning('❌ Deploy falhou!');
    warning('Execute "dep rollback producao" para reverter.');
    invoke('maintenance:off');  // Garante que sai do modo manutenção
});

after('deploy:success', function () {
    info('✅ Deploy concluído com sucesso!');
    info('🌐 Sua aplicação está online!');
});

// ==========================================
// TASKS AUXILIARES
// ==========================================

desc('Conectar ao servidor via SSH');
task('ssh', function () {
    run('bash');
});

desc('Reiniciar containers');
task('restart', function () {
    run('cd {{deploy_path}}/current && docker compose restart');
});

desc('Status completo do sistema');
task('status', function () {
    info('📊 Status do Sistema:');
    run('cd {{deploy_path}}/current && docker compose ps');
    run('df -h | grep -E "Filesystem|/var/www"');
    run('free -h');
});