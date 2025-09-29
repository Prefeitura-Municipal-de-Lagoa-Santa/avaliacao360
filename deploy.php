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
// Desabilita multiplexação SSH (evita erros em Windows como "getsockname failed: Not a socket")
set('ssh_multiplexing', false);

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
// ==========================================
// TASKS CUSTOMIZADAS PARA DOCKER
// ==========================================

desc('Parar containers Docker');
task('docker:down', function () {
    run('cd {{deploy_path}}/current && docker compose down || true');
});

desc('Build da imagem Docker');
task('docker:build', function () {
    // Build da imagem usando o código do release (mantém cache) e com timeout maior
    run('cd {{release_path}} && docker compose build', ['timeout' => 3600]);
});

desc('Instalar dependências Node.js');
task('npm:install', function () {
    // Executa no release antes do publish
    run('cd {{release_path}} && docker compose run --rm app npm ci', ['timeout' => 1800]);
});

desc('Compilar assets com Vite');
task('npm:build', function () {
    // Executa no release antes do publish
    run('cd {{release_path}} && docker compose run --rm app npm run build', ['timeout' => 1800]);
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
    // Só tenta se o symlink current existir
    run('[ -d {{deploy_path}}/current ] && cd {{deploy_path}}/current && docker compose exec -T app php artisan down --retry=60 || true');
});

desc('Modo manutenção OFF');
task('maintenance:off', function () {
    // Só tenta se o symlink current existir
    run('[ -d {{deploy_path}}/current ] && cd {{deploy_path}}/current && docker compose exec -T app php artisan up || true');
});

// ==========================================
// FLUXO DE DEPLOY PRINCIPAL
// ==========================================

// Vamos usar o fluxo padrão do recipe/laravel e inserir nossos passos com hooks
// 1) Construir imagem ANTES de criar os links compartilhados (evita copiar symlink de storage para a imagem)
before('deploy:shared', 'docker:build');
// 2) Rodar npm dentro do release antes do publish
before('deploy:publish', 'npm:install');
before('deploy:publish', 'npm:build');

// Sobrescreve o deploy:vendors para executar dentro do container Docker
// Isso garante que as extensões (ex.: ext-ldap) presentes na imagem sejam usadas
task('deploy:vendors', function () {
    run('cd {{release_path}} && docker compose run --rm app composer install --verbose --prefer-dist --no-progress --no-interaction --no-dev --optimize-autoloader', ['timeout' => 1800]);
})->desc('Instalar vendors com Composer dentro do Docker');

// ==========================================
// DEPLOY COM MODO MANUTENÇÃO
// ==========================================

// Mantém tarefa de deploy:safe usando o fluxo padrão, apenas com hooks aplicados
task('deploy:safe', [
    'maintenance:on',
    'deploy',
    'maintenance:off',
])->desc('Deploy com modo de manutenção');

// ==========================================
// DEPLOY RÁPIDO (sem build)
// ==========================================

task('deploy:quick', [
    'deploy:prepare',
    'deploy:vendors',
    'deploy:publish',
    'docker:up',
    'docker:wait',
    'artisan:migrate',
    'artisan:cache',
])->desc('Deploy rápido (sem rebuild de assets)');

// ==========================================
// ROLLBACK CUSTOMIZADO
// ==========================================

// Remove a task padrão do rollback
Deployer::get()->tasks->remove('rollback');

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
