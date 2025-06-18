<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import {
  ArrowLeftIcon,
  Trash2Icon,
  PlusIcon,
  PlusCircleIcon,
  LoaderCircleIcon
} from 'lucide-vue-next';

// --- Interfaces para a Estrutura de Dados ---
interface Question {
  text: string;
  weight: number | null; // Peso relativo (dentro do grupo, a soma é 100)
}

interface Group {
  name: string;
  weight: number | null; // Peso do grupo no formulário (a soma dos grupos é 100)
  questions: Question[];
}

// --- Props e Emits ---
const props = defineProps<{
  title: string;
  isSaving: boolean;
  initialGroups?: Group[];
}>();

const emit = defineEmits<{
  (e: 'save', payload: Group[]): void;
  (e: 'cancel'): void;
}>();

// --- Estado Reativo Principal ---
const groups = ref<Group[]>(
  props.initialGroups && props.initialGroups.length > 0
    ? JSON.parse(JSON.stringify(props.initialGroups))
    : [{ name: '', weight: 100, questions: [{ text: '', weight: 100 }] }]
);

const validationError = ref<string | null>(null);

// --- Propriedades Computadas ---
const totalFormWeight = computed(() => {
  return groups.value.reduce((sum, g) => sum + (Number(g.weight) || 0), 0);
});

const groupQuestionWeightSum = (group: Group) => {
    return group.questions.reduce((sum, q) => sum + (Number(q.weight) || 0), 0);
};

// --- Watcher para limpar erros ---
watch(groups, () => {
  validationError.value = null;
}, { deep: true });

// --- Lógica de Distribuição Automática de Pesos ---

// --- GRUPOS ---
function distributeGroupWeights() {
    const numGroups = groups.value.length;
    if (numGroups === 0) return;
    const weightPerGroup = 100 / numGroups;
    groups.value.forEach(g => g.weight = parseFloat(weightPerGroup.toFixed(2)));
    adjustLastItemWeight(groups.value, 100, g => g.weight, (g, w) => g.weight = w);
}

function adjustGroupWeights(changedIndex: number) {
    adjustItemWeights(groups.value, changedIndex, 100, g => g.weight, (g, w) => g.weight = w);
}

function addGroup() {
  groups.value.push({ name: '', weight: 0, questions: [{ text: '', weight: 100 }] });
  distributeGroupWeights();
}

function removeGroup(index: number) {
  if (groups.value.length > 1) {
    groups.value.splice(index, 1);
    distributeGroupWeights();
  }
}

// --- QUESTÕES ---
function distributeQuestionWeights(groupIndex: number) {
    const group = groups.value[groupIndex];
    const numQuestions = group.questions.length;
    if (numQuestions === 0) return;
    const weightPerQuestion = 100 / numQuestions;
    group.questions.forEach(q => q.weight = parseFloat(weightPerQuestion.toFixed(2)));
    adjustLastItemWeight(group.questions, 100, q => q.weight, (q, w) => q.weight = w);
}

function adjustQuestionWeights(groupIndex: number, changedQuestionIndex: number) {
    adjustItemWeights(groups.value[groupIndex].questions, changedQuestionIndex, 100, q => q.weight, (q, w) => q.weight = w);
}

function addQuestion(groupIndex: number) {
  groups.value[groupIndex].questions.push({ text: '', weight: 0 });
  distributeQuestionWeights(groupIndex);
}

function removeQuestion(groupIndex: number, questionIndex: number) {
  const group = groups.value[groupIndex];
  if (group.questions.length > 1) {
    group.questions.splice(questionIndex, 1);
    distributeQuestionWeights(groupIndex);
  }
}

// --- Funções Genéricas de Ajuste ---
function adjustLastItemWeight<T>(items: T[], total: number, getWeight: (item: T) => number | null, setWeight: (item: T, weight: number) => void) {
    const currentSum = items.reduce((sum, item) => sum + (getWeight(item) || 0), 0);
    const difference = total - currentSum;
    if (difference !== 0 && items.length > 0) {
        const lastItem = items[items.length - 1];
        const lastItemWeight = getWeight(lastItem) || 0;
        setWeight(lastItem, parseFloat((lastItemWeight + difference).toFixed(2)));
    }
}

function adjustItemWeights<T>(items: T[], changedIndex: number, total: number, getWeight: (item: T) => number | null, setWeight: (item: T, weight: number) => void) {
    const changedItem = items[changedIndex];
    let typedWeight = Number(getWeight(changedItem));

    if (isNaN(typedWeight)) return;

    typedWeight = Math.max(0, Math.min(total, typedWeight));
    setWeight(changedItem, typedWeight);

    const otherItems = items.filter((_, i) => i !== changedIndex);
    if (otherItems.length === 0) {
        setWeight(changedItem, total);
        return;
    }
    
    const remainingWeight = total - typedWeight;
    let sumOfOtherWeights = otherItems.reduce((sum, item) => sum + (getWeight(item) || 0), 0);
    
    otherItems.forEach(item => {
        const originalWeight = getWeight(item) || 0;
        const proportion = (sumOfOtherWeights > 0) ? originalWeight / sumOfOtherWeights : 1 / otherItems.length;
        setWeight(item, parseFloat((remainingWeight * proportion).toFixed(2)));
    });
    
    adjustLastItemWeight(items, total, getWeight, setWeight);
}


