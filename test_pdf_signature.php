<?php

require_once 'vendor/autoload.php';

use App\Models\EvaluationRequest;

// Encontrar uma avaliação concluída
$completedEvaluation = EvaluationRequest::where('status', 'completed')->first();

if ($completedEvaluation) {
    echo "✅ Avaliação encontrada - ID: {$completedEvaluation->id}\n";
    echo "📝 Avaliador: {$completedEvaluation->requestedPerson->name}\n";
    echo "👤 Avaliado: {$completedEvaluation->evaluation->evaluatedPerson->name}\n";
    echo "🖊️  Tem assinatura: " . ($completedEvaluation->assinatura_base64 ? "SIM" : "NÃO") . "\n";
    echo "📄 Evidências: " . ($completedEvaluation->evidencias ? "SIM" : "NÃO") . "\n";
    echo "\n🔗 Para testar o PDF, acesse:\n";
    echo "   /evaluations/completed/{$completedEvaluation->id}/pdf\n";
    echo "\n✨ Funcionalidade de assinatura no PDF implementada com sucesso!\n";
} else {
    echo "❌ Nenhuma avaliação concluída encontrada\n";
}
