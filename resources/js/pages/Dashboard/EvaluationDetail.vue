<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import * as icons from 'lucide-vue-next';
import { route } from 'ziggy-js';

const props = defineProps<{
  year: string;
  person: {
    id: number;
    name: string;
  };
  form?: {
    id: number;
    name: string;
  };
  blocos: Array<{
    tipo: string;
    nota: number | null;
    answers: Array<{ question: string; score: number | null; weight: number }>;
    evidencias?: string;
    evaluator_name?: string;
    team_info?: {
      total_members: number;
      completed_members: number;
      has_team_evaluation: boolean;
    } | null;
  }>;
  blocoEquipe?: {
    tipo: string;
    nota: number | null;
    answers: Array<{ question: string; score_media: number | null; weight: number }>;
    team_info?: {
      total_members: number;
      completed_members: number;
      has_team_evaluation: boolean;
    } | null;
  } | null;
  final_score: number;
  is_commission?: boolean;
}>();

function goBack() {
  router.get(route('evaluations.history'));
}

function getEvaluationTypeLabel(type: string): string {
  const types: Record<string, string> = {
    'autoavaliaçãoGestor': 'Autoavaliação Gestor',
    'autoavaliaçãoComissionado': 'Autoavaliação Comissionado',
    'autoavaliação': 'Autoavaliação',
    'auto': 'Autoavaliação',
    'gestor': 'Avaliação do Chefe',
    'comissionado': 'Avaliação do Chefe',
    'servidor': 'Avaliação do Servidor',
    'par': 'Avaliação entre Pares',
    'chefia': 'Avaliação da Equipe',
    'equipe': 'Avaliação da Equipe',
  };
  
  return types[type] || type.charAt(0).toUpperCase() + type.slice(1);
}

function getEvaluationTypeColor(type: string): string {
  const colors: Record<string, string> = {
    'auto': 'bg-blue-500',
    'autoavaliação': 'bg-blue-500',
    'autoavaliaçãoGestor': 'bg-blue-500',
    'autoavaliaçãoComissionado': 'bg-blue-500',
    'gestor': 'bg-purple-500',
    'comissionado': 'bg-green-500',
    'servidor': 'bg-orange-500',
    'par': 'bg-teal-500',
    'subordinado': 'bg-pink-500',
    'chefia': 'bg-indigo-500',
    'equipe': 'bg-indigo-500',
  };
  
  return colors[type] || 'bg-gray-500';
}

function getScoreColor(score: number | null): string {
  if (score === null) return 'text-gray-400';
  if (score >= 4) return 'text-green-600';
  if (score >= 3) return 'text-yellow-600';
  return 'text-red-600';
}

function getScoreIcon(score: number | null) {
  if (score === null) return icons.Minus;
  if (score >= 4) return icons.TrendingUp;
  if (score >= 3) return icons.Minus;
  return icons.TrendingDown;
}

// Todas as avaliações incluindo equipe
const allEvaluations = computed(() => {
  const evaluations = [...props.blocos];
  if (props.blocoEquipe) {
    evaluations.push({
      tipo: 'Equipe',
      nota: props.blocoEquipe.nota,
      answers: props.blocoEquipe.answers.map(a => ({
        question: a.question,
        score: a.score_media,
        weight: a.weight
      })),
      evidencias: undefined,
      evaluator_name: 'Equipe',
      team_info: props.blocoEquipe.team_info
    });
  }
  return evaluations;
});

const completedEvaluations = computed(() => 
  allEvaluations.value.filter(e => e.nota !== null)
);

function formatNota(value: number | null): string {
  if (value === null || value === undefined) return '—';
  return (Math.round(value * 10) / 10).toFixed(1).replace('.', ',');
}
</script>

