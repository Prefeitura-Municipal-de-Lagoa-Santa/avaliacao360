<script setup lang="ts">
import { ref, computed, onMounted, reactive} from 'vue';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowLeftIcon } from 'lucide-vue-next';

const props = defineProps({
    form: {
        type: Object,
        required: true,
    },
    person: { // O usuário autenticado virá aqui
        type: Array,
        required: true,
    }
});

console.log(props);

const evaluationForm = useForm({
    // O ID do usuário avaliado agora é preenchido automaticamente!
    //evaluated_user_id: props.users.length > 0 ? props.users[0].id : null, 
    periodo_avaliado: String(props.form.year),
    nome: props.person.name || '',
    matricula: props.person.registration_number || '',
    cargo: props.person.current_position || '',
    secretaria: props.person.organizational_unit?.all_parents?.all_parents.name || '',
    lotacao: props.person.organizational_unit?.name || '',
    evaluated_user_id: props.person.user_id, 
    evidencias: '',
    answers: {},
    evidencias: '',

    
});

// Inicializa o objeto 'answers' com todas as perguntas do formulário.
onMounted(() => {
    const initialAnswers = {};
    props.form.group_questions.forEach(group => {
        group.questions.forEach(question => {
            initialAnswers[question.id] = null;
        });
    });
    evaluationForm.answers = initialAnswers;
});

// Mapeia o objeto de respostas para o formato de array esperado pelo backend.
const formattedAnswers = computed(() => {
    return Object.entries(evaluationForm.answers)
        .filter(([, score]) => score !== null)
        .map(([question_id, score]) => ({
            question_id: parseInt(question_id),
            score: parseInt(score),
        }));
});

// Calcula a pontuação total da avaliação em tempo real.
const grandTotal = computed(() => {
    let total = 0;
    props.form.group_questions.forEach(group => {
        group.questions.forEach(question => {
            const score = evaluationForm.answers[question.id];
            if (score !== null && score !== undefined) {
                const questionScore = (parseInt(score) / 100.0) * question.weight;
                total += questionScore;
            }
        });
    });
    return Math.round(total);
});

// Função de submissão do formulário
const submitEvaluation = () => {
    // Transforma os dados antes de enviar, incluindo apenas o necessário
    evaluationForm.transform(data => ({
        answers: formattedAnswers.value,
        evaluated_user_id: data.evaluated_user_id,
        // Adicione outros campos se o backend precisar, como 'evidencias'
        // evidencias: data.evidencias,
    })).post(route('evaluations.autoavaliacao.store', { form: props.form.id }), {
        onSuccess: () => {
            alert('Autoavaliação salva com sucesso!');
        },
        onError: (errors) => {
            console.error('Erros de validação:', errors);
            alert('Ocorreram erros de validação. Verifique o console.');
        }
    });
};

// Formata a data atual para exibição.
const formattedDate = computed(() => {
    return new Date().toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: 'long',
        year: 'numeric'
    });
});

// Função para navegar para a página anterior.
function goBack() {
  window.history.back();
}

const evaluationRubric = [
     {
        title: 'Aprimoramento Significativo',
        text: 'Demonstra comportamento indicando a necessidade de aprimoramento significativo no desenvolvimento da competência.',
        scores: [0, 10, 20, 30, 40],
        colorClass: 'bg-red-500'
    },
    {
        title: 'Fase Inicial',
        text: 'Demonstra comportamento alinhado às expectativas para a fase inicial de desenvolvimento da competência.',
        scores: [50, 60],
        colorClass: 'bg-orange-400'
    },
    {
        title: 'Fase Intermediária',
        text: 'Demonstra comportamento alinhado às expectativas para a fase intermediária de desenvolvimento da competência.',
        scores: [70, 80],
        colorClass: 'bg-yellow-400'
    },
    {
        title: 'Fase Avançada',
        text: 'Demonstra comportamento alinhado às expectativas para a fase avançada de desenvolvimento da competência.',
        scores: [90],
        colorClass: 'bg-lime-500'
    },
    {
        title: 'Fase Plena',
        text: 'Demonstra o comportamento alinhado às expectativas para a fase plena de desenvolvimento da competência.',
        scores: [100],
        colorClass: 'bg-green-600'
    }
];

const scoreOptions = [0, 10, 20, 30, 40, 50, 60, 70, 80, 90, 100];

</script>

