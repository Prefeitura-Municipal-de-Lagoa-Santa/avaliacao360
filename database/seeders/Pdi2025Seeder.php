<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class Pdi2025Seeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Definição das questões padrão para os formulários de PDI
        $gruposPdi = [
            [
                'name' => 'Pactuação de Metas e Desenvolvimento',
                'weight' => 100, // O PDI pode ter um único grupo de pactuação
                'perguntas' => [
                    'Quais são as principais metas de desenvolvimento (competências técnicas e comportamentais) para o próximo ciclo?',
                    'Quais ações, projetos ou tarefas específicas serão realizados para alcançar cada meta?',
                    'Quais cursos, treinamentos ou recursos serão necessários para apoiar o desenvolvimento?',
                    'Quais são os prazos de entrega ou marcos para as ações propostas?',
                    'Como o gestor irá acompanhar e apoiar o progresso do servidor?',
                ],
            ],
        ];

        // Definição dos diferentes tipos de formulários de PDI
        $formularios = [
            [
                'type' => 'pactuacao_servidor',
                'name' => 'Formulário de Pactuação PDI 2025 - Servidor',
                'grupos' => $gruposPdi,
            ],
            [
                'type' => 'pactuacao_gestor',
                'name' => 'Formulário de Pactuação PDI 2025 - Gestor',
                'grupos' => $gruposPdi,
            ],
            [
                'type' => 'pactuacao_comissionado',
                'name' => 'Formulário de Pactuação PDI 2025 - Comissionado',
                'grupos' => $gruposPdi,
            ],
        ];

        // Lógica para inserir os dados no banco de dados
        foreach ($formularios as $formulario) {
            $formId = DB::table('forms')->insertGetId([
                'type' => $formulario['type'],
                'year' => 2025,
                'name' => $formulario['name'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($formulario['grupos'] as $grupo) {
                $groupId = DB::table('group_questions')->insertGetId([
                    'form_id' => $formId,
                    'name' => $grupo['name'],
                    'weight' => $grupo['weight'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                foreach ($grupo['perguntas'] as $texto) {
                    DB::table('questions')->insert([
                        'group_question_id' => $groupId,
                        'text_content' => $texto,
                        'weight' => 0, // No PDI, as questões são descritivas, então o peso pode ser 0.
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
}