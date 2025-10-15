<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
// Corrigido: Importa-se o ícone diretamente para melhor performance
import { ArrowLeftIcon } from 'lucide-vue-next';

// As props recebem os dados do formulário, incluindo os grupos e suas questões.
const props = defineProps<{
  form: {
    id: number;
    name: string;
    year: string;
    type: string;
    group_questions: Array<{
      id: number;
      name: string;
      weight: number | null;
      questions: Array<{
        id: number;
        text_content: string;
        weight: number | null;
      }>;
    }>;
  };
}>();

const isPdiForm = computed(() => {
  const pdiTypes = ['pactuacao_servidor', 'pactuacao_comissionado', 'pactuacao_gestor'];
  return pdiTypes.includes(props.form.type);
});

// Função para formatar peso com decimais quando necessário (robusta contra valores não numéricos)
const formatWeight = (weight: number | string | null | undefined) => {
  if (weight === null || weight === undefined || weight === '') {
    return '0';
  }

  // Se vier como string (ex: '10', '10,5', '20%') normaliza
  let raw = weight;
  if (typeof raw === 'string') {
    raw = raw.replace('%', '').replace(',', '.').trim();
  }

  const num = typeof raw === 'number' ? raw : Number(raw);
  if (isNaN(num)) {
    return '0';
  }

  // Inteiro?
  if (Number.isInteger(num)) {
    return num.toString();
  }

  // Limita a 2 casas, removendo zeros supérfluos e usa vírgula
  const fixed = num.toFixed(2); // string
  const trimmed = fixed.replace(/\.00$/, '').replace(/(\.[1-9])0$/, '$1');
  return trimmed.replace('.', ',');
};

// Função para navegar para a página anterior.
function goBack() {
  window.history.back();
}
</script>

<template>
  <Head :title="`Visualizar: ${props.form.name}`" />
  <DashboardLayout :pageTitle="`Visualizar Formulário`">

    <div class="max-w-4xl mx-auto">
      <div class="detail-page-header">
        <div>
          <h2 class="text-2xl font-bold text-gray-800">{{ props.form.name }}</h2>
          <p class="text-sm text-gray-500">Ano de referência: {{ props.form.year }}</p>
        </div>
        <button @click="goBack" class="back-btn">
          <ArrowLeftIcon class="size-4 mr-2" />
          Voltar
        </button>
      </div>

      <div v-if="props.form.group_questions && props.form.group_questions.length > 0" class="space-y-6 mt-6">
        <div v-for="group in props.form.group_questions" :key="group.id" class="group-container-view">

            <div class="group-header-view">
                <h3 class="flex-grow font-semibold text-gray-800">{{ group.name }}</h3>
                <span v-if="!isPdiForm" class="font-bold text-indigo-500 bg-white px-3 py-1 rounded-full text-sm ml-4">
                    Peso do Grupo: {{ formatWeight(group.weight) }}%
                </span>
            </div>

            <div class="space-y-3 p-4">
                 <div class="flex items-center pb-2 border-b mb-2 text-sm text-gray-500 font-medium">
                    <div :class="isPdiForm ? 'w-full' : 'w-10/12'">Questão</div>
                    <div v-if="!isPdiForm" class="w-2/12 text-center">Peso Final</div>
                </div>
                
                <div v-for="question in group.questions" :key="question.id" class="question-view-row">
                    <div :class="isPdiForm ? 'w-full' : 'w-10/12'">
                        <p class="text-gray-800">{{ question.text_content }}</p>
                    </div>
                    <div v-if="!isPdiForm" class="w-2/12 text-center">
                        <span class="font-medium text-gray-700">{{ formatWeight(question.weight) }}%</span>
                    </div>
                </div>
            </div>
        </div>
      </div>

      <div v-else class="text-center text-gray-500 py-8 form-creator-container">
          <p>Este formulário ainda não possui grupos ou questões cadastradas.</p>
      </div>
    </div>
  </DashboardLayout>
</template>

<style scoped></style>
