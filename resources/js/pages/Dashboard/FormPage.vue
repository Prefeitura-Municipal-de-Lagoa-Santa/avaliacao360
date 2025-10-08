<script setup lang="ts">
import { computed } from 'vue';
import { useForm, Head } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import FormCreator from '@/pages/Dashboard/Forms.vue';

// --- Interfaces para Tipagem ---
// QuestionPayload.weight aqui representa o PESO RELATIVO (%) dentro do grupo (0-100)
interface QuestionPayload {
  text: string;
  weight: number | null; // relativo
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
  // Normaliza pesos relativos das questões para garantir soma 100 exata em cada grupo.
  const normalized = groupsPayload.map(g => {
    const qs = g.questions.slice();
    // Trabalha em centésimos para minimizar erro acumulado
    let rawCents = qs.map(q => Math.round(((Number(q.weight) || 0)) * 100)); // ex: 16.67 -> 1667
    let sumCents = rawCents.reduce((a,b) => a+b, 0);
    const targetCents = 100 * 100; // 100.00%
    let diff = targetCents - sumCents; // positivo: falta; negativo: sobra
    if (diff !== 0 && qs.length > 0) {
      // Distribui ajuste de 1 cent por iteração priorizando maiores pesos quando adicionando
      const order = rawCents.map((v,i)=>({i,v})).sort((a,b)=> diff>0 ? b.v - a.v : a.v - b.v);
      let idx=0;
      while(diff !== 0) {
        rawCents[order[idx].i] += diff>0 ? 1 : -1;
        diff += diff>0 ? -1 : 1;
        idx = (idx + 1) % order.length;
      }
    }
    const finalQuestions = qs.map((q, i) => ({
      text: q.text,
      weight: parseFloat((rawCents[i]/100).toFixed(2))
    }));
    return { name: g.name, weight: g.weight, questions: finalQuestions };
  });

  form.groups = normalized as any;

  if (isEditing.value) {
    form.put(route('configs.form.update', { formulario: props.form!.id }), {
      onError: (errors) => {
        console.error('Erro ao atualizar:', errors);
      },
    });
  } else {
    form.post(route('configs.form.store'), {
      onError: (errors) => {
        console.error('Erro ao criar:', errors);
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
