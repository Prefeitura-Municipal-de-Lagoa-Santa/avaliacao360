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
    ->set('remote_user', 'seu_usuario')          // 🔴 ALTERE AQUI
    ->set('hostname', '192.168.1.100')         // 🔴 ALTERE AQUI
    ->set('port', 22)
    ->set('deploy_path', '/var/www/laravel-app') // 🔴 ALTERE AQUI
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
    // Build da imagem usando o código do release (mantém cache) e com timeout maior
    run('cd {{release_path}} && docker compose build', ['timeout' => 3600]);
});

desc('Instalar dependências Node.js');
task('npm:install', function () {
    // Executa no release antes do publish. Usar 'npm ci' é melhor para CI/CD pois usa o package-lock.json
    run('cd {{release_path}} && docker compose run --rm --no-deps --entrypoint "" -w /var/www/html app npm ci', ['timeout' => 1800]);
});

desc('Compilar assets com Vite');
task('npm:build', function () {
    // Executa no release antes do publish
    run('cd {{release_path}} && docker compose run --rm --no-deps --entrypoint "" -w /var/www/html app npm run build', ['timeout' => 1800]);
});

// ✅ NOVO: Meta-tarefa para garantir a ordem correta de instalação e build.
task('build:assets', [
    'npm:install',
    'npm:build',
])->desc('Instalar dependências NPM e compilar assets');


desc('Subir containers Docker');
task('docker:up', function () {
    run('cd {{deploy_path}}/current && docker compose up -d');
});

desc('Aguardar containers iniciarem');
task('docker:wait', function () {
    info('⏳ Aguardando 5 segundos para os containers iniciarem...');
    sleep(5);
});

desc('Executar migrations');
task('artisan:migrate', function () {
    // Se "current" existir e os containers estiverem de pé, usa exec; caso contrário, roda no release usando run
    run('[ -d {{deploy_path}}/current ] && cd {{deploy_path}}/current && docker compose exec -T app php artisan migrate --force || (cd {{release_path}} && docker compose run --rm --no-deps --entrypoint "" -w /var/www/html app php artisan migrate --force)');
});

// Cachear configurações Laravel (após publish, usando container em execução)
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

// ✅ CORREÇÃO: Redefinimos a tarefa 'deploy' para ser um fluxo único e sequencial.
// Isso evita race conditions e garante que cada passo espere o anterior.
task('deploy', [
    'deploy:prepare',   // Prepara a estrutura de release
    'docker:build',     // Builda a imagem Docker com o código novo
    'deploy:vendors',   // Instala dependências do Composer
    'build:assets',     // Instala dependências NPM e compila os assets
    'deploy:publish',   // Ativa a nova release (symlink)
    'docker:up',        // Sobe os containers (usando a nova release em 'current')
    'docker:wait',      // Dá um tempo para os serviços iniciarem
    'artisan:migrate',  // Roda as migrations no container em execução
    'artisan:cache',    // Roda os comandos de cache no container em execução
])->desc('Fluxo de deploy completo');


// 1) Construir imagem ANTES de criar os links compartilhados (evita copiar symlink de storage para a imagem)
// REMOVIDO: A tarefa 'docker:build' agora faz parte do fluxo principal 'deploy'.
// before('deploy:shared', 'docker:build');

// 2) ✅ CORREÇÃO: Usar a nova meta-tarefa para garantir a ordem de instalação e build.
// REMOVIDO: A tarefa 'build:assets' agora faz parte do fluxo principal 'deploy'.
// before('deploy:publish', 'build:assets');

// 3) Depois de publicar, subir containers e executar passos Laravel
// REMOVIDO: Todas essas tarefas agora fazem parte do fluxo principal 'deploy'.
// after('deploy:publish', 'docker:up');
// after('docker:up', 'docker:wait');
// after('docker:wait', 'artisan:migrate');

// 4) Após tudo ter sucesso, recachear e limpar
// REMOVIDO: A tarefa 'artisan:cache' agora faz parte do fluxo principal 'deploy'.
// after('deploy:success', 'artisan:cache');
after('deploy:success', 'docker:cleanup');

// Sobrescreve o deploy:vendors para executar dentro do container Docker
// Isso garante que as extensões (ex.: ext-ldap) presentes na imagem sejam usadas
task('deploy:vendors', function () {
    run('cd {{release_path}} && docker compose run --rm --no-deps --entrypoint "" -w /var/www/html -e COMPOSER_ALLOW_SUPERUSER=1 app composer install --verbose --prefer-dist --no-progress --no-interaction --no-dev --optimize-autoloader', ['timeout' => 1800]);
})->desc('Instalar vendors com Composer dentro do Docker');

// ==========================================
// DEPLOY COM MODO MANUTENÇÃO
// ==========================================

task('deploy:safe', [
    'maintenance:on',
    'deploy',
    'maintenance:off',
])->desc('Deploy com modo de manutenção');

// ==========================================
// DEPLOY RÁPIDO (sem build de imagem/assets)
// ==========================================

task('deploy:quick', [
    'deploy:prepare',
    'deploy:vendors',
    'deploy:publish',
    'docker:up',
    'docker:wait',
    'artisan:migrate',
    'artisan:cache',
    'deploy:success', // Adicionado para disparar os hooks de sucesso
])->desc('Deploy rápido (sem rebuild de imagem/assets)');

// ==========================================
// ROLLBACK CUSTOMIZADO
// ==========================================

// ✅ CORREÇÃO: Remove a tarefa 'rollback' padrão antes de definir a nossa customizada.
Deployer::get()->tasks->remove('rollback');

task('rollback', [
    'deploy:rollback',          // Rollback do Deployer (muda o symlink)
    'docker:down',              // Para containers atuais
    'docker:up',                // Sobe containers da versão anterior (que está em 'current' agora)
    'docker:wait',
    'artisan:cache',            // Recacheia para a versão anterior
])->desc('Reverter para versão anterior');

// ==========================================
// CALLBACKS
// ==========================================

after('deploy:failed', function () {
    warning('❌ Deploy falhou!');
    // A sugestão de rollback deve usar o host correto
    warning('Execute "dep rollback {{hostname}}" para reverter.');
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
    // O ideal é especificar o path para começar na pasta da aplicação
    run('cd {{deploy_path}}/current && bash');
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


