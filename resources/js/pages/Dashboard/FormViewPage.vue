<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import * as icons from 'lucide-vue-next';

// 1. Props recebidas do FormController@show
const props = defineProps<{
  form: {
    id: number;
    name: string;
    year: string;
    type: string;
    questions: Array<{
      id: number;
      text_content: string;
      weight: number;
    }>;
  };
}>();

// 2. Função para voltar para a página anterior
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
          <component :is="icons.ArrowLeftIcon" class="size-4 mr-2" />
          Voltar
        </button>
      </div>

      <div class="form-creator-container">
        <div class="flex items-center pb-2 border-b mb-4">
            <div class="w-10/12 font-semibold text-gray-600">Questão</div>
            <div class="w-2/12 font-semibold text-gray-600 text-center">Peso (%)</div>
        </div>

        <div v-if="props.form.questions && props.form.questions.length > 0" class="space-y-3">
          <div v-for="question in props.form.questions" :key="question.id" class="question-view-row">
              <div class="w-10/12">
                  <p class="text-gray-800">{{ question.text_content }}</p>
              </div>
              <div class="w-2/12 flex justify-center">
                  <span class="font-medium text-gray-700">{{ question.weight }}%</span>
              </div>
          </div>
        </div>

        <div v-else class="text-center text-gray-500 py-8">
            <p>Este formulário ainda não possui questões cadastradas.</p>
        </div>
      </div>
    </div>

  </DashboardLayout>
</template>

<style scoped>
/* Adicione este estilo ao seu custom.css ou aqui mesmo para melhor aparência */
.question-view-row {
  display: flex;
  align-items: center;
  padding: 12px 8px;
  background-color: #f9fafb; /* bg-gray-50 */
  border-radius: 6px;
  border: 1px solid #e5e7eb; /* border-gray-200 */
}
</style>