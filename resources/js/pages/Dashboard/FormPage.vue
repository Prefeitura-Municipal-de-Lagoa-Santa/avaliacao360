<script setup lang="ts">
import { computed } from 'vue';
import { useForm, Head } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import FormCreator from '@/pages/Dashboard/Forms.vue';

// --- Interfaces para Tipagem ---
interface QuestionPayload {
  text: string;
  weight: number | null;
}
interface GroupPayload {
  name: string;
  weight: number | null;
  questions: QuestionPayload[];
}

// --- Props ---
const props = defineProps<{
  formType: 'gestor' | 'chefia' | 'servidor' | 'comissionado' | 'pactuacao_servidor' | 'pactuacao_comissionado' | 'pactuacao_gestor' ;
  year: string;
  form?: {
    id: number;
    name: string;
    year: string;
    type: string;
    group_questions: Array<{
      id: number;
      name: string;
      weight: number; // Peso do grupo
      questions: Array<{ id: number; text_content: string; weight: number }>;
    }>;
  };
}>();

const isEditing = computed(() => !!props.form);

// Títulos dinâmicos
const formTitles: Record<string, string> = {
  gestor: 'Formulário do Gestor',
  chefia: 'Formulário de Avaliação Chefia',
  servidor: 'Formulário de Avaliação do Servidor',
  comissionado: 'Formulário de Avaliação do Comissionado',
  pactuacao: 'Formulário de Pactuação (PDI)',

};
const pageTitle = computed(() => `${isEditing.value ? 'Editar' : 'Criar'} ${formTitles[props.formType]}`);

// --- Lógica do Formulário com useForm ---
// CORRIGIDO: A estrutura do 'useForm' agora corresponde perfeitamente à nova lógica
const form = useForm({
    title: props.form?.name || formTitles[props.formType],
    year: props.year,
    type: props.formType,
    groups: props.form?.group_questions.map(g => {
        // Calcula o peso relativo das questões dentro do grupo para exibição no formulário
        const groupTotalAbsoluteWeight = g.questions.reduce((sum, q) => sum + q.weight, 0);
        return {
            name: g.name,
            weight: g.weight,
            questions: g.questions.map(q => ({
                text: q.text_content,
                // Converte o peso final de volta para o peso relativo (0-100) para o componente de edição
                weight: groupTotalAbsoluteWeight > 0
                    ? parseFloat(((q.weight / groupTotalAbsoluteWeight) * 100).toFixed(2))
                    : 0
            }))
        }
    }) || [{ name: '', weight: 100, questions: [{ text: '', weight: 100 }] }],
});




function handleSave(groupsPayload: GroupPayload[]) {
  form.groups = groupsPayload;

  if (isEditing.value) {
    // Ao editar, usa form.put() que envia uma requisição PUT.
    form.put(route('configs.update', { formulario: props.form!.id }), {
        onError: (errors) => {
            console.error("Erro ao atualizar:", errors);
        },
    });
  } else {
    // Ao criar, usa form.post() que envia uma requisição POST.
    form.post(route('configs.store'), {
        onError: (errors) => {
            console.error("Erro ao criar:", errors);
        },
    });
  }
}

function handleCancel() {
    window.history.back();
}
</script>

<template>
  <Head :title="pageTitle" />
  <DashboardLayout :pageTitle="pageTitle">
    <FormCreator
      :title="pageTitle"
      :initial-groups="form.groups"
      :is-saving="form.processing"
      @save="handleSave"
      @cancel="handleCancel"
    />
  </DashboardLayout>
</template>
