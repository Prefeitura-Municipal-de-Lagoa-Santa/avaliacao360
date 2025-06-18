<script setup lang="ts">
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
      weight: number; // O peso do grupo que será exibido
      questions: Array<{
        id: number;
        text_content: string;
        weight: number; // O peso final da questão
      }>;
    }>;
  };
}>();

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

      <!-- Container principal que itera sobre os grupos de questões -->
      <div v-if="props.form.group_questions && props.form.group_questions.length > 0" class="space-y-6 mt-6">
        <div v-for="group in props.form.group_questions" :key="group.id" class="group-container-view">

            <!-- CORRIGIDO: Header do grupo agora usa flexbox para alinhar o nome e o peso -->
            <div class="group-header-view">
                <h3 class="flex-grow font-semibold text-gray-800">{{ group.name }}</h3>
                <!-- Badge estilizado para exibir o peso do grupo -->
                <span class="font-bold text-indigo-500 bg-white px-3 py-1 rounded-full text-sm ml-4">
                    Peso do Grupo: {{ group.weight }}%
                </span>
            </div>

            <!-- Corpo do grupo, com a lista de questões -->
            <div class="space-y-3 p-4">
                 <div class="flex items-center pb-2 border-b mb-2 text-sm text-gray-500 font-medium">
                    <div class="w-10/12">Questão</div>
                    <div class="w-2/12 text-center">Peso Final</div>
                </div>
                <div v-for="question in group.questions" :key="question.id" class="question-view-row">
                    <div class="w-10/12"><p class="text-gray-800">{{ question.text_content }}</p></div>
                    <div class="w-2/12 text-center"><span class="font-medium text-gray-700">{{ question.weight }}%</span></div>
                </div>
            </div>
        </div>
      </div>

      <!-- Mensagem exibida se o formulário não tiver grupos -->
      <div v-else class="text-center text-gray-500 py-8 form-creator-container">
          <p>Este formulário ainda não possui grupos ou questões cadastradas.</p>
      </div>
    </div>

  </DashboardLayout>
</template>

<style scoped>

</style>
