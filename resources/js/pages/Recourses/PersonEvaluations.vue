<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import * as icons from 'lucide-vue-next';
import { route } from 'ziggy-js';

const props = defineProps<{
  recourse: {
    id: number;
    status: string;
    person: { name: string };
    evaluation: {
      year: string;
      form_name: string;
    };
  };
  evaluations: Array<{
    id: number;
    type: string;
    evaluator_name: string;
    is_team_evaluation?: boolean;
    team_members_count?: number;
    evidencias?: string;
    answers: Array<{ question: string; score: number | null }>;
    average: number | null;
    total_score?: number;
    total_questions: number;
    answered_questions: number;
  }>;
  media_geral?: number | null;
}>();

function goBack() {
  router.get(route('recourses.review', props.recourse.id));
}

function getEvaluationTypeLabel(type: string): string {
  const types: Record<string, string> = {
    'autoavaliaçãoGestor': 'Autoavaliação Gestor',
    'autoavaliaçãoComissionado': 'Autoavaliação Comissionado',
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

// Verifica se o recurso foi deferido
const isDeferido = computed(() => props.recourse.status === 'respondido');

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
</script>

<template>
  <Head title="Todas as Avaliações" />
  <DashboardLayout page-title="Análise Completa de Avaliações">
    <div class="max-w-7xl mx-auto space-y-6">
      <!-- Cabeçalho -->
      <div class="bg-white p-6 rounded-lg shadow-sm border">
        <div class="flex justify-between items-start mb-4">
          <div>
            <h1 class="text-2xl font-bold text-gray-900 mb-2">
              Análise Completa das Avaliações
            </h1>
            <div class="flex items-center gap-4 text-sm text-gray-600">
              <span class="flex items-center gap-1">
                <icons.User class="w-4 h-4" />
                <strong>Avaliado:</strong> {{ recourse.person.name }}
              </span>
              <span class="flex items-center gap-1">
                <icons.Calendar class="w-4 h-4" />
                <strong>Ano:</strong> {{ recourse.evaluation.year }}
              </span>
              <span class="flex items-center gap-1">
                <icons.FileText class="w-4 h-4" />
                <strong>Formulário:</strong> {{ recourse.evaluation.form_name }}
              </span>
            </div>
          </div>
          <button 
            @click="goBack" 
            class="inline-flex items-center px-4 py-2 text-sm text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded-lg transition-colors"
          >
            <icons.ArrowLeft class="w-4 h-4 mr-2" />
            Voltar para Recurso
          </button>
        </div>

        <!-- Resumo geral -->
        <div class="bg-gray-50 rounded-lg p-4 border">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="text-center">
              <div class="text-2xl font-bold text-gray-800">{{ evaluations.length }}</div>
              <div class="text-sm text-gray-600">Total de Avaliações</div>
            </div>
            <div class="text-center">
              <div class="text-2xl font-bold text-gray-800">
                {{ evaluations.filter(e => e.average !== null).length }}
              </div>
              <div class="text-sm text-gray-600">Avaliações Completas</div>
            </div>
            <div class="text-center">
              <div class="text-2xl font-bold text-blue-600">
                {{ media_geral !== null && media_geral !== undefined ? media_geral.toFixed(1) : '—' }}
              </div>
              <div class="text-sm text-gray-600">Média Geral Ponderada</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Lista de avaliações -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div 
          v-for="evaluation in evaluations" 
          :key="evaluation.id"
          class="bg-white rounded-lg shadow-sm border"
        >
          <!-- Header da avaliação -->
          <div 
            class="text-white p-4 rounded-t-lg"
            :class="getEvaluationTypeColor(evaluation.type)"
          >
            <div class="flex justify-between items-start">
              <div>
                <h3 class="text-lg font-semibold flex items-center gap-2">
                  {{ getEvaluationTypeLabel(evaluation.type) }}
                  <icons.Users 
                    v-if="evaluation.is_team_evaluation" 
                    class="w-4 h-4 text-white/80" 
                    title="Avaliação de Equipe"
                  />
                </h3>
                <p class="text-sm opacity-90 mt-1">
                  {{ evaluation.is_team_evaluation ? 
                      `${evaluation.team_members_count} membros da equipe` : 
                      `Avaliador: ${evaluation.evaluator_name}` 
                  }}
                </p>
              </div>
              <div class="text-right">
                <div class="text-2xl font-bold">
                  {{ evaluation.average !== null ? evaluation.average : '—' }}
                </div>
                <div class="text-xs opacity-75">
                  {{ evaluation.answered_questions }}/{{ evaluation.total_questions }} questões
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
                  {{ evaluation.is_team_evaluation ? 'Avaliação da Equipe' : 'Progresso da Avaliação' }}
                </span>
                <span class="font-medium">
                  {{ Math.round((evaluation.answered_questions / evaluation.total_questions) * 100) }}%
                </span>
              </div>
              <div class="w-full bg-gray-200 rounded-full h-2 mt-1">
                <div 
                  class="h-2 rounded-full transition-all"
                  :class="evaluation.answered_questions === evaluation.total_questions ? 'bg-green-500' : 'bg-yellow-500'"
                  :style="{ width: (evaluation.answered_questions / evaluation.total_questions) * 100 + '%' }"
                ></div>
              </div>
              
              <!-- Informações específicas para avaliação de equipe -->
              <div v-if="evaluation.is_team_evaluation" class="mt-3 p-3 bg-blue-50 rounded-lg border border-blue-200">
                <div class="flex items-center gap-2 text-blue-800">
                  <icons.Users class="w-4 h-4" />
                  <span class="text-sm font-medium">
                    Avaliação consolidada de {{ evaluation.team_members_count }} membros da equipe
                  </span>
                </div>
                <p class="text-xs text-blue-600 mt-1">
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
              <div v-if="evaluation.average !== null" class="mt-4 pt-4 border-t border-gray-200">
                <div class="grid grid-cols-3 gap-4 text-sm">
                  <div class="text-center">
                    <div 
                      class="text-xl font-bold"
                      :class="getScoreColor(evaluation.average)"
                    >
                      {{ evaluation.average }}
                    </div>
                    <div class="text-gray-600">Média</div>
                  </div>
                  <div class="text-center">
                    <div class="text-xl font-bold text-blue-600">
                      {{ evaluation.total_score || 0 }}
                    </div>
                    <div class="text-gray-600">Total</div>
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
              <div v-if="!evaluation.is_team_evaluation" class="mt-4 pt-4 border-t border-gray-200">
                <h4 class="font-medium text-gray-800 flex items-center gap-2 mb-3">
                  <icons.FileText class="w-4 h-4" />
                  Evidências
                </h4>
                <div class="bg-gray-50 rounded-lg p-3 border">
                  <p v-if="evaluation.evidencias && evaluation.evidencias.trim()" 
                     class="text-sm text-gray-700 leading-relaxed whitespace-pre-line">
                    {{ evaluation.evidencias }}
                  </p>
                  <p v-else class="text-sm text-gray-500 italic">
                    Nenhuma evidência fornecida.
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Seção de Média Geral Ponderada -->
    <div v-if="media_geral !== null" class="mt-8">
      <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-blue-500">
        <div class="flex items-center justify-between">
          <div>
            <h3 class="text-lg font-semibold text-gray-800 mb-2">
              Média Geral Ponderada
            </h3>
            <div v-if="isDeferido" class="mb-3 p-3 bg-green-50 border border-green-200 rounded-md">
              <div class="flex items-center gap-2 text-green-800">
                <icons.CheckCircle class="w-5 h-5" />
                <span class="font-medium">Recurso DEFERIDO</span>
              </div>
              <p class="text-sm text-green-700 mt-1">
                A nota do chefe foi anulada. O cálculo considera apenas a autoavaliação{{ evaluations.some(e => e.is_team_evaluation) ? ' e a avaliação da equipe' : '' }}.
              </p>
            </div>
            <p class="text-sm text-gray-600">
              {{ isDeferido 
                ? (evaluations.some(e => e.is_team_evaluation) 
                  ? 'Cálculo: 75% Autoavaliação + 25% Equipe' 
                  : 'Cálculo: 100% Autoavaliação')
                : (evaluations.some(e => e.is_team_evaluation) 
                  ? 'Cálculo: 50% Chefe + 25% Equipe + 25% Autoavaliação' 
                  : 'Cálculo: 70% Chefe + 30% Autoavaliação')
              }}
            </p>
          </div>
          <div class="text-right">
            <div 
              class="text-4xl font-bold"
              :class="{
                'text-blue-600': !isDeferido,
                'text-green-600': isDeferido
              }"
            >
              {{ media_geral }}
            </div>
            <div class="text-sm text-gray-500">Nota Final</div>
          </div>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>
