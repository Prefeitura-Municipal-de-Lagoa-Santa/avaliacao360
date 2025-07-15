<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\JobFunction;

class JobFunctionSeeder extends Seeder
{
    public function run()
    {
        $data = [
            ['id' => 28, 'name' => 'PREFEITO MUNICIPAL', 'type' => 'chefe', 'is_manager' => true, 'code' => 123],
            ['id' => 29, 'name' => 'VICE PREFEITO MUNICIPAL', 'type' => 'chefe', 'is_manager' => true, 'code' => 124],
            ['id' => 2, 'name' => 'CHEFE DE DEPARTAMENTO', 'type' => 'chefe', 'is_manager' => true, 'code' => 127],
            ['id' => 14, 'name' => 'GERENTE DE SETOR DE VIGILANCIA EPIDEMIOLOGICA', 'type' => 'chefe', 'is_manager' => true, 'code' => 281],
            ['id' => 1, 'name' => 'COORDENADOR MUNICIPAL', 'type' => 'chefe', 'is_manager' => true, 'code' => 303],
            ['id' => 7, 'name' => 'DIRETOR ESCOLAR I', 'type' => 'chefe', 'is_manager' => true, 'code' => 339],
            ['id' => 5, 'name' => 'DIRETOR ESCOLAR II', 'type' => 'chefe', 'is_manager' => true, 'code' => 340],
            ['id' => 13, 'name' => 'DIRETOR ESCOLAR III', 'type' => 'chefe', 'is_manager' => true, 'code' => 341],
            ['id' => 6, 'name' => 'VICE DIRETOR ESCOLAR I', 'type' => 'chefe', 'is_manager' => true, 'code' => 342],
            ['id' => 8, 'name' => 'VICE DIRETOR ESCOLAR II', 'type' => 'chefe', 'is_manager' => true, 'code' => 343],
            ['id' => 12, 'name' => 'VICE DIRETOR ESCOLAR III', 'type' => 'chefe', 'is_manager' => true, 'code' => 344],
            ['id' => 9, 'name' => 'DIRETOR MUNICIPAL', 'type' => 'chefe', 'is_manager' => true, 'code' => 350],
            ['id' => 23, 'name' => 'SECRETARIO MUNICIPAL', 'type' => 'chefe', 'is_manager' => true, 'code' => 380],
            ['id' => 30, 'name' => 'CONTROLADOR-GERAL DO MUNICIPIO', 'type' => 'chefe', 'is_manager' => true, 'code' => 381],
            ['id' => 4, 'name' => 'CHEFE DE APOIO AO GABINETE', 'type' => 'chefe', 'is_manager' => true, 'code' => 385],
            ['id' => 3, 'name' => 'CHEFE DA ASSESSORIA ESTRATEGICA', 'type' => 'chefe', 'is_manager' => true, 'code' => 386],
            ['id' => 20, 'name' => 'AGENTE DE COMUNICACAO', 'type' => 'chefe', 'is_manager' => true, 'code' => 387],
            ['id' => 16, 'name' => 'AGENTE DE DESENVOLVIMENTO ECONOMICO SUSTENTAVEL', 'type' => 'chefe', 'is_manager' => true, 'code' => 388],
            ['id' => 10, 'name' => 'GESTOR OPERACIONAL', 'type' => 'chefe', 'is_manager' => true, 'code' => 389],
            ['id' => 25, 'name' => 'GESTOR DE UNIDADE DE SERVICOS SOCIAIS', 'type' => 'chefe', 'is_manager' => true, 'code' => 390],
            ['id' => 22, 'name' => 'ASSESSOR JURIDICO', 'type' => 'chefe', 'is_manager' => true, 'code' => 391],
            ['id' => 19, 'name' => 'ASSESSOR I', 'type' => 'comissionado', 'is_manager' => false, 'code' => 392],
            ['id' => 21, 'name' => 'ASSESSOR II', 'type' => 'comissionado', 'is_manager' => false, 'code' => 393],
            ['id' => 18, 'name' => 'REFERENCIA TECNICA', 'type' => 'chefe', 'is_manager' => true, 'code' => 394],
            ['id' => 17, 'name' => 'AGENTE DE PLANEJAMENTO', 'type' => 'chefe', 'is_manager' => true, 'code' => 395],
            ['id' => 26, 'name' => 'COORDENADOR DE PROTECAO E DEFESA CIVIL', 'type' => 'chefe', 'is_manager' => true, 'code' => 403],
            ['id' => 27, 'name' => 'ASSESSOR DE COMUNICACAO', 'type' => 'comissionado', 'is_manager' => false, 'code' => 411],
            ['id' => 15, 'name' => 'CORREGEDOR-GERAL DO MUNICIPIO', 'type' => 'chefe', 'is_manager' => true, 'code' => 412],
            ['id' => 24, 'name' => 'CHEFE DE GABINETE', 'type' => 'chefe', 'is_manager' => true, 'code' => 413],
            ['id' => 11, 'name' => 'OUVIDOR-GERAL DO MUNICIPIO', 'type' => 'chefe', 'is_manager' => true, 'code' => 415],
        ];


        foreach ($data as $item) {
            JobFunction::updateOrCreate(
                ['id' => $item['id']], // ou ['code' => $item['code']] se preferir não fixar o id
                $item
            );
        }
    }
}
