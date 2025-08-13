<?php

require 'bootstrap/app.php';

use App\Jobs\GenerateEvaluationsJob;

try {
    echo "Executando job de geração de avaliações para 2025..." . PHP_EOL;
    $job = new GenerateEvaluationsJob('2025');
    $job->handle();
    echo "Job executado com sucesso!" . PHP_EOL;
} catch (Exception $e) {
    echo "Erro ao executar job: " . $e->getMessage() . PHP_EOL;
    echo "Stack trace: " . $e->getTraceAsString() . PHP_EOL;
}