<template>
  <Head title="Detalhes da Avaliação" />
  <DashboardLayout page-title="Detalhes da Avaliação">
    <div class="max-w-7xl mx-auto space-y-6">
      <!-- Cabeçalho -->
      <div class="bg-white p-6 rounded-lg shadow-sm border">
        <div class="flex justify-between items-start mb-4">
          <div>
            <h1 class="text-2xl font-bold text-gray-900 mb-2">
              Detalhes da Avaliação {{ year }}
            </h1>
            <div class="flex items-center gap-4 text-sm text-gray-600">
              <span class="flex items-center gap-1">
                <icons.User class="w-4 h-4" />
                <strong>Avaliado:</strong> {{ person.name }}
              </span>
              <span class="flex items-center gap-1">
                <icons.Calendar class="w-4 h-4" />
                <strong>Ano:</strong> {{ year }}
              </span>
              <span v-if="form" class="flex items-center gap-1">
                <icons.FileText class="w-4 h-4" />
                <strong>Formulário:</strong> {{ form.name }}
              </span>
            </div>
          </div>
          <button
            @click="goBack"
            class="inline-flex items-center px-4 py-2 border text-sm font-medium rounded-md shadow-sm text-white bg-purple-600 border-purple-600 hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 transition-colors"
          >
            <icons.ArrowLeft class="w-4 h-4 mr-2" />
            Voltar
          </button>
        </div>

        <!-- Resumo geral -->
        <div class="bg-gray-50 rounded-lg p-4 border">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="text-center">
              <div class="text-2xl font-bold text-gray-800">{{ allEvaluations.length }}</div>
              <div class="text-sm text-gray-600">Total de Avaliações</div>
            </div>
            <div class="text-center">
              <div class="text-2xl font-bold text-gray-800">
                {{ completedEvaluations.length }}
              </div>
              <div class="text-sm text-gray-600">Avaliações Completas</div>
            </div>
            <div class="text-center">
              <div class="text-2xl font-bold text-blue-600">
                {{ final_score }}
              </div>
              <div class="text-sm text-gray-600">Nota Final</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Lista de avaliações -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div 
          v-for="evaluation in allEvaluations" 
          :key="evaluation.tipo"
          class="bg-white rounded-lg shadow-sm border"
        >
          <!-- Header da avaliação -->
          <div 
            class="text-white p-4 rounded-t-lg"
            :class="getEvaluationTypeColor(evaluation.tipo)"
          >
            <div class="flex justify-between items-start">
              <div>
                <h3 class="text-lg font-semibold flex items-center gap-2">
                  {{ getEvaluationTypeLabel(evaluation.tipo) }}
                  <icons.Users 
                    v-if="evaluation.tipo === 'Equipe'" 
                    class="w-4 h-4 text-white/80" 
                    title="Avaliação de Equipe"
                  />
                </h3>
                <p class="text-sm opacity-90 mt-1">
                  {{ evaluation.tipo === 'Equipe' ? 
                      'Média consolidada da equipe' : 
                      `Avaliador: ${evaluation.evaluator_name || 'Não informado'}` 
                  }}
                </p>
              </div>
              <div class="text-right">
                <div class="text-2xl font-bold">
                  {{ evaluation.nota !== null ? formatNota(evaluation.nota) : '—' }}
                </div>
                <div class="text-xs opacity-75">
                  {{ evaluation.answers.filter(a => a.score !== null).length }}/{{ evaluation.answers.length }} questões
                </div>
              </div>
            </div>
          </div>

          <!-- Conteúdo da avaliação -->
          <div class="p-4">
            <!-- Status da avaliação -->
            <div class="mb-4">
              <div class="flex items-center justify-between text-sm">
                <span class="text-gray-600">
                  {{ evaluation.tipo === 'Equipe' ? 'Avaliação da Equipe' : 'Progresso da Avaliação' }}
                </span>
                <span class="font-medium">
                  {{ Math.round((evaluation.answers.filter(a => a.score !== null).length / evaluation.answers.length) * 100) }}%
                </span>
              </div>
              <div class="w-full bg-gray-200 rounded-full h-2 mt-1">
                <div 
                  class="h-2 rounded-full transition-all"
                  :class="evaluation.answers.filter(a => a.score !== null).length === evaluation.answers.length ? 'bg-green-500' : 'bg-yellow-500'"
                  :style="{ width: (evaluation.answers.filter(a => a.score !== null).length / evaluation.answers.length) * 100 + '%' }"
                ></div>
              </div>
              
              <!-- Informações específicas para avaliação de equipe -->
              <div v-if="evaluation.tipo === 'Equipe'" class="mt-3 p-3 bg-blue-50 rounded-lg border border-blue-200">
                <div class="flex items-center gap-2 text-blue-800">
                  <icons.Users class="w-4 h-4" />
                  <span class="text-sm font-medium">
                    <template v-if="evaluation.team_info">
                      Avaliação consolidada de {{ evaluation.team_info.completed_members }}/{{ evaluation.team_info.total_members }} membros da equipe
                    </template>
                    <template v-else>
                      Avaliação consolidada da equipe
                    </template>
                  </span>
                </div>
                <div v-if="evaluation.team_info" class="mt-2">
                  <div class="flex items-center justify-between text-xs text-blue-600">
                    <span>Progresso das avaliações da equipe</span>
                    <span class="font-medium">
                      {{ Math.round((evaluation.team_info.completed_members / evaluation.team_info.total_members) * 100) }}%
                    </span>
                  </div>
                  <div class="w-full bg-blue-200 rounded-full h-2 mt-1">
                    <div 
                      class="h-2 rounded-full transition-all"
                      :class="evaluation.team_info.completed_members === evaluation.team_info.total_members ? 'bg-green-500' : 'bg-yellow-500'"
                      :style="{ width: (evaluation.team_info.completed_members / evaluation.team_info.total_members) * 100 + '%' }"
                    ></div>
                  </div>
                </div>
                <p class="text-xs text-blue-600 mt-2">
                  A média apresentada representa a consolidação das avaliações de todos os membros da equipe.
                </p>
              </div>
            </div>

            <!-- Questões e notas -->
            <div class="space-y-3">
              <h4 class="font-medium text-gray-800 flex items-center gap-2">
                <icons.BarChart3 class="w-4 h-4" />
                Notas por Questão
              </h4>
              
              <div class="space-y-2 max-h-64 overflow-y-auto">
                <div 
                  v-for="(answer, index) in evaluation.answers" 
                  :key="index"
                  class="flex justify-between items-start py-2 px-3 bg-gray-50 rounded border"
                >
                  <div class="flex-1 pr-3">
                    <p class="text-sm text-gray-700 leading-tight">
                      {{ answer.question }}
                    </p>
                  </div>
                  <div class="flex items-center gap-1">
                    <component 
                      :is="getScoreIcon(answer.score)" 
                      class="w-4 h-4"
                      :class="getScoreColor(answer.score)"
                    />
                    <span 
                      class="text-lg font-bold min-w-[24px] text-center"
                      :class="getScoreColor(answer.score)"
                    >
                      {{ answer.score ?? '—' }}
                    </span>
                  </div>
                </div>
              </div>

              <!-- Estatísticas da avaliação -->
              <div v-if="evaluation.nota !== null" class="mt-4 pt-4 border-t border-gray-200">
                <div class="grid grid-cols-2 gap-4 text-sm">
                  <div class="text-center">
                    <div 
                      class="text-xl font-bold"
                      :class="getScoreColor(evaluation.nota)"
                    >
                      {{ formatNota(evaluation.nota) }}
                    </div>
                    <div class="text-gray-600">Nota Total</div>
                  </div>
                  <div class="text-center">
                    <div class="text-xl font-bold text-gray-800">
                      {{ evaluation.answers.filter(a => a.score !== null && a.score >= 4).length }}
                    </div>
                    <div class="text-gray-600">Notas ≥ 4</div>
                  </div>
                </div>
              </div>

              <!-- Evidências -->
              <div v-if="evaluation.tipo !== 'Equipe' && evaluation.evidencias" class="mt-4 pt-4 border-t border-gray-200">
                <h4 class="font-medium text-gray-800 flex items-center gap-2 mb-3">
                  <icons.FileText class="w-4 h-4" />
                  Evidências
                </h4>
                <div class="bg-gray-50 rounded-lg p-3 border">
                  <p class="text-sm text-gray-700 leading-relaxed whitespace-pre-line">
                    {{ evaluation.evidencias }}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Seção de Nota Final -->
      <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-blue-500">
        <div class="flex items-center justify-between">
          <div>
            <h3 class="text-lg font-semibold text-gray-800 mb-2">
              Nota Final
            </h3>
          </div>
          <div class="text-right">
            <div class="text-4xl font-bold text-blue-600">
              {{ final_score }}
            </div>
            <div class="text-sm text-gray-500">Nota Final</div>
          </div>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<style scoped>
.form-section {
  margin-bottom: 2rem;
}
.section-title {
  font-size: 1.125rem;
  font-weight: bold;
  color: #374151;
  margin-bottom: 0.5rem;
}
</style>
