<script setup>
import { ref, computed, onMounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Head } from '@inertiajs/vue3';
import { ArrowLeftIcon } from 'lucide-vue-next';


// As propriedades (props) que o componente recebe do controller do Laravel.
const props = defineProps({
    form: {
        type: Object,
        required: true,
    },
    // Lista de usuários para avaliar, vinda do Controller.
    users: {
        type: Array,
        required: true
    }
});

console.log(props);

// Utilizando o helper 'useForm' do Inertia para gerenciar os dados do formulário.
const evaluationForm = useForm({
    evaluated_user_id: null, // ID do servidor que está sendo avaliado.
    periodo_avaliado: '',
    chefia_nome: '',
    chefia_matricula: '',
    chefia_cargo: '',
    chefia_secretaria: '',
    chefia_lotacao: '',
    equipe_secretaria: '',
    equipe_lotacao: '',
    evidencias: '',
    answers: {},
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

// Função para submeter o formulário para o backend.
const submitEvaluation = () => {
    const submissionData = {
        ...evaluationForm.data(),
        answers: formattedAnswers.value,
    };

    evaluationForm.transform(() => submissionData).post(route('evaluations.chefia.store', { form: props.form.id }), {
        onSuccess: () => {
            alert('Avaliação salva com sucesso!');
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

</script>

<template>
    <Head title="Avaliação de Desempenho da Chefia" />

    <!-- Container Principal -->
    <div class="container mx-auto p-4 sm:p-6 lg:p-8 bg-white shadow-lg rounded-lg my-8">
        
        <!-- Cabeçalho da Página -->
        <div class="flex justify-between items-center border-b pb-4 mb-6">
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">Termo de Avaliação de Desempenho</h1>
            <button @click="goBack" class="back-btn">
          <ArrowLeftIcon class="size-4 mr-2" />
          Voltar
        </button>
        </div>

        <!-- Formulário -->
        <form @submit.prevent="submitEvaluation">
            <div id="chefia-form-wrapper" class="space-y-10">

                <!-- Seção 1: Ciclo de Avaliação -->
                <div class="form-section">
                    <h3 class="section-title">1 - CICLO DE AVALIAÇÃO</h3>
                    <div class="form-grid">
                        <div class="form-field">
                            <label>PERÍODO AVALIADO:</label>
                            <input type="text" v-model="evaluationForm.periodo_avaliado" placeholder="Ex: 01/01/2024 a 31/12/2024" class="form-input">
                        </div>
                    </div>
                </div>

                <!-- Seção 2: Identificação da Chefia -->
                <div class="form-section">
                    <h3 class="section-title">2 - IDENTIFICAÇÃO DA CHEFIA IMEDIATA DA EQUIPE</h3>
                    <div class="form-grid sm:grid-cols-3">
                        <div class="form-field sm:col-span-3">
                            <label>NOME:</label>
                            <input type="text" v-model="evaluationForm.chefia_nome" class="form-input">
                        </div>
                        <div class="form-field sm:col-span-1">
                            <label>MATRÍCULA:</label>
                            <input type="text" v-model="evaluationForm.chefia_matricula" class="form-input">
                        </div>
                        <div class="form-field sm:col-span-2">
                            <label>CARGO / FUNÇÃO:</label>
                            <input type="text" v-model="evaluationForm.chefia_cargo" class="form-input">
                        </div>
                        <div class="form-field sm:col-span-3">
                            <label>SECRETARIA:</label>
                            <input type="text" v-model="evaluationForm.chefia_secretaria" class="form-input">
                        </div>
                        <div class="form-field sm:col-span-3">
                            <label>LOTAÇÃO:</label>
                            <input type="text" v-model="evaluationForm.chefia_lotacao" class="form-input">
                        </div>
                    </div>
                </div>

                <!-- Seção 3: Identificação do Servidor Avaliado -->
                <div class="form-section">
                    <h3 class="section-title">3 - IDENTIFICAÇÃO DO SERVIDOR AVALIADO</h3>
                    <div class="form-grid">
                        <div class="form-field">
                            <label for="evaluated_user">SERVIDOR:</label>
                            <select id="evaluated_user" v-model="evaluationForm.evaluated_user_id" class="form-input" required>
                                <option :value="null" disabled>-- Selecione um servidor --</option>
                                <option v-for="user in users" :key="user.id" :value="user.id">
                                    {{ user.name }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Seção 4: Comportamentos Avaliados -->
                <div class="form-section">
                    <h3 class="section-title">4 - COMPORTAMENTOS AVALIADOS</h3>
                    <div id="chefia-evaluation-tables-container" class="space-y-8">
                        <div v-for="group in form.group_questions" :key="group.id" class="overflow-x-auto">
                            <h4 class="text-lg font-semibold text-gray-700 mb-3">{{ group.name }} (Peso: {{ group.weight }}%)</h4>
                            <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="table-header w-2/3">Fator de Competência</th>
                                        <th class="table-header w-1/3">Pontuação (0 a 100)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="question in group.questions" :key="question.id" class="border-t">
                                        <td class="table-cell">{{ question.text_content }}</td>
                                        <td class="table-cell text-center">
                                            <input type="number" min="0" max="100" step="10"
                                                v-model="evaluationForm.answers[question.id]"
                                                class="form-input w-24 text-center"
                                            />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Seção de Pontuação Total -->
                <div class="form-section bg-blue-50 p-6 rounded-lg text-right">
                     <div class="font-bold text-2xl text-gray-800">Pontuação Total: <span id="chefia-grand-total" class="text-blue-600">{{ grandTotal }}</span></div>
                </div>

                <!-- Seção 5: Evidências -->
                <div class="form-section">
                    <h3 class="section-title">5 - EVIDÊNCIAS</h3>
                    <textarea v-model="evaluationForm.evidencias" class="form-input w-full" rows="5" placeholder="Descreva aqui as evidências observadas durante o período de avaliação..."></textarea>
                </div>

                <!-- Seção 6: Assinaturas -->
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
    </div>
</template>

