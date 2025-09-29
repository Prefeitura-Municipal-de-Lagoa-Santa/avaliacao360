<?php

namespace Deployer;

require 'recipe/laravel.php';

// Configurações globais
set('application', 'avaliacao360');
set('repository', 'git@github.com:Prefeitura-Municipal-de-Lagoa-Santa/avaliacao360.git');
set('git_tty', true);
set('keep_releases', 3);

// Configuração para usar containers Docker
set('use_docker', true);
set('docker_compose_file', 'docker-compose.yml');
set('container_name', 'laravel_avaliacao');

// Diretórios compartilhados entre deploys
set('shared_dirs', [
    'storage',
    'bootstrap/cache',
]);

// Arquivos compartilhados entre deploys
set('shared_files', [
    '.env'
]);

// Diretórios que devem ter permissões específicas
set('writable_dirs', [
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

// Usar sudo para chmod se necessário
set('writable_use_sudo', false);

// ==============================================
// HOSTS - Servidores de Desenvolvimento e Produção
// ==============================================

// Servidor de Desenvolvimento
host('develop')
    ->setHostname('10.1.7.75') // Substitua pelo IP/hostname
    ->setRemoteUser('deploy') // Usuário para deploy
    ->setPort(22)
    ->set('labels', ['stage' => 'develop'])
    ->set('deploy_path', '/var/www/avaliacao360-develop')
    ->set('branch', 'develop')
    ->set('docker_compose_env', 'develop')
    ->set('app_env', 'develop');

// Servidor de Produção
host('production')
    ->setHostname('SEU_SERVIDOR_PRODUCTION') // Substitua pelo IP/hostname
    ->setRemoteUser('deploy') // Usuário para deploy
    ->setPort(22)
    ->set('labels', ['stage' => 'production'])
    ->set('deploy_path', '/var/www/avaliacao360-production')
    ->set('branch', 'main')
    ->set('docker_compose_env', 'production')
    ->set('app_env', 'production');

// ==============================================
// TAREFAS CUSTOMIZADAS PARA CONTAINERS
// ==============================================

// Tarefa para build dos assets do frontend
task('build:assets', function () {
    writeln('<info>Building frontend assets...</info>');
    runLocally('npm install');
    runLocally('npm run build');
})->desc('Build frontend assets locally');

// Tarefa para fazer upload dos assets buildados
task('upload:assets', function () {
    writeln('<info>Uploading built assets...</info>');
    upload('public/build/', '{{release_path}}/public/build/', [
        'options' => ['--recursive', '--compress']
    ]);
})->desc('Upload built assets to server');

// Tarefa para construir a imagem Docker no servidor
task('docker:build', function () {
    $dockerComposeFile = get('docker_compose_file');
    $env = get('docker_compose_env');
    
    writeln('<info>Building Docker image on server...</info>');
    
    // Para em containers existentes
    run("cd {{release_path}} && docker-compose -f docker-compose.{{env}}.yml down || true");
    
    // Constrói nova imagem
    run("cd {{release_path}} && docker-compose -f docker-compose.{{env}}.yml build --no-cache");
})->desc('Build Docker image on server');

// Tarefa para iniciar containers
task('docker:up', function () {
    $env = get('docker_compose_env');
    
    writeln('<info>Starting Docker containers...</info>');
    run("cd {{release_path}} && docker-compose -f docker-compose.{{env}}.yml up -d");
})->desc('Start Docker containers');

// Tarefa para parar containers antigos
task('docker:stop_old', function () {
    $env = get('docker_compose_env');
    
    writeln('<info>Stopping old containers...</info>');
    run("cd {{current_path}} && docker-compose -f docker-compose.{{env}}.yml down || true");
})->desc('Stop old Docker containers');

// Tarefa para executar comandos Artisan dentro do container
task('artisan:migrate', function () {
    $containerName = get('container_name');
    $env = get('docker_compose_env');
    
    writeln('<info>Running database migrations inside container...</info>');
    run("cd {{release_path}} && docker-compose -f docker-compose.{{env}}.yml exec -T app php artisan migrate --force");
})->desc('Run database migrations inside container');

task('artisan:cache', function () {
    $containerName = get('container_name');
    $env = get('docker_compose_env');
    
    writeln('<info>Clearing and caching inside container...</info>');
    run("cd {{release_path}} && docker-compose -f docker-compose.{{env}}.yml exec -T app php artisan config:cache");
    run("cd {{release_path}} && docker-compose -f docker-compose.{{env}}.yml exec -T app php artisan route:cache");
    run("cd {{release_path}} && docker-compose -f docker-compose.{{env}}.yml exec -T app php artisan view:cache");
})->desc('Cache config, routes and views inside container');

task('artisan:queue_restart', function () {
    $env = get('docker_compose_env');
    
    writeln('<info>Restarting queue workers inside container...</info>');
    run("cd {{release_path}} && docker-compose -f docker-compose.{{env}}.yml exec -T app php artisan queue:restart || true");
})->desc('Restart queue workers inside container');

// ==============================================
// FLUXO DE DEPLOY CUSTOMIZADO
// ==============================================

// Fluxo principal de deploy
task('deploy', [
    'deploy:prepare',
    'deploy:vendors',
    'build:assets',
    'upload:assets',
    'docker:stop_old',
    'docker:build',
    'docker:up',
    'artisan:migrate',
    'artisan:cache',
    'artisan:queue_restart',
    'deploy:publish',
    'deploy:cleanup',
])->desc('Deploy application with Docker');

// Deploy apenas para desenvolvimento (mais rápido, sem build completo)
task('deploy:develop', [
    'deploy:prepare',
    'deploy:vendors',
    'build:assets',
    'upload:assets',
    'docker:stop_old',
    'docker:up',
    'artisan:migrate',
    'deploy:publish',
])->desc('Quick deploy for development environment');

// ==============================================
// HOOKS E EVENTOS
// ==============================================

// Antes do deploy
before('deploy', 'deploy:info');

// Depois do deploy bem-sucedido
after('deploy:success', function () {
    writeln('<info>✅ Deploy completed successfully!</info>');
});

// Em caso de falha no deploy
fail('deploy', function () {
    writeln('<error>❌ Deploy failed!</error>');
    // Tentar voltar containers antigos
    invoke('docker:stop_old');
});

// ==============================================
// TAREFAS AUXILIARES
// ==============================================

// Tarefa para verificar status dos containers
task('docker:status', function () {
    $env = get('docker_compose_env');
    run("cd {{current_path}} && docker-compose -f docker-compose.{{env}}.yml ps");
})->desc('Show Docker containers status');

// Tarefa para ver logs dos containers
task('docker:logs', function () {
    $env = get('docker_compose_env');
    run("cd {{current_path}} && docker-compose -f docker-compose.{{env}}.yml logs --tail=50");
})->desc('Show Docker containers logs');

// Tarefa para acessar o container
task('docker:shell', function () {
    $env = get('docker_compose_env');
    runLocally("ssh -t {{hostname}} 'cd {{current_path}} && docker-compose -f docker-compose.{{env}}.yml exec app bash'");
})->desc('Access container shell');