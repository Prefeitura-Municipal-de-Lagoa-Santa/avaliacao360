<script setup lang="ts">
import { ref, computed, watch } from 'vue'; // Importar watch
import * as icons from 'lucide-vue-next';

// 1. Props: Adicionamos 'isSaving' para receber o estado do pai
const props = defineProps<{
  title: string;
  isSaving: boolean; // <-- NOVA PROP
}>();

// 2. Emits: Continua igual
const emit = defineEmits<{
  (e: 'save', payload: Array<{ text: string; weight: number | null }>): void;
  (e: 'cancel'): void;
}>();


// --- Lógica Interna ---
const questions = ref([
  { text: '', weight: null }
]);
// Ref para a mensagem de erro de validação local
const validationError = ref<string | null>(null); // <-- NOVA REF DE ERRO

const totalWeight = computed(() => {
  return questions.value.reduce((sum, q) => sum + (Number(q.weight) || 0), 0);
});

// Watcher para limpar o erro automaticamente quando o usuário corrigir o peso
watch(totalWeight, () => { // <-- NOVO WATCHER
    validationError.value = null;
});

function addQuestion() {
  questions.value.push({ text: '', weight: null });
}

function removeQuestion(index: number) {
  if (questions.value.length > 1) {
    questions.value.splice(index, 1);
  }
}

// 3. Funções de Evento Atualizadas
function handleSave() {
  // Validação local sem alert()
  if (totalWeight.value !== 100) {
    validationError.value = 'A soma dos pesos de todas as questões deve ser exatamente 100%.'; // <-- ATUALIZA O ERRO
    return;
  }
  // Se a validação passar, emite o evento
  emit('save', questions.value);
}

function handleCancel() {
  // Não permite cancelar enquanto estiver salvando
  if (props.isSaving) return;
  emit('cancel');
}
</script>

<template>
  <div class="max-w-4xl mx-auto">
    <div class="detail-page-header">
      <h2 class="text-2xl font-bold text-gray-800">{{ props.title }}</h2>
      <button @click="handleCancel" class="back-btn" :disabled="props.isSaving">
        <component :is="icons.ArrowLeftIcon" class="size-4 mr-2" />
        Voltar
      </button>
    </div>
    <div class="form-creator-container">
      <div class="space-y-3">
        <div class="flex items-center pb-2 border-b mb-2">
            <div class="w-9/12 font-semibold text-gray-600">Questão</div>
            <div class="w-2/12 font-semibold text-gray-600 text-center">Peso (%)</div>
            <div class="w-1/12 font-semibold text-gray-600 text-center">Ação</div>
        </div>
        <div v-for="(question, index) in questions" :key="index" class="question-row">
            <div class="w-9/12">
                <textarea v-model="question.text" class="form-textarea" rows="1" placeholder="Digite a questão aqui..."></textarea>
            </div>
            <div class="w-2/12 flex justify-center">
                <input v-model.number="question.weight" type="number" min="0" class="form-input weight-input" placeholder="0">
            </div>
            <div class="w-1/12 flex justify-center">
                <button @click="removeQuestion(index)" class="remove-question-btn" :disabled="questions.length <= 1">
                    <component :is="icons.Trash2Icon" class="size-5" />
                </button>
            </div>
        </div>
      </div>
      <div class="mt-6 flex justify-between items-center">
        <button @click="addQuestion" type="button" class="btn-green">
          <component :is="icons.PlusIcon" class="size-5 mr-2" />
          Adicionar Questão
        </button>
        <div class="font-semibold text-right">
          <span>Peso Total: </span>
          <span class="text-lg" :class="{ 'text-green-600': totalWeight === 100, 'text-red-600': totalWeight !== 100 }">
            {{ totalWeight }}%
          </span>
        </div>
      </div>

      <div v-if="validationError" class="mt-4 text-center text-red-600 font-medium">
          {{ validationError }}
      </div>

      <div class="mt-8 flex justify-end gap-4 border-t pt-6">
        <button @click="handleCancel" class="btn-gray" :disabled="props.isSaving">Cancelar</button>
        <button @click="handleSave" class="btn-blue" :disabled="props.isSaving">
          <span v-if="!props.isSaving">Salvar Formulário</span>
          <span v-else class="flex items-center">
            <component :is="icons.LoaderCircleIcon" class="animate-spin mr-2" />
            Salvando...
          </span>
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Seu CSS aqui. Apenas corrigi o nome da classe principal para corresponder ao template */
.detail-page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
.back-btn { display: flex; align-items: center; padding: 0.5rem 1rem; background-color: #6366f1; color: white; border-radius: 0.5rem; font-weight: 500; font-size: 0.875rem; transition: background-color 0.2s; }
.back-btn:hover { background-color: #4f46e5; }
.back-btn:disabled { background-color: #9ca3af; cursor: not-allowed; }
.form-creator-container { background-color: #f9fafb; padding: 1.5rem; border-radius: 0.5rem; border: 1px solid #e5e7eb; }
.question-row { display: flex; align-items: center; padding: 0.5rem 0; gap: 1rem; }
.form-textarea, .form-input { width: 100%; border: 1px solid #d1d5db; border-radius: 0.375rem; padding: 0.5rem 0.75rem; background-color: white; transition: border-color 0.2s, box-shadow 0.2s; }
.form-textarea:focus, .form-input:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.3); }
.weight-input { max-width: 80px; text-align: center; }
.remove-question-btn { background: none; border: none; color: #ef4444; cursor: pointer; transition: color 0.2s; }
.remove-question-btn:hover { color: #dc2626; }
.remove-question-btn:disabled { color: #9ca3af; cursor: not-allowed; }
.btn-green, .btn-gray, .btn-blue { padding: 0.6rem 1.2rem; border-radius: 8px; font-weight: 500; display: inline-flex; align-items: center; justify-content: center; transition: background-color 0.2s; }
.btn-green { background-color: #22c55e; color: white; }
.btn-blue { background-color: #3b82f6; color: white; }
.btn-gray { background-color: #6b7280; color: white; }

</style>