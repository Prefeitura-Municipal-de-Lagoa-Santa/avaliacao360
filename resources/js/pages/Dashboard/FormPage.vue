<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import FormCreator from '@/pages/Dashboard/Forms.vue'; // O seu componente de formulário

// 1. Props recebidas do FormController (edit ou create)
const props = defineProps<{
  formType: 'autoavaliacao' | 'chefia' | 'servidor' | 'pactuacao' | 'metas';
  year: string;
  form?: { // O formulário completo, opcional (apenas para edição)
    id: number;
    name: string;
    year: string;
    type: string;
    questions: Array<{ id: number; text_content: string; weight: number }>;
  };
}>();

const isEditing = computed(() => !!props.form);

// 2. Títulos dinâmicos
const formTitles: Record<string, string> = {
  autoavaliacao: 'Formulário de Autoavaliação',
  chefia: 'Formulário de Avaliação Chefia',
  servidor: 'Formulário de Avaliação do Servidor',
  pactuacao: 'Formulário de Pactuação (PDI)',
  metas: 'Formulário de Cumprimento de Metas (PDI)',
};

const pageTitle = computed(() => {
    const action = isEditing.value ? 'Editar' : 'Criar';
    return `${action} ${formTitles[props.formType]}`;
});

// 3. Lógica do formulário com o useForm do Inertia
const form = useForm({
    title: props.form?.name || formTitles[props.formType],
    year: props.year,
    type: props.formType,
    questions: props.form?.questions.map(q => ({ text: q.text_content, weight: q.weight })) || [{ text: '', weight: null }],
});

function handleSave(questionsPayload: Array<{ text: string; weight: number | null }>) {
  form.questions = questionsPayload;

  if (isEditing.value) {
    // Rota de UPDATE
    form.put(route('configs.update', { formulario: props.form!.id }), {
      onError: (errors) => console.error("Erro ao atualizar:", errors),
    });
  } else {
    // Rota de STORE
    form.post(route('configs.store'), {
      onError: (errors) => console.error("Erro ao criar:", errors),
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
      :initial-questions="form.questions"
      :is-saving="form.processing"
      @save="handleSave"
      @cancel="handleCancel"
    />
  </DashboardLayout>
</template>