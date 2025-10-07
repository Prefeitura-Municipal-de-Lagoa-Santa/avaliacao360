<script setup lang="ts">
import { computed } from 'vue';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ArrowLeftIcon } from 'lucide-vue-next';

const props = defineProps({
    form: { type: Object, required: true },
    person: { type: Object, required: true },
    type: { type: String, required: true },
    evaluation: { type: Object, required: true }
});

const cidade = 'Lagoa Santa';
const dataEnvio = computed(() => {
  if (!props.evaluation.updated_at) return '';
  const date = new Date(props.evaluation.updated_at);
  const dia = date.toLocaleDateString('pt-BR', { day: '2-digit' });
  const mes = date.toLocaleDateString('pt-BR', { month: 'long' });
  const ano = date.getFullYear();
  const mesFormatado = mes.charAt(0).toUpperCase() + mes.slice(1);
  return `${cidade}, ${dia} de ${mesFormatado} de ${ano}`;
});

function goBack() {
  window.history.back();
}

// Nota ponderada (ajuste se necessário)
const grandTotal = computed(() => {
  if (!props.form?.group_questions || !props.evaluation?.answers) {
    console.log('[DEBUG] Dados ausentes: form.group_questions ou evaluation.answers');
    return 0;
  }

  let somaNotas = 0;
  let somaPesos = 0;
  let hasRespostas = false;

  props.form.group_questions.forEach((group: any) => {
    group.questions.forEach((question: any) => {
      const answer = props.evaluation.answers.find((ans: any) => ans.question_id === question.id);
      const nota = Number(answer?.score);
      const peso = Number(question.weight);

      console.log('[DEBUG] Questão:', question.text_content);
      console.log('→ Score recebido:', answer?.score, '| Interpretado como:', nota);
      console.log('→ Peso recebido:', question.weight, '| Interpretado como:', peso);

      if (!isNaN(nota) && !isNaN(peso)) {
        hasRespostas = true;
        somaNotas += nota * peso;
        somaPesos += peso;
      } else {
        console.warn('[AVISO] Nota ou peso inválido:', { nota, peso, answer });
      }
    });
  });

  if (!hasRespostas || somaPesos === 0) {
    console.log('[INFO] Nenhuma resposta válida ou peso total zero');
    return 0;
  }

  const bruto = somaNotas / somaPesos;
  const resultado = Math.round(bruto * 100) / 100; // duas casas
  console.log('[RESULTADO] somaNotas:', somaNotas, '| somaPesos:', somaPesos, '| Média final (2 casas):', resultado, 'bruto:', bruto);

  return resultado;
});

// Formatação com duas casas decimais (string) mantendo separador padrão brasileiro se desejar no futuro
const grandTotalFormatted = computed(() => {
  return grandTotal.value.toFixed(1).replace('.', ',');
});


// Cargo/função dinâmica
const cargoOuFuncao = computed(() => {
  return props.person.job_function?.name || props.person.current_position || '-';
});
</script>

<template>
  <Head title="Resultado da Avaliação" />
  <DashboardLayout :pageTitle="'Resultado da Avaliação'">
    <div class="flex justify-between items-center border-b pb-4 mb-6">
      <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">Resultado da Avaliação</h1>
      <button @click="goBack" class="back-btn">
        <ArrowLeftIcon class="size-4 mr-2" />
        Voltar
      </button>
    </div>

    <!-- Identificação -->
    <div class="form-section">
      <h3 class="section-title">Identificação do Servidor</h3>
      <div class="form-grid sm:grid-cols-3 gap-4 mb-2">
        <div class="form-field sm:col-span-3">
          <label class="font-semibold">Nome:</label>
          <input type="text" :value="person.name" class="form-input" readonly>
        </div>
        <div class="form-field sm:col-span-2">
          <label class="font-semibold">Matrícula:</label>
          <input type="text" :value="person.registration_number" class="form-input" readonly>
        </div>
        <div class="form-field sm:col-span-1">
          <label class="font-semibold">Cargo / Função:</label>
          <input type="text" :value="cargoOuFuncao" class="form-input" readonly>
        </div>
        <div class="form-field sm:col-span-3">
          <label class="font-semibold">Secretaria:</label>
          <input type="text" :value="person.organizational_unit?.all_parents?.all_parents?.name || person.organizational_unit?.name || '-'" class="form-input" readonly>
        </div>
        <div class="form-field sm:col-span-3">
          <label class="font-semibold">Lotação:</label>
          <input type="text" :value="person.organizational_unit?.name || '-'" class="form-input" readonly>
        </div>
      </div>
    </div>

    <!-- Tabela de perguntas e respostas -->
    <div class="form-section">
      <h3 class="section-title">Competências Avaliados</h3>
      <div v-for="group in form.group_questions" :key="group.id" class="mb-8">
        <h4 class="text-lg font-semibold mb-2">{{ group.name }}</h4>
        <table class="w-full bg-white border border-gray-200 rounded-lg">
          <thead>
            <tr class="bg-gray-50">
              <th class="py-2 px-4 text-left">Questão</th>
              <th class="py-2 px-4 text-center w-40">Nota</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="question in group.questions" :key="question.id" class="border-t">
              <td class="px-4 py-3 align-top">{{ question.text_content }}</td>
              <td class="px-4 py-3 text-center">
                <span class="inline-flex justify-center items-center bg-blue-100 text-blue-800 font-bold rounded-full text-lg w-20 h-10">
                  {{ (() => { const s = props.evaluation.answers?.find((ans: any) => ans.question_id === question.id)?.score; return (s === undefined || s === null) ? '-' : Number(s).toFixed(1).replace('.', ','); })() }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Evidências -->
    <div class="form-section">
      <h3 class="section-title">Evidências</h3>
      <textarea :value="props.evaluation.evidencias" class="form-input w-full" rows="4" readonly></textarea>
    </div>

    <!-- Assinatura -->
    <div class="form-section">
      <h3 class="section-title">Assinatura</h3>
      <div class="flex flex-col justify-center items-center pt-8">
        <img
          v-if="props.evaluation.assinatura_base64"
          :src="props.evaluation.assinatura_base64"
          alt="Assinatura do Servidor"
          class="border border-gray-400 rounded-md w-full h-48 object-contain bg-white max-w-lg"
        />
        <p class="font-medium text-gray-700 mt-2 text-center">ASSINATURA DO SERVIDOR</p>
        <p class="text-right w-full max-w-lg text-gray-600 mt-2 text-sm">{{ dataEnvio }}</p>
      </div>
    </div>

    <!-- Pontuação final -->
    <div class="form-section bg-blue-50 p-6 rounded-lg text-right mt-8">
      <div class="font-bold text-2xl text-gray-800">
        Pontuação Total:
  <span class="text-blue-600">{{ grandTotalFormatted }}</span>
      </div>
    </div>
  </DashboardLayout>
</template>

<style scoped>
.form-section {
  margin-bottom: 2rem;
}
.section-title {
  font-size: 1.125rem;
  font-weight: bold;
  color: #374151;
  margin-bottom: 0.5rem;
}
.form-input {
  background: #f3f4f6;
  border: 1px solid #d1d5db;
  border-radius: 0.5rem;
  padding: 0.5rem 0.75rem;
  width: 100%;
  font-size: 1rem;
  margin-bottom: 0.5rem;
}
</style>
