<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Form;
use App\Models\GroupQuestion;
use App\Models\Question;

class RecalculateQuestionWeights extends Command
{
    protected $signature = 'forms:recalculate-weights {--form-id= : ID específico do formulário para recalcular}';
    
    protected $description = 'Recalcula os pesos das questões dos formulários para corrigir arredondamentos';

    public function handle()
    {
        $formId = $this->option('form-id');
        
        if ($formId) {
            $forms = Form::where('id', $formId)->get();
            if ($forms->isEmpty()) {
                $this->error("Formulário com ID {$formId} não encontrado.");
                return 1;
            }
        } else {
            $forms = Form::with(['groupQuestions.questions'])->get();
        }

        $this->info('Iniciando recálculo dos pesos das questões...');
        $progressBar = $this->output->createProgressBar($forms->count());

        $totalFormsProcessed = 0;
        $totalQuestionsUpdated = 0;

        foreach ($forms as $form) {
            $this->info("\nProcessando formulário: {$form->name} (ID: {$form->id})");
            
            foreach ($form->groupQuestions as $group) {
                $questions = $group->questions;
                
                if ($questions->count() === 0) {
                    continue;
                }

                // Assume que todas as questões no grupo têm peso igual (divisão igual)
                // Pega a soma atual dos pesos relativos para calcular proporcionalmente
                $totalCurrentWeight = $questions->sum('weight');
                
                if ($totalCurrentWeight > 0) {
                    // Se já existem pesos, mantém a proporção atual
                    $groupWeight = $group->weight / 100.0;
                    
                    foreach ($questions as $question) {
                        $relativeWeight = ($question->weight / $totalCurrentWeight) * 100;
                        $newWeight = $groupWeight * $relativeWeight;
                        
                        if (abs($question->weight - $newWeight) > 0.001) {
                            $oldWeight = $question->weight;
                            $question->weight = $newWeight;
                            $question->save();
                            
                            $this->line("  Questão {$question->id}: {$oldWeight}% → " . number_format($newWeight, 3) . "%");
                            $totalQuestionsUpdated++;
                        }
                    }
                } else {
                    // Se não há pesos, divide igualmente
                    $questionsCount = $questions->count();
                    $weightPerQuestion = ($group->weight / 100.0) * (100 / $questionsCount);
                    
                    foreach ($questions as $question) {
                        $oldWeight = $question->weight;
                        $question->weight = $weightPerQuestion;
                        $question->save();
                        
                        $this->line("  Questão {$question->id}: {$oldWeight}% → " . number_format($weightPerQuestion, 3) . "%");
                        $totalQuestionsUpdated++;
                    }
                }
            }
            
            $totalFormsProcessed++;
            $progressBar->advance();
        }

        $progressBar->finish();
        
        $this->info("\n\n✅ Recálculo concluído!");
        $this->info("📊 Formulários processados: {$totalFormsProcessed}");
        $this->info("🔧 Questões atualizadas: {$totalQuestionsUpdated}");
        
        return 0;
    }
}