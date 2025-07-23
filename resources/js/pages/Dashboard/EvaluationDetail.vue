<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import { ArrowLeftIcon } from 'lucide-vue-next';

const props = defineProps({
  year: String,
  person: Object,
  form: Object,
  auto: Object,
  chefia: Object,
  equipe: Object,
  final_score: Number,
  calc_final: String,
});

function goBack() {
  window.history.back();
}

// Função auxiliar para buscar respostas
function getAnswers(request) {
  return request && request.answers ? request.answers : [];
}
function getScore(answers, question) {
  const answer = answers.find(ans => ans.question_id === question.id);
  return answer && answer.score !== undefined ? answer.score : '-';
}
</script>

<template>
  <Head title="Detalhes do Ciclo Anual" />
  <DashboardLayout :pageTitle="`Detalhes do Ciclo ${year}`">
    <div class="flex justify-between items-center border-b pb-4 mb-6">
      <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">
        Detalhes do Ciclo {{ year }}
      </h1>
      <button @click="goBack" class="back-btn">
        <ArrowLeftIcon class="size-4 mr-2" />
        Voltar
      </button>
    </div>

    <!-- Nota Final Consolidada -->
    <div class="mb-8 p-4 rounded bg-blue-50 border border-blue-100 shadow-sm">
      <div class="text-xl font-bold mb-1">
        Nota Final do Ciclo: <span class="text-blue-600">{{ final_score }}</span>
      </div>
      <div class="text-gray-500">Cálculo: {{ calc_final }}</div>
    </div>

    <!-- Autoavaliação -->
    <div v-if="auto && auto.request" class="form-section mb-8">
      <h3 class="section-title">Autoavaliação</h3>
      <div class="mb-2">Nota: <b>{{ auto.nota }}</b></div>
      <div v-if="auto.request.answers && form && form.group_questions">
        <div v-for="group in form.group_questions" :key="'auto-'+group.id" class="mb-6">
          <h4 class="text-lg font-semibold mb-2">{{ group.name }}</h4>
          <table class="w-full bg-white border border-gray-200 rounded-lg">
            <thead>
              <tr class="bg-gray-50">
                <th class="py-2 px-4 text-left">Questão</th>
                <th class="py-2 px-4 text-center w-40">Nota</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="question in group.questions" :key="question.id" class="border-t">
                <td class="px-4 py-3 align-top">{{ question.text_content }}</td>
                <td class="px-4 py-3 text-center">
                  <span class="inline-flex justify-center items-center bg-blue-100 text-blue-800 font-bold rounded-full text-lg w-14 h-10">
                    {{ getScore(auto.request.answers, question) }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Chefia -->
    <div v-if="chefia && chefia.request" class="form-section mb-8">
      <h3 class="section-title">Chefia</h3>
      <div class="mb-2">Nota: <b>{{ chefia.nota }}</b></div>
      <div v-if="chefia.request.answers && form && form.group_questions">
        <div v-for="group in form.group_questions" :key="'chefia-'+group.id" class="mb-6">
          <h4 class="text-lg font-semibold mb-2">{{ group.name }}</h4>
          <table class="w-full bg-white border border-gray-200 rounded-lg">
            <thead>
              <tr class="bg-gray-50">
                <th class="py-2 px-4 text-left">Questão</th>
                <th class="py-2 px-4 text-center w-40">Nota</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="question in group.questions" :key="question.id" class="border-t">
                <td class="px-4 py-3 align-top">{{ question.text_content }}</td>
                <td class="px-4 py-3 text-center">
                  <span class="inline-flex justify-center items-center bg-blue-100 text-blue-800 font-bold rounded-full text-lg w-14 h-10">
                    {{ getScore(chefia.request.answers, question) }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Equipe: média das respostas, sem mostrar avaliadores -->
    <div v-if="equipe && equipe.answers && equipe.answers.length" class="form-section mb-8">
      <h3 class="section-title">Equipe (média das respostas)</h3>
      <table class="w-full bg-white border border-gray-200 rounded-lg">
        <thead>
          <tr class="bg-gray-50">
            <th class="py-2 px-4 text-left">Questão</th>
            <th class="py-2 px-4 text-center w-40">Média</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="q in equipe.answers" :key="q.question">
            <td class="px-4 py-3">{{ q.question }}</td>
            <td class="px-4 py-3 text-center">
              <span class="inline-flex justify-center items-center bg-blue-100 text-blue-800 font-bold rounded-full text-lg w-14 h-10">
                {{ q.score_media ?? '-' }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
      <div class="mt-4 text-right font-semibold">
        Média ponderada da equipe: <span class="text-blue-600 text-lg">{{ equipe.nota ?? '-' }}</span>
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