// --- Submissão do Formulário ---
function handleSave() {
  if (Math.abs(totalFormWeight.value - 100) > 0.01) {
    validationError.value = 'A soma dos pesos de todos os GRUPOS deve ser exatamente 100%.';
    return;
  }

  for (const group of groups.value) {
    if (!group.name.trim()) {
      validationError.value = 'Todos os grupos devem ter um nome.';
      return;
    }
    const groupSum = groupQuestionWeightSum(group);
    if (Math.abs(groupSum - 100) > 0.01) {
        validationError.value = `A soma dos pesos das questões no grupo "${group.name}" deve ser 100%. Atualmente é ${groupSum.toFixed(2)}%.`;
        return;
    }
    for (const question of group.questions) {
      if (!question.text.trim()) {
        validationError.value = 'Todas as questões devem ter um texto.';
        return;
      }
    }
  }
  
  emit('save', groups.value);
}

function handleCancel() {
  if (props.isSaving) return;
  emit('cancel');
}
</script>

<template>
  <div class="max-w-4xl mx-auto">
    <div class="detail-page-header">
      <h2 class="text-2xl font-bold text-gray-800">{{ props.title }}</h2>
      <button @click="handleCancel" class="back-btn" :disabled="props.isSaving">
        <ArrowLeftIcon class="size-4 mr-2" />
        Voltar
      </button>
    </div>

    <div class="form-creator-container">
        <!-- Container principal para os grupos -->
        <div class="space-y-8">
          <div v-for="(group, groupIndex) in groups" :key="groupIndex" class="group-container">
            <!-- Header do Grupo -->
            <div class="group-header">
              <input v-model="group.name" type="text" class="group-name-input placeholder:text-red-500" placeholder="Digite o Nome do Grupo (Ex: Fator I - Assiduidade)" >
              <div class="flex items-center gap-4">
                  <div class="flex items-center gap-2">
                    <label :for="`group-weight-${groupIndex}`" class="text-sm font-medium text-gray-600 whitespace-nowrap">Peso do Grupo (%):</label>
                    <input :id="`group-weight-${groupIndex}`" v-model.number="group.weight" type="number" min="0" max="100" class="form-input weight-input" placeholder="0" @change="adjustGroupWeights(groupIndex)">
                  </div>
                  <button @click="removeGroup(groupIndex)" class="remove-group-btn" :disabled="groups.length <= 1">
                    <Trash2Icon class="size-5" />
                  </button>
              </div>
            </div>

            <!-- Tabela de Questões do Grupo -->
            <div class="space-y-3 p-4">
                <div class="flex items-center pb-2 border-b mb-2">
                    <div class="w-9/12 font-semibold text-gray-600">Questão</div>
                    <div class="w-2/12 font-semibold text-gray-600 text-center">Peso Relativo (%)</div>
                    <div class="w-1/12 font-semibold text-gray-600 text-center">Ação</div>
                </div>
                <div v-for="(question, qIndex) in group.questions" :key="qIndex" class="question-row">
                    <div class="w-9/12">
                        <textarea v-model="question.text" class="form-textarea" rows="1" placeholder="Digite a questão aqui..."></textarea>
                    </div>
                    <div class="w-2/12 flex justify-center">
                        <input v-model.number="question.weight" type="number" min="0" max="100" class="form-input weight-input" placeholder="0" @change="adjustQuestionWeights(groupIndex, qIndex)">
                    </div>
                    <div class="w-1/12 flex justify-center">
                        <button @click="removeQuestion(groupIndex, qIndex)" class="remove-question-btn" :disabled="group.questions.length <= 1">
                            <Trash2Icon class="size-5" />
                        </button>
                    </div>
                </div>
                <div class="flex justify-between items-center mt-2">
                    <button @click="addQuestion(groupIndex)" type="button" class="btn-green-outline">
                      <PlusIcon class="size-4 mr-2" />
                      Adicionar Questão
                    </button>
                    <div class="text-sm font-semibold text-gray-600" :class="{'text-red-600': Math.abs(groupQuestionWeightSum(group) - 100) > 0.01}">
                        Soma do Grupo: {{ groupQuestionWeightSum(group).toFixed(2) }}%
                    </div>
                </div>
            </div>
          </div>
        </div>

        <!-- Controles Gerais -->
        <div class="mt-8 flex justify-between items-center border-t pt-6">
          <button @click="addGroup" type="button" class="btn-green">
            <PlusCircleIcon class="size-5 mr-2" />
            Adicionar Grupo
          </button>
          <div class="font-semibold text-right">
            <span>Peso Total do Formulário: </span>
            <span class="text-xl font-bold" :class="{ 'text-green-600': Math.abs(totalFormWeight - 100) < 0.01, 'text-red-600': Math.abs(totalFormWeight - 100) >= 0.01 }">
              {{ totalFormWeight.toFixed(2) }}%
            </span>
          </div>
        </div>

        <div v-if="validationError" class="mt-4 text-center text-red-600 font-medium bg-red-50 p-3 rounded-md">
            {{ validationError }}
        </div>

        <div class="mt-8 flex justify-end gap-4">
          <button @click="handleCancel" class="btn-gray" :disabled="props.isSaving">Cancelar</button>
          <button @click="handleSave" class="btn-blue" :disabled="props.isSaving">
            <span v-if="!props.isSaving">Salvar Formulário</span>
            <span v-else class="flex items-center">
              <LoaderCircleIcon class="animate-spin mr-2" />
              Salvando...
            </span>
          </button>
        </div>
    </div>
  </div>
</template>

<style scoped>
/* Estilos gerais */

</style>
