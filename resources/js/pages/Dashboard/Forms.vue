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

