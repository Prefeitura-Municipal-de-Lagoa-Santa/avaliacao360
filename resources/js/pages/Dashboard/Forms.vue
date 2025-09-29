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
  weight: number | null;
}

interface Group {
  name: string;
  weight: number | null;
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
const autoBalanceEnabled = ref<boolean>(true);

// Sistema de rastreamento de edições manuais
const manuallyEditedGroups = ref<Set<number>>(new Set());
const manuallyEditedQuestions = ref<Map<string, Set<number>>>(new Map());

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

// --- Lógica de Distribuição de Pesos (Melhorada) ---

function distributeGroupWeights() {
    const numGroups = groups.value.length;
    if (numGroups === 0) return;
    const weightPerGroup = parseFloat((100 / numGroups).toFixed(2));
    groups.value.forEach((g, index) => {
        g.weight = index === numGroups - 1 
            ? parseFloat((100 - weightPerGroup * (numGroups - 1)).toFixed(2)) 
            : weightPerGroup;
    });
}

function distributeQuestionWeights(groupIndex: number) {
    const group = groups.value[groupIndex];
    const numQuestions = group.questions.length;
    if (numQuestions === 0) return;
    const weightPerQuestion = parseFloat((100 / numQuestions).toFixed(2));
    group.questions.forEach((q, index) => {
        q.weight = index === numQuestions - 1 
            ? parseFloat((100 - weightPerQuestion * (numQuestions - 1)).toFixed(2))
            : weightPerQuestion;
    });
}

// --- Ajuste Manual de Pesos (Nova Lógica com Preservação) ---
function adjustGroupWeight(changedIndex: number) {
    if (!autoBalanceEnabled.value) return;
    
    // Marca este grupo como editado manualmente
    manuallyEditedGroups.value.add(changedIndex);
    
    const changedGroup = groups.value[changedIndex];
    const newWeight = Number(changedGroup.weight) || 0;
    
    // Garante que o peso não exceda 100
    if (newWeight > 100) {
        changedGroup.weight = 100;
        return;
    }
    
    // Filtra apenas grupos que NÃO foram editados manualmente
    const otherGroups = groups.value
        .map((group, index) => ({ group, index }))
        .filter(({ index }) => index !== changedIndex && !manuallyEditedGroups.value.has(index));
    
    if (otherGroups.length === 0) return;
    
    // Calcula peso usado por grupos editados manualmente (incluindo o atual)
    const manualWeight = groups.value.reduce((sum, group, index) => {
        if (manuallyEditedGroups.value.has(index)) {
            return sum + (Number(group.weight) || 0);
        }
        return sum;
    }, 0);
    
    const remainingWeight = 100 - manualWeight;
    
    // Se o peso restante é 0 ou negativo, zera os não editados
    if (remainingWeight <= 0) {
        otherGroups.forEach(({ group }) => group.weight = 0);
        return;
    }
    
    // Calcula a soma atual dos grupos não editados
    const currentSum = otherGroups.reduce((sum, { group }) => sum + (Number(group.weight) || 0), 0);
    
    if (currentSum === 0) {
        // Se todos os não editados são zero, distribui igualmente
        const equalWeight = parseFloat((remainingWeight / otherGroups.length).toFixed(2));
        otherGroups.forEach(({ group }, index) => {
            group.weight = index === otherGroups.length - 1 
                ? parseFloat((remainingWeight - equalWeight * (otherGroups.length - 1)).toFixed(2))
                : equalWeight;
        });
    } else {
        // Distribui proporcionalmente apenas entre os não editados
        otherGroups.forEach(({ group }) => {
            const currentWeight = Number(group.weight) || 0;
            const proportion = currentWeight / currentSum;
            group.weight = parseFloat((remainingWeight * proportion).toFixed(2));
        });
    }
}

function adjustQuestionWeight(groupIndex: number, changedQuestionIndex: number) {
    if (!autoBalanceEnabled.value) return;
    
    // Marca esta questão como editada manualmente
    const groupKey = `group-${groupIndex}`;
    if (!manuallyEditedQuestions.value.has(groupKey)) {
        manuallyEditedQuestions.value.set(groupKey, new Set());
    }
    manuallyEditedQuestions.value.get(groupKey)!.add(changedQuestionIndex);
    
    const group = groups.value[groupIndex];
    const changedQuestion = group.questions[changedQuestionIndex];
    const newWeight = Number(changedQuestion.weight) || 0;
    
    // Garante que o peso não exceda 100
    if (newWeight > 100) {
        changedQuestion.weight = 100;
        return;
    }
    
    // Filtra apenas questões que NÃO foram editadas manualmente
    const editedQuestions = manuallyEditedQuestions.value.get(groupKey) || new Set();
    const otherQuestions = group.questions
        .map((question, index) => ({ question, index }))
        .filter(({ index }) => index !== changedQuestionIndex && !editedQuestions.has(index));
    
    if (otherQuestions.length === 0) return;
    
    // Calcula peso usado por questões editadas manualmente (incluindo a atual)
    const manualWeight = group.questions.reduce((sum, question, index) => {
        if (editedQuestions.has(index)) {
            return sum + (Number(question.weight) || 0);
        }
        return sum;
    }, 0);
    
    const remainingWeight = 100 - manualWeight;
    
    // Se o peso restante é 0 ou negativo, zera as não editadas
    if (remainingWeight <= 0) {
        otherQuestions.forEach(({ question }) => question.weight = 0);
        return;
    }
    
    // Calcula a soma atual das questões não editadas
    const currentSum = otherQuestions.reduce((sum, { question }) => sum + (Number(question.weight) || 0), 0);
    
    if (currentSum === 0) {
        // Se todas as não editadas são zero, distribui igualmente
        const equalWeight = parseFloat((remainingWeight / otherQuestions.length).toFixed(2));
        otherQuestions.forEach(({ question }, index) => {
            question.weight = index === otherQuestions.length - 1 
                ? parseFloat((remainingWeight - equalWeight * (otherQuestions.length - 1)).toFixed(2))
                : equalWeight;
        });
    } else {
        // Distribui proporcionalmente apenas entre as não editadas
        otherQuestions.forEach(({ question }) => {
            const currentWeight = Number(question.weight) || 0;
            const proportion = currentWeight / currentSum;
            question.weight = parseFloat((remainingWeight * proportion).toFixed(2));
        });
    }
}

// --- Funções de Manipulação ---
function addGroup() {
  groups.value.push({ name: '', weight: 0, questions: [{ text: '', weight: 100 }] });
  if (autoBalanceEnabled.value) {
    distributeGroupWeights();
    // Limpa o histórico de edições manuais quando redistribui
    manuallyEditedGroups.value.clear();
  }
}

function removeGroup(index: number) {
  if (groups.value.length > 1) {
    groups.value.splice(index, 1);
    
    // Atualiza os índices no histórico de edições manuais
    const newEditedGroups = new Set<number>();
    manuallyEditedGroups.value.forEach(editedIndex => {
      if (editedIndex < index) {
        newEditedGroups.add(editedIndex);
      } else if (editedIndex > index) {
        newEditedGroups.add(editedIndex - 1);
      }
      // Se editedIndex === index, não adiciona (foi removido)
    });
    manuallyEditedGroups.value = newEditedGroups;
    
    // Remove o histórico de questões do grupo removido
    manuallyEditedQuestions.value.delete(`group-${index}`);
    
    // Atualiza as chaves dos grupos remanescentes
    const newEditedQuestions = new Map<string, Set<number>>();
    manuallyEditedQuestions.value.forEach((questionSet, key) => {
      const groupIndex = parseInt(key.replace('group-', ''));
      if (groupIndex < index) {
        newEditedQuestions.set(key, questionSet);
      } else if (groupIndex > index) {
        newEditedQuestions.set(`group-${groupIndex - 1}`, questionSet);
      }
    });
    manuallyEditedQuestions.value = newEditedQuestions;
    
    if (autoBalanceEnabled.value) {
      distributeGroupWeights();
      manuallyEditedGroups.value.clear();
    }
  }
}

function addQuestion(groupIndex: number) {
  groups.value[groupIndex].questions.push({ text: '', weight: 0 });
  if (autoBalanceEnabled.value) {
    distributeQuestionWeights(groupIndex);
    // Limpa o histórico de edições manuais das questões deste grupo
    manuallyEditedQuestions.value.delete(`group-${groupIndex}`);
  }
}

function removeQuestion(groupIndex: number, questionIndex: number) {
  const group = groups.value[groupIndex];
  if (group.questions.length > 1) {
    group.questions.splice(questionIndex, 1);
    
    // Atualiza os índices no histórico de edições manuais das questões
    const groupKey = `group-${groupIndex}`;
    const editedQuestions = manuallyEditedQuestions.value.get(groupKey);
    if (editedQuestions) {
      const newEditedQuestions = new Set<number>();
      editedQuestions.forEach(editedIndex => {
        if (editedIndex < questionIndex) {
          newEditedQuestions.add(editedIndex);
        } else if (editedIndex > questionIndex) {
          newEditedQuestions.add(editedIndex - 1);
        }
        // Se editedIndex === questionIndex, não adiciona (foi removido)
      });
      manuallyEditedQuestions.value.set(groupKey, newEditedQuestions);
    }
    
    if (autoBalanceEnabled.value) {
      distributeQuestionWeights(groupIndex);
      manuallyEditedQuestions.value.delete(groupKey);
    }
  }
}

// Função para limpar histórico de edições manuais
function clearManualEditHistory() {
  manuallyEditedGroups.value.clear();
  manuallyEditedQuestions.value.clear();
}

// --- Submissão do Formulário ---
function handleSave() {
  const totalWeight = totalFormWeight.value;
  const weightDifference = Math.abs(totalWeight - 100);
  
  if (weightDifference > 0.01) {
    validationError.value = `A soma dos pesos dos grupos é ${totalWeight.toFixed(2)}%. Deve ser exatamente 100%.${!autoBalanceEnabled.value ? ' Ative o balanceamento automático ou ajuste manualmente.' : ''}`;
    return;
  }

  for (const group of groups.value) {
    if (!group.name.trim()) {
      validationError.value = 'Todos os grupos devem ter um nome.';
      return;
    }
    
    const groupTotalWeight = groupQuestionWeightSum(group);
    const groupWeightDifference = Math.abs(groupTotalWeight - 100);
    
    if (groupWeightDifference > 0.01) {
      validationError.value = `No grupo "${group.name}", a soma dos pesos das questões é ${groupTotalWeight.toFixed(2)}%. Deve ser 100%.${!autoBalanceEnabled.value ? ' Ative o balanceamento automático ou ajuste manualmente.' : ''}`;
      return;
    }

    for (const question of group.questions) {
      if (!question.text.trim()) {
        validationError.value = `Todas as questões do grupo "${group.name}" devem ter um texto.`;
        return;
      }
    }
  }

  validationError.value = null;
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
        <!-- Toggle para Balanceamento Automático -->
        <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-sm font-medium text-blue-800">Balanceamento Automático de Pesos</h3>
              <p class="text-sm text-blue-600 mt-1">
                Quando ativado, preserva pesos editados manualmente e ajusta apenas os não editados.
                Desative para ter controle manual total dos pesos.
              </p>
              <div class="mt-2 flex items-center gap-4">
                <span class="text-xs text-blue-700">
                  Grupos editados: {{ manuallyEditedGroups.size }}
                </span>
                <span class="text-xs text-blue-700">
                  Questões editadas: {{ Array.from(manuallyEditedQuestions.values()).reduce((sum, set) => sum + set.size, 0) }}
                </span>
                <button 
                  @click="clearManualEditHistory"
                  class="text-xs px-2 py-1 bg-blue-100 text-blue-700 rounded hover:bg-blue-200"
                  v-if="manuallyEditedGroups.size > 0 || manuallyEditedQuestions.size > 0"
                >
                  Limpar Histórico
                </button>
              </div>
            </div>
            <label class="flex items-center cursor-pointer">
              <input 
                type="checkbox" 
                v-model="autoBalanceEnabled" 
                class="sr-only"
              >
              <div :class="autoBalanceEnabled ? 'bg-blue-600' : 'bg-gray-200'" class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                <span :class="autoBalanceEnabled ? 'translate-x-5' : 'translate-x-0'" class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
              </div>
              <span class="ml-3 text-sm font-medium text-gray-700">
                {{ autoBalanceEnabled ? 'Ativado' : 'Desativado' }}
              </span>
            </label>
          </div>
        </div>

        <!-- Status do Total dos Pesos -->
        <div class="mb-4 p-3 rounded-lg" :class="Math.abs(totalFormWeight - 100) > 0.01 ? 'bg-red-50 border border-red-200' : 'bg-green-50 border border-green-200'">
          <div class="flex items-center justify-between">
            <span class="text-sm font-medium" :class="Math.abs(totalFormWeight - 100) > 0.01 ? 'text-red-800' : 'text-green-800'">
              Total dos Pesos dos Grupos: {{ totalFormWeight.toFixed(2) }}%
            </span>
            <span v-if="Math.abs(totalFormWeight - 100) > 0.01" class="text-sm text-red-600">
              (Deve ser 100%)
            </span>
          </div>
        </div>

        <!-- Container principal para os grupos -->
        <div class="space-y-8">
          <div v-for="(group, groupIndex) in groups" :key="groupIndex" class="group-container">
            <!-- Header do Grupo -->
            <div class="group-header">
              <input v-model="group.name" type="text" class="group-name-input placeholder:text-red-500" placeholder="Digite o Nome do Grupo (Ex: Fator I - Assiduidade)" >
              <div class="flex items-center gap-4">
                  <div class="flex items-center gap-2">
                    <label :for="`group-weight-${groupIndex}`" class="text-sm font-medium text-gray-600 whitespace-nowrap">Peso do Grupo (%):</label>
                    <div class="relative">
                      <input 
                        :id="`group-weight-${groupIndex}`" 
                        v-model.number="group.weight" 
                        type="number" 
                        min="0" 
                        max="100" 
                        step="0.01"
                        :class="[
                          'form-input weight-input',
                          manuallyEditedGroups.has(groupIndex) ? 'manual-edited' : ''
                        ]"
                        placeholder="0" 
                        @input="adjustGroupWeight(groupIndex)"
                      >
                      <span 
                        v-if="manuallyEditedGroups.has(groupIndex)" 
                        class="manual-indicator"
                        title="Editado manualmente - será preservado no balanceamento"
                      >
                        ✓
                      </span>
                    </div>
                  </div>
                  <button @click="removeGroup(groupIndex)" class="remove-group-btn" :disabled="groups.length <= 1">
                    <Trash2Icon class="size-5" />
                  </button>
              </div>
            </div>

            <!-- Status do Total das Questões do Grupo -->
            <div class="mb-2 p-2 rounded text-sm" :class="Math.abs(groupQuestionWeightSum(group) - 100) > 0.01 ? 'bg-yellow-50 text-yellow-800 border border-yellow-200' : 'bg-green-50 text-green-800 border border-green-200'">
              Total das questões neste grupo: {{ groupQuestionWeightSum(group).toFixed(2) }}%
              <span v-if="Math.abs(groupQuestionWeightSum(group) - 100) > 0.01"> (Deve ser 100%)</span>
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
                        <div class="relative">
                          <input 
                            v-model.number="question.weight" 
                            type="number" 
                            min="0" 
                            max="100" 
                            step="0.01"
                            :class="[
                              'form-input weight-input',
                              manuallyEditedQuestions.get(`group-${groupIndex}`)?.has(qIndex) ? 'manual-edited' : ''
                            ]"
                            placeholder="0" 
                            @input="adjustQuestionWeight(groupIndex, qIndex)"
                          >
                          <span 
                            v-if="manuallyEditedQuestions.get(`group-${groupIndex}`)?.has(qIndex)" 
                            class="manual-indicator"
                            title="Editado manualmente - será preservado no balanceamento"
                          >
                            ✓
                          </span>
                        </div>
                    </div>
                    <div class="w-1/12 flex justify-center">
                        <button @click="removeQuestion(groupIndex, qIndex)" class="remove-question-btn" :disabled="group.questions.length <= 1">
                            <Trash2Icon class="size-4" />
                        </button>
                    </div>
                </div>
                <button @click="addQuestion(groupIndex)" class="add-question-btn">
                    <PlusIcon class="size-4 mr-2" />
                    Adicionar Questão
                </button>
            </div>
          </div>
        </div>

        <!-- Botão para Adicionar Grupos -->
        <div class="text-center mt-8">
          <button @click="addGroup" class="add-group-btn">
            <PlusCircleIcon class="size-5 mr-2" />
            Adicionar Grupo
          </button>
        </div>

        <!-- Botões para Distribuição Automática -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-6">
          <button @click="distributeGroupWeights" class="distribute-btn">
            Distribuir Pesos dos Grupos Igualmente
          </button>
          <button @click="groups.forEach((_, i) => distributeQuestionWeights(i))" class="distribute-btn">
            Distribuir Pesos das Questões Igualmente
          </button>
        </div>

        <!-- Exibe erros de validação -->
        <div v-if="validationError" class="error-alert mt-6">
          <strong>Erro:</strong> {{ validationError }}
        </div>

        <!-- Botões de Ação -->
        <div class="flex justify-end gap-4 mt-8">
          <button @click="handleCancel" class="btn-gray" :disabled="props.isSaving">Cancelar</button>
          <button @click="handleSave" class="btn-primary" :disabled="props.isSaving">
            <LoaderCircleIcon v-if="props.isSaving" class="animate-spin size-4 mr-2" />
            {{ props.isSaving ? 'Salvando...' : 'Salvar Formulário' }}
          </button>
        </div>
    </div>
  </div>
