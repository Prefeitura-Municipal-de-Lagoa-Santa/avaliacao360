<script setup lang="ts">
import { computed, ref } from 'vue';
import { useForm, Head } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import { PlusIcon, Trash2Icon, LoaderCircleIcon, PlusCircleIcon } from 'lucide-vue-next';

// --- Interfaces (sem alteração) ---
interface PdiQuestion {
  text: string;
}
interface PdiGroup {
  name: string;
  questions: PdiQuestion[];
}

// --- Props ---
const props = defineProps<{
  formType: 'pactuacao_servidor' | 'pactuacao_comissionado' | 'pactuacao_gestor';
  year: string;
  form?: {
    id: number;
    name: string;
    year: string;
    type: string;
    group_questions: Array<{
      id: number;
      name: string;
      questions: Array<{ id: number; text_content: string }>;
    }>;
  };
}>();

const isEditing = computed(() => !!props.form);

const pdiTitles: Record<string, string> = {
  pactuacao_servidor: 'Formulário de Pactuação - Servidor',
  pactuacao_comissionado: 'Formulário de Pactuação - Comissionado',
  pactuacao_gestor: 'Formulário de Pactuação - Gestor',
};

const pageTitle = computed(() => `${isEditing.value ? 'Editar' : 'Criar'} ${pdiTitles[props.formType]}`);

// --- Lógica do Formulário com useForm ---
const pdiForm = useForm({
    // MODIFICADO: Usa o título dinâmico
    title: props.form?.name || pdiTitles[props.formType],
    year: props.year,
    type: props.formType,
    groups: props.form?.group_questions.map(g => ({
        name: g.name,
        questions: g.questions.map(q => ({ text: q.text_content }))
    })) || [{ name: '', questions: [{ text: '' }] }],
});


const validationError = ref<string | null>(null);

// --- Funções de Manipulação ---
function addGroup() {
  pdiForm.groups.push({ name: '', questions: [{ text: '' }] });
}

function removeGroup(index: number) {
  if (pdiForm.groups.length > 1) {
    pdiForm.groups.splice(index, 1);
  }
}

function addQuestion(groupIndex: number) {
  pdiForm.groups[groupIndex].questions.push({ text: '' });
}

function removeQuestion(groupIndex: number, questionIndex: number) {
  const group = pdiForm.groups[groupIndex];
  if (group.questions.length > 1) {
    group.questions.splice(questionIndex, 1);
  }
}

// --- Salvamento ---
function handleSave() {
  validationError.value = null;
  // Validação simples
  for (const group of pdiForm.groups) {
    if (!group.name.trim()) {
      validationError.value = 'Todos os grupos devem ter um nome.';
      return;
    }
    for (const question of group.questions) {
      if (!question.text.trim()) {
        validationError.value = 'Todas as questões devem ter um texto.';
        return;
      }
    }
  }

  if (isEditing.value) {
    // Chama a nova rota de update do PDI
    pdiForm.put(route('configs.pdi.update', { formulario: props.form!.id }));
  } else {
    // Chama a nova rota de store do PDI
    pdiForm.post(route('configs.pdi.store'));
  }
}

function handleCancel() {
    window.history.back();
}
</script>

<template>
  <Head :title="pageTitle" />
  <DashboardLayout :pageTitle="pageTitle">
    <div class="max-w-4xl mx-auto form-creator-container">
      <div class="space-y-8">
        <div v-for="(group, groupIndex) in pdiForm.groups" :key="groupIndex" class="group-container">
          <div class="group-header">
            <input v-model="group.name" type="text" class="group-name-input placeholder:text-red-500" placeholder="Digite o Nome do Grupo (Ex: Metas de Curto Prazo)">
            <button @click="removeGroup(groupIndex)" class="remove-group-btn" :disabled="pdiForm.groups.length <= 1">
              <Trash2Icon class="size-5" />
            </button>
          </div>

          <div class="space-y-3 p-4">
            <div v-for="(question, qIndex) in group.questions" :key="qIndex" class="question-row items-start">
              <div class="w-11/12">
                <textarea v-model="question.text" class="form-textarea" rows="2" placeholder="Digite a meta ou questão aqui..."></textarea>
              </div>
              <div class="w-1/12 flex justify-center pt-1">
                <button @click="removeQuestion(groupIndex, qIndex)" class="remove-question-btn" :disabled="group.questions.length <= 1">
                  <Trash2Icon class="size-5" />
                </button>
              </div>
            </div>
            <button @click="addQuestion(groupIndex)" type="button" class="btn-green-outline">
              <PlusIcon class="size-4 mr-2" />
              Adicionar Questão/Meta
            </button>
          </div>
        </div>
      </div>

      <div class="mt-8 flex justify-between items-center border-t pt-6">
        <button @click="addGroup" type="button" class="btn-green">
          <PlusCircleIcon class="size-5 mr-2" />
          Adicionar Grupo
        </button>
        <div class="flex justify-end gap-4">
          <button @click="handleCancel" class="btn-gray" :disabled="pdiForm.processing">Cancelar</button>
          <button @click="handleSave" class="btn-blue" :disabled="pdiForm.processing">
            <span v-if="!pdiForm.processing">Salvar Formulário PDI</span>
            <span v-else class="flex items-center">
              <LoaderCircleIcon class="animate-spin mr-2" />
              Salvando...
            </span>
          </button>
        </div>
      </div>
       <div v-if="validationError" class="mt-4 text-center text-red-600 font-medium bg-red-50 p-3 rounded-md">
            {{ validationError }}
        </div>
    </div>
  </DashboardLayout>
</template>