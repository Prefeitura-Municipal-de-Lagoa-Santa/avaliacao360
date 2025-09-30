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
                'grupos' => [
                    [
                        'name' => 'Competências Individuais',
                        'weight' => 50,
                        'perguntas' => [
                            'Demonstra proatividade em suas atividades?',
                            'Cumpre prazos e entregas estabelecidos?',
                            'Busca atualização e aprimoramento profissional?',
                            'Demonstra ética e respeito nas relações de trabalho?',
                            'Utiliza recursos de forma consciente e responsável?',
                        ],
                    ],
                    [
                        'name' => 'Competências Relacionais',
                        'weight' => 50,
                        'perguntas' => [
                            'Colabora com colegas de equipe em projetos e tarefas?',
                            'Apresenta boa comunicação (oral e escrita)?',
                            'Lida bem com situações de pressão ou adversidade?',
                            'Apresenta capacidade de resolver problemas?',
                            'Demonstra iniciativa para sugerir melhorias?',
                        ],
                    ],
                ]
            ],
            [
                'type' => 'gestor',
                'name' => 'Formulário Anual de Desempenho 2025 - Gestor',
                'grupos' => [
                    [
                        'name' => 'Gestão de Pessoas',
                        'weight' => 50,
                        'perguntas' => [
                            'Promove um ambiente de trabalho colaborativo?',
                            'Orienta e apoia o desenvolvimento dos membros da equipe?',
                            'Gerencia conflitos de forma eficaz?',
                            'Avalia o desempenho de subordinados de forma justa?',
                            'Utiliza feedback construtivo para aprimorar processos?',
                        ],
                    ],
                    [
                        'name' => 'Gestão de Resultados',
                        'weight' => 50,
                        'perguntas' => [
                            'Comunica metas e objetivos de forma clara?',
                            'Acompanha o desempenho da equipe regularmente?',
                            'Toma decisões de forma ética e transparente?',
                            'Garante o cumprimento de prazos e resultados?',
                            'Busca atualização e aprimoramento em gestão?',
                        ],
                    ],
                ]
            ],
            [
                'type' => 'chefia',
                'name' => 'Formulário Anual de Desempenho 2025 - Chefia',
                'grupos' => [
                    [
                        'name' => 'Estratégias e Processos',
                        'weight' => 50,
                        'perguntas' => [
                            'Define estratégias alinhadas aos objetivos institucionais?',
                            'Promove a integração entre diferentes setores?',
                            'Toma decisões assertivas diante de desafios?',
                            'Garante transparência nos processos da unidade?',
                            'Monitora resultados e propõe melhorias contínuas?',
                        ],
                    ],
                    [
                        'name' => 'Gestão de Pessoas e Inovação',
                        'weight' => 50,
                        'perguntas' => [
                            'Incentiva a inovação e a busca por soluções?',
                            'Desenvolve políticas para o crescimento da equipe?',
                            'Avalia o desempenho de subordinados de forma justa?',
                            'Lida com situações de crise de forma eficiente?',
                            'Promove um clima organizacional saudável?',
                        ],
                    ],
                ]
            ],
            [
                'type' => 'comissionado',
                'name' => 'Formulário Anual de Desempenho 2025 - Comissionado',
                'grupos' => [
                    [
                        'name' => 'Desempenho Institucional',
                        'weight' => 50,
                        'perguntas' => [
                            'Define estratégias alinhadas aos objetivos institucionais?',
                            'Promove a integração entre diferentes setores?',
                            'Garante transparência nos processos da unidade?',
                            'Monitora resultados e propõe melhorias contínuas?',
                            'Toma decisões assertivas diante de desafios?',
                        ],
                    ],
                    [
                        'name' => 'Gestão e Liderança',
                        'weight' => 50,
                        'perguntas' => [
                            'Incentiva a inovação e a busca por soluções?',
                            'Desenvolve políticas para o crescimento da equipe?',
                            'Avalia o desempenho de subordinados de forma justa?',
                            'Lida com situações de crise de forma eficiente?',
                            'Promove um clima organizacional saudável?',
                        ],
                    ],
                ]
            ],
        ];

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
                        'weight' => 10, // Ajuste o peso por pergunta se quiser ponderar internamente
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
}