</template>

<style scoped>
/* Estilos básicos sem @apply para compatibilidade */
.detail-page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1.5rem;
  padding: 1rem;
  background-color: white;
  border-bottom: 1px solid #e5e7eb;
}

.back-btn {
  display: flex;
  align-items: center;
  padding: 0.5rem 1rem;
  color: #4b5563;
  background-color: #f3f4f6;
  border-radius: 0.5rem;
  transition: background-color 0.2s;
}

.back-btn:hover {
  background-color: #e5e7eb;
}

.form-creator-container {
  background-color: white;
  border-radius: 0.5rem;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
  border: 1px solid #e5e7eb;
  padding: 1.5rem;
}

.group-container {
  border: 1px solid #e5e7eb;
  border-radius: 0.5rem;
  padding: 1rem;
  background-color: #f9fafb;
}

.group-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1rem;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid #d1d5db;
}

.group-name-input {
  flex: 1;
  font-size: 1.125rem;
  font-weight: 600;
  background-color: transparent;
  border: none;
  outline: none;
  margin-right: 1rem;
}

.weight-input {
  width: 5rem;
  text-align: center;
}

.manual-edited {
  border-color: #10b981 !important;
  background-color: #f0fdf4;
}

.manual-indicator {
  position: absolute;
  top: -8px;
  right: -8px;
  background-color: #10b981;
  color: white;
  border-radius: 50%;
  width: 16px;
  height: 16px;
  font-size: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: bold;
}

