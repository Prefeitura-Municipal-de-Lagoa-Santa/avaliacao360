<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import { ArrowLeftIcon } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps({
  year: String,
  person: Object,
  form: Object,
  blocos: Array,         // Blocos para auto, chefia, gestor, etc
  blocoEquipe: Object,   // Bloco único para equipe, médias
  final_score: Number,
  calc_final: String,
});

// Função para nomes amigáveis de bloco
function nomeBloco(tipo) {
  tipo = (tipo || '').toLowerCase();
  if (tipo.includes('auto')) return 'Autoavaliação';
  if (tipo === 'chefia') return 'Chefia';
  if (tipo === 'gestor') return 'Chefe';
  if (tipo === 'servidor') return 'Chefe';
  if (tipo === 'comissionado') return 'Chefe';
  if (tipo === 'equipe') return 'Equipe';
  return tipo.charAt(0).toUpperCase() + tipo.slice(1);
}

function goBack() {
  window.history.back();
}

// Verifica se a nota foi zerada por falta de preenchimento
const notaZerada = computed(() => {
  return props.final_score === 0 && props.calc_final?.toLowerCase().includes('zerada');
});
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
      <div v-if="notaZerada" class="text-red-600 mt-1">
        A nota foi zerada devido à ausência de preenchimento por uma ou mais partes envolvidas.
      </div>
      <div v-else class="text-gray-500 mt-1">
        {{ calc_final }}
      </div>
    </div>

    <!-- Blocos: auto, chefia, gestor, etc -->
    <div v-for="bloco in blocos" :key="bloco.tipo" class="form-section mb-8">
      <h3 class="section-title">{{ nomeBloco(bloco.tipo) }} (Nota: <b>{{ bloco.nota }}</b>)</h3>
      <div v-if="bloco.evidencias" class="mb-2">
        <span class="font-semibold">Evidências:</span>
        <span class="whitespace-pre-line">{{ bloco.evidencias }}</span>
      </div>
      <table class="w-full bg-white border border-gray-200 rounded-lg">
        <thead>
          <tr class="bg-gray-50">
            <th class="py-2 px-4 text-left">Questão</th>
            <th class="py-2 px-4 text-center w-40">Nota</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="q in bloco.answers" :key="q.question">
            <td class="px-4 py-3">{{ q.question }}</td>
            <td class="px-4 py-3 text-center">
              <span class="inline-flex justify-center items-center bg-blue-100 text-blue-800 font-bold rounded-full text-lg w-14 h-10">
                {{ q.score ?? '-' }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Equipe: só média de cada pergunta, SEM identificação -->
    <div v-if="blocoEquipe && blocoEquipe.answers && blocoEquipe.answers.length" class="form-section mb-8">
      <h3 class="section-title">{{ nomeBloco(blocoEquipe.tipo) }} (Média das respostas | Nota: <b>{{ blocoEquipe.nota }}</b>)</h3>
      <div v-if="blocoEquipe.evidencias" class="mb-2">
        <span class="font-semibold">Evidências:</span>
        <span class="whitespace-pre-line">{{ blocoEquipe.evidencias }}</span>
      </div>
      <table class="w-full bg-white border border-gray-200 rounded-lg">
        <thead>
          <tr class="bg-gray-50">
            <th class="py-2 px-4 text-left">Questão</th>
            <th class="py-2 px-4 text-center w-40">Média</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="q in blocoEquipe.answers" :key="q.question">
            <td class="px-4 py-3">{{ q.question }}</td>
            <td class="px-4 py-3 text-center">
              <span class="inline-flex justify-center items-center bg-blue-100 text-blue-800 font-bold rounded-full text-lg w-14 h-10">
                {{ q.score_media ?? '-' }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
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