<template>
    <Head title="Autoavaliação de Desempenho" /> 
  <DashboardLayout pageTitle="Autoavaliação">
        
        <div class="flex justify-between items-center border-b pb-4 mb-6">
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">Termo de Autoavaliação de Desempenho</h1> 
            <button @click="goBack" class="back-btn">
              <ArrowLeftIcon class="size-4 mr-2" />
              Voltar
            </button>
        </div>

        <!-- Formulário -->
        <form @submit.prevent="submitEvaluation">
            <div id="form-wrapper" class="space-y-10">

                <!-- Seção 1: Ciclo de Avaliação -->
                <div class="form-section">
                    <h3 class="section-title">1 - CICLO DE AVALIAÇÃO</h3>
                    <div class="form-grid sm:grid-cols-3">
                        <div class="form-field sm:col-span-2">
                            <label>PERÍODO AVALIADO:</label>
                            <input type="text" v-model="evaluationForm.periodo_avaliado" class="form-input" readonly>
                        </div>
                    </div>
                </div>

                <!-- Seção 2: Identificação do servidor -->
                <div class="form-section">
                    <h3 class="section-title">2 - IDENTIFICAÇÃO DO SERVIDOR </h3>
                    <div class="form-grid sm:grid-cols-3">
                        <div class="form-field sm:col-span-3">
                            <label>NOME:</label>
                            <input type="text" v-model="evaluationForm.nome" class="form-input" readonly>
                        </div>
                        <div class="form-field sm:col-span-2">
                            <label>MATRÍCULA:</label>
                            <input type="text" v-model="evaluationForm.matricula" class="form-input" readonly>
                        </div>
                        <div class="form-field sm:col-span-1">
                            <label>CARGO / FUNÇÃO:</label>
                            <input type="text" v-model="evaluationForm.cargo" class="form-input" readonly>
                        </div>
                        <div class="form-field sm:col-span-3">
                            <label>SECRETARIA:</label>
                            <input type="text" v-model="evaluationForm.secretaria" class="form-input" readonly>
                        </div>
                        <div class="form-field sm:col-span-3">
                            <label>LOTAÇÃO:</label>
                            <input type="text" v-model="evaluationForm.lotacao" class="form-input" readonly>
                        </div>
                    </div>
                </div>

                <!-- Seção 3: Comportamentos Avaliados -->
                <div class="form-section">
                    <h3 class="section-title">3 - COMPORTAMENTOS AVALIADOS</h3>
                     <div id="evaluation-tables-container" class="space-y-8">
                        <div v-for="group in form.group_questions" :key="group.id" class="overflow-x-auto">
                            <h4 class="text-lg font-semibold text-gray-700 mb-3">{{ group.name }} (Peso: {{ group.weight }}%)</h4>

                            <div class="flex flex-col sm:flex-row border border-gray-300 rounded-lg overflow-hidden mb-4">
                                <div v-for="(item, index) in evaluationRubric" :key="index" class="flex flex-col flex-1 text-center border-b sm:border-b-0 sm:border-r border-gray-200 last:border-r-0">
                                   
                                    <div class="p-3 text-xs text-gray-700 flex-grow" :class="item.colorClass">
                                        {{ item.text }}
                                    </div>

                                    <div class="p-3 bg-gray-100 border-t border-gray-200">
                                        <div class="flex flex-wrap justify-center items-center gap-2">
                                            <div v-for="score in item.scores" :key="score"
                                                class="flex items-center justify-center w-8 h-8 rounded-full border border-gray-400 bg-white text-xs font-semibold text-gray-700">
                                                {{ score }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="table-header w-2/3">Fator de Competência</th>
                                        <th class="table-header w-1/3">Pontuação (0 a 100)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="question in group.questions" :key="question.id" class="border-t">
                                        
                                        <td class="table-cell px-4 py-3">
                                            
                                            <div class="mb-4 text-gray-800 font-medium">
                                                {{ question.text_content }}
                                            </div>

                                            <div class="flex flex-wrap justify-around items-center gap-2 p-2 rounded-md bg-gray-50 border">
                                                
                                                <div v-for="score in scoreOptions" :key="score">
                                                    <input 
                                                        type="radio"
                                                        :name="'question-' + question.id"
                                                        :id="'q' + question.id + 's' + score"
                                                        :value="score"
                                                        v-model="evaluationForm.answers[question.id]"
                                                        class="sr-only peer"
                                                    />
                                                    <label 
                                                        :for="'q' + question.id + 's' + score"
                                                        class="flex items-center justify-center w-9 h-9 rounded-full border border-gray-300 bg-white text-sm font-medium text-gray-700 cursor-pointer transition-colors duration-200 ease-in-out hover:bg-gray-100 peer-checked:bg-blue-600 peer-checked:text-white peer-checked:border-blue-700"
                                                    >
                                                        {{ score }}
                                                    </label>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Seção de Pontuação Total -->
                <div class="form-section bg-blue-50 p-6 rounded-lg text-right">
                     <div class="font-bold text-2xl text-gray-800">Pontuação Total: <span id="grand-total" class="text-blue-600">{{ grandTotal }}</span></div>
                </div>

                <!-- Seção 4: Evidências -->
                <div class="form-section">
                    <h3 class="section-title">5 - EVIDÊNCIAS</h3>
                    <textarea v-model="evaluationForm.evidencias" class="form-input w-full" rows="5" placeholder="Descreva aqui as evidências observadas durante o período de avaliação..."></textarea>
                </div>

                <!-- Seção 5: Assinaturas -->
                <div class="form-section">
                    <h3 class="section-title">6 - ASSINATURAS</h3>
                    <p class="text-right mb-12 text-gray-600">Lagoa Santa, {{ formattedDate }}</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-16 pt-8">
                        <div class="text-center">
                            <div class="signature-line"></div>
                            <p class="text-center font-medium text-gray-700">ASSINATURA DO SERVIDOR</p>
                        </div>
                        <div class="text-center">
                            <div class="signature-line"></div>
                            <p class="text-center font-medium text-gray-700">ASSINATURA DA CHEFIA IMEDIATA</p>
                        </div>
                    </div>
                </div>

                <!-- Botão de Salvar -->
                <div class="mt-12 text-center">
                    <button type="submit" :disabled="evaluationForm.processing || !evaluationForm.evaluated_user_id" class="px-8 py-3 bg-green-600 text-white font-bold rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:bg-gray-400 transition-colors duration-300">
                        Salvar Avaliação
                    </button>
                </div>
            </div>
        </form>
    </DashboardLayout>
</template>