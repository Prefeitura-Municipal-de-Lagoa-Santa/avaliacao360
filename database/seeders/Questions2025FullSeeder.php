<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class Questions2025FullSeeder extends Seeder
{
    public function run(): void
    {
        $formularios = [
            [
                'type' => 'servidor',
                'name' => 'Formulário Anual de Desempenho 2025 - Servidor',
                'perguntas' => [
                    'Demonstra proatividade em suas atividades?',
                    'Colabora com colegas de equipe em projetos e tarefas?',
                    'Cumpre prazos e entregas estabelecidos?',
                    'Apresenta boa comunicação (oral e escrita)?',
                    'Busca atualização e aprimoramento profissional?',
                    'Demonstra ética e respeito nas relações de trabalho?',
                    'Lida bem com situações de pressão ou adversidade?',
                    'Apresenta capacidade de resolver problemas?',
                    'Utiliza recursos de forma consciente e responsável?',
                    'Demonstra iniciativa para sugerir melhorias?',
                ]
            ],
            [
                'type' => 'gestor',
                'name' => 'Formulário Anual de Desempenho 2025 - Gestor',
                'perguntas' => [
                    'Promove um ambiente de trabalho colaborativo?',
                    'Comunica metas e objetivos de forma clara?',
                    'Acompanha o desempenho da equipe regularmente?',
                    'Toma decisões de forma ética e transparente?',
                    'Orienta e apoia o desenvolvimento dos membros da equipe?',
                    'Gerencia conflitos de forma eficaz?',
                    'Garante o cumprimento de prazos e resultados?',
                    'Utiliza feedback construtivo para aprimorar processos?',
                    'Demonstra liderança em situações de pressão?',
                    'Busca atualização e aprimoramento em gestão?',
                ]
            ],
            [
                'type' => 'chefia',
                'name' => 'Formulário Anual de Desempenho 2025 - Chefia',
                'perguntas' => [
                    'Define estratégias alinhadas aos objetivos institucionais?',
                    'Promove a integração entre diferentes setores?',
                    'Toma decisões assertivas diante de desafios?',
                    'Garante transparência nos processos da unidade?',
                    'Monitora resultados e propõe melhorias contínuas?',
                    'Incentiva a inovação e a busca por soluções?',
                    'Desenvolve políticas para o crescimento da equipe?',
                    'Avalia o desempenho de subordinados de forma justa?',
                    'Lida com situações de crise de forma eficiente?',
                    'Promove um clima organizacional saudável?',
                ]
            ],
        ];

        foreach ($formularios as $formulario) {
            // 1. Cria o formulário
            $formId = DB::table('forms')->insertGetId([
                'type' => $formulario['type'],
                'year' => 2025,
                'name' => $formulario['name'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 2. Cria um grupo de perguntas (você pode criar mais se quiser)
            $groupId = DB::table('group_questions')->insertGetId([
                'form_id' => $formId,
                'name' => 'Perguntas Gerais',
                'weight' => 100.00,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 3. Cria as 10 perguntas para este grupo
            foreach ($formulario['perguntas'] as $texto) {
                DB::table('questions')->insert([
                    'group_question_id' => $groupId,
                    'text_content' => $texto,
                    'weight' => 10,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