.form-input {
  padding: 0.5rem 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 0.375rem;
}

.form-input:focus {
  outline: none;
  box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.5);
  border-color: #3b82f6;
}

.form-textarea {
  width: 100%;
  padding: 0.5rem 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 0.375rem;
  resize: none;
}

.form-textarea:focus {
  outline: none;
  box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.5);
  border-color: #3b82f6;
}

.question-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.remove-group-btn, .remove-question-btn {
  padding: 0.5rem;
  color: #dc2626;
  border-radius: 0.375rem;
  transition: background-color 0.2s;
}

.remove-group-btn:hover, .remove-question-btn:hover {
  background-color: #fef2f2;
}

.remove-group-btn:disabled, .remove-question-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.add-question-btn, .add-group-btn {
  display: flex;
  align-items: center;
  padding: 0.5rem 1rem;
  color: #2563eb;
  background-color: #eff6ff;
  border: 1px solid #bfdbfe;
  border-radius: 0.375rem;
  transition: background-color 0.2s;
}

.add-question-btn:hover, .add-group-btn:hover {
  background-color: #dbeafe;
}

.distribute-btn {
  padding: 0.5rem 1rem;
  color: #059669;
  background-color: #ecfdf5;
  border: 1px solid #a7f3d0;
  border-radius: 0.375rem;
  transition: background-color 0.2s;
}

.distribute-btn:hover {
  background-color: #d1fae5;
}

.btn-primary {
  display: flex;
  align-items: center;
  padding: 0.5rem 1.5rem;
  background-color: #2563eb;
  color: white;
  border-radius: 0.375rem;
  transition: background-color 0.2s;
}

.btn-primary:hover {
  background-color: #1d4ed8;
}

.btn-primary:disabled {
  opacity: 0.5;
}

.btn-gray {
  display: flex;
  align-items: center;
  padding: 0.5rem 1.5rem;
  background-color: #d1d5db;
  color: #374151;
  border-radius: 0.375rem;
  transition: background-color 0.2s;
}

.btn-gray:hover {
  background-color: #9ca3af;
}

.btn-gray:disabled {
  opacity: 0.5;
}

.error-alert {
  padding: 1rem;
  background-color: #fef2f2;
  border: 1px solid #fecaca;
  color: #991b1b;
  border-radius: 0.375rem;
}
</style>
