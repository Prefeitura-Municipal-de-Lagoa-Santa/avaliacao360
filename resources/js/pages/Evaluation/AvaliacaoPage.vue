<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowLeftIcon } from 'lucide-vue-next';
import SignaturePad from 'signature_pad';

const props = defineProps({
    form: { type: Object, required: true },
    person: { type: Object, required: true },
    type: { type: String, required: true },
    evaluationRequest: { type: Object, required: true },
    answers: { type: Array, default: () => [] },
    evidencias: { type: String, default: '' },
    assinatura_base64: { type: String, default: '' },
    status: { type: String, default: 'pending' }
});
const isSigned = ref(false);
const canvas = ref<HTMLCanvasElement | null>(null);
let signaturePad: SignaturePad | null = null;

const dynamicTitle = computed(() => {
    const titleMap = {
        autoavaliação: 'Termo de Autoavaliação do Servidor',
        autoavaliacaoGestor: 'Termo de Autoavaliação do Gestor',
        servidor: 'Termo de Avaliação do Servidor',
        gestor: 'Termo de Avaliação do Servidor em cargo de Gestor',
        chefia: 'Termo de Avaliação do Gestor',
    };
    return titleMap[props.type] || 'Termo de Avaliação de Desempenho';
});

const evaluationForm = useForm({
    periodo_avaliado: String(props.form.year),
    nome: props.person.name || '',
    matricula: props.person.registration_number || '',
    cargo: props.person.job_function?.name || props.person.current_position || '-',
    secretaria: props.person.organizational_unit?.all_parents?.all_parents?.name || props.person.organizational_unit?.name || '',
    lotacao: props.person.organizational_unit?.name || '',
    evaluated_user_id: props.person.id || '',
    evaluation_request_id: props.evaluationRequest.id,
    evidencias: props.evidencias || '',
    answers: {},
    assinatura_base64: ''
});

onMounted(() => {
    const initialAnswers = {};
    props.form.group_questions.forEach(group => {
        group.questions.forEach(question => {
            const answerObj = props.answers.find(ans => ans.question_id === question.id);
            initialAnswers[question.id] = answerObj ? answerObj.score : null;
        });
    });
    evaluationForm.answers = initialAnswers;

    if (props.evidencias) {
        evaluationForm.evidencias = props.evidencias;
    }

    if (props.assinatura_base64 && canvas.value) {
        const img = new window.Image();
        img.src = props.assinatura_base64;
        img.onload = function() {
            const ctx = canvas.value!.getContext('2d');
            ctx.clearRect(0, 0, canvas.value!.width, canvas.value!.height);
            ctx.drawImage(img, 0, 0, canvas.value!.width, canvas.value!.height);
            isSigned.value = true;
        }
    }

    if (canvas.value && !props.assinatura_base64) {
        signaturePad = new SignaturePad(canvas.value, {
            backgroundColor: 'rgb(255, 255, 255)'
        });
        canvas.value.addEventListener('pointerup', () => {
            if (signaturePad) {
                setTimeout(() => {
                    isSigned.value = !signaturePad.isEmpty();
                }, 50);
            }
        });

        window.addEventListener('resize', () => resizeCanvas());
        resizeCanvas();
    }
});

function resizeCanvas() {
    if (canvas.value) {
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.value.width = canvas.value.offsetWidth * ratio;
        canvas.value.height = canvas.value.offsetHeight * ratio;
        canvas.value.getContext("2d")!.scale(ratio, ratio);
        if (signaturePad) {
            signaturePad.clear();
            isSigned.value = false;
        }
    }
}

function clearSignature() {
    if (signaturePad) {
        signaturePad.clear();
        isSigned.value = false;
    }
}

const formattedAnswers = computed(() => {
    return Object.entries(evaluationForm.answers)
        .filter(([, score]) => score !== null)
        .map(([question_id, score]) => ({
            question_id: parseInt(question_id),
            score: parseInt(score as string),
        }));
});

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

const cidade = 'Lagoa Santa';
const dataHojeFormatada = computed(() => {
  const hoje = new Date();
  const dia = hoje.toLocaleDateString('pt-BR', { day: '2-digit' });
  const mes = hoje.toLocaleDateString('pt-BR', { month: 'long' });
  const ano = hoje.getFullYear();
  const mesFormatado = mes.charAt(0).toUpperCase() + mes.slice(1);
  return `${cidade}, ${dia} de ${mesFormatado} de ${ano}`;
});

const dataEnvio = computed(() => {
  if (!props.evaluationRequest.evaluation.updated_at) return '';
  const date = new Date(props.evaluationRequest.evaluation.updated_at);
  const dia = date.toLocaleDateString('pt-BR', { day: '2-digit' });
  const mes = date.toLocaleDateString('pt-BR', { month: 'long' });
  const ano = date.getFullYear();
  const mesFormatado = mes.charAt(0).toUpperCase() + mes.slice(1);
  return `${cidade}, ${dia} de ${mesFormatado} de ${ano}`;
});

const isFormReadyToSubmit = computed(() => {
    const allQuestionsAnswered = Object.values(evaluationForm.answers).every(answer => answer !== null);
    return allQuestionsAnswered && isSigned.value;
});

const submitEvaluation = () => {
    if (signaturePad && !signaturePad.isEmpty()) {
        evaluationForm.assinatura_base64 = signaturePad.toDataURL();
    } else if (!props.assinatura_base64) {
        alert('Por favor, assine antes de enviar.');
        return;
    }

    const submissionRoute = route('evaluations.store', { form: props.form.id });

    evaluationForm.transform(data => ({
        answers: formattedAnswers.value,
        evaluated_user_id: data.evaluated_user_id,
        evaluation_request_id: data.evaluation_request_id,
        evidencias: data.evidencias,
        assinatura_base64: data.assinatura_base64,
    })).post(submissionRoute, {
        onSuccess: () => {},
        onError: (errors) => {
            console.error('Erros de validação:', errors);
            alert('Ocorreram erros de validação. Verifique o console.');
        }
    });
};

function goBack() {
    window.history.back();
}

const evaluationRubric = [
    { title: 'Aprimoramento Significativo', text: 'Demonstra comportamento indicando a necessidade de aprimoramento significativo no desenvolvimento da competência.', scores: [0, 10, 20, 30, 40], colorClass: 'bg-red-500' },
    { title: 'Fase Inicial', text: 'Demonstra comportamento alinhado às expectativas para a fase inicial de desenvolvimento da competência.', scores: [50, 60], colorClass: 'bg-orange-400' },
    { title: 'Fase Intermediária', text: 'Demonstra comportamento alinhado às expectativas para a fase intermediária de desenvolvimento da competência.', scores: [70, 80], colorClass: 'bg-yellow-400' },
    { title: 'Fase Avançada', text: 'Demonstra comportamento alinhado às expectativas para a fase avançada de desenvolvimento da competência.', scores: [90], colorClass: 'bg-lime-500' },
    { title: 'Fase Plena', text: 'Demonstra o comportamento alinhado às expectativas para a fase plena de desenvolvimento da competência.', scores: [100], colorClass: 'bg-green-600' }
];
const scoreOptions = [0, 10, 20, 30, 40, 50, 60, 70, 80, 90, 100];
</script>

<template>
    <Head :title="dynamicTitle" />
    <DashboardLayout :pageTitle="props.form.type === 'autoavaliacao' ? 'Autoavaliação' : 'Avaliação de Chefia'">
        <div class="flex justify-between items-center border-b pb-4 mb-6">
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">{{ dynamicTitle }}</h1>
            <button @click="goBack" class="back-btn">
                <ArrowLeftIcon class="size-4 mr-2" />
                Voltar
            </button>
        </div>

        <form v-if="props.status === 'pending'" @submit.prevent="submitEvaluation">
            <div id="form-wrapper" class="space-y-10">
                <div class="form-section">
                    <h3 class="section-title">1 - CICLO DE AVALIAÇÃO</h3>
                    <div class="form-grid sm:grid-cols-3">
                        <div class="form-field sm:col-span-2">
                            <label>PERÍODO AVALIADO:</label>
                            <input type="text" v-model="evaluationForm.periodo_avaliado" class="form-input" readonly>
                        </div>
                    </div>
                </div>
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
                <div class="form-section">
                    <h3 class="section-title">3 - COMPETÊNCIAS AVALIADOS</h3>
                    <div id="evaluation-tables-container" class="space-y-8">
                        <div v-for="group in form.group_questions" :key="group.id" class="overflow-x-auto">
                            <h4 class="text-lg font-semibold text-gray-700 mb-3">{{ group.name }} (Peso: {{ group.weight }}%)</h4>
                            <div class="flex flex-col sm:flex-row border border-gray-300 rounded-lg overflow-hidden mb-4">
                                <div v-for="(item, index) in evaluationRubric" :key="index"
                                    class="flex flex-col flex-1 text-center border-b sm:border-b-0 sm:border-r border-gray-200 last:border-r-0">
                                    <div class="p-3 text-xs text-gray-700 flex-grow flex items-center justify-center"
                                        :class="item.colorClass">
                                        {{ item.text }}
                                    </div>
                                    <div class="p-3 bg-gray-100 border-t border-gray-200">
                                        <div class="flex flex-nowrap justify-center items-center gap-2">
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
                                        <th class="table-header">Fator de Comportamento</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="question in group.questions" :key="question.id" class="border-t">
                                        <td class="table-cell px-4 py-3">
                                            <div class="mb-4 text-gray-800 font-medium">
                                                {{ question.text_content }}
                                            </div>
                                            <div class="flex flex-wrap justify-center items-center gap-2">
                                                <div v-for="score in scoreOptions" :key="score">
                                                    <input type="radio" :name="'question-' + question.id"
                                                        :id="'q' + question.id + 's' + score" :value="score"
                                                        v-model="evaluationForm.answers[question.id]"
                                                        class="sr-only peer" />
                                                    <label :for="'q' + question.id + 's' + score"
                                                        class="flex items-center justify-center w-9 h-9 rounded-full border border-gray-300 bg-white text-sm font-medium text-gray-700 cursor-pointer transition-colors duration-200 ease-in-out hover:bg-gray-100 peer-checked:bg-blue-600 peer-checked:text-white peer-checked:border-blue-700">
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
                <div class="form-section bg-blue-50 p-6 rounded-lg text-right">
                    <div class="font-bold text-2xl text-gray-800">Pontuação Total: <span id="grand-total" class="text-blue-600">{{ grandTotal }}</span></div>
                </div>
                <div class="form-section">
                    <h3 class="section-title">4 - EVIDÊNCIAS</h3>
                    <textarea v-model="evaluationForm.evidencias" class="form-input w-full" rows="5"
                        placeholder="Descreva aqui as evidências observadas durante o período de avaliação..."></textarea>
                </div>
                <div class="form-section">
                    <h3 class="section-title">5 - ASSINATURAS</h3>
                    <p class="text-right mb-12 text-gray-600">{{ dataHojeFormatada }}</p>
                    <div class="flex justify-center pt-8">
                        <div class="w-full max-w-lg text-center">
                            <canvas ref="canvas"
                                class="border border-gray-400 rounded-md w-full h-48 bg-white"></canvas>
                            <p class="font-medium text-gray-700 mt-2">ASSINATURA DO SERVIDOR</p>
                            <button @click.prevent="clearSignature"
                                class="flex items-center mx-auto mt-2 text-sm btn-remove">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round" class="size-4 mr-2">
                                    <path d="m7 21-4.3-4.3c-1-1-1-2.5 0-3.4l9.6-9.6c1-1 2.5-1 3.4 0l5.6 5.6c1 1 1 2.5 0 3.4L13 21H7Z" />
                                    <path d="M22 21H7" />
                                </svg>
                                Limpar Assinatura
                            </button>
                        </div>
                    </div>
                </div>
                <div class="mt-12 text-center">
                    <button type="submit" :disabled="evaluationForm.processing || !isFormReadyToSubmit"
                        class="px-8 py-3 bg-green-600 text-white font-bold rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:bg-gray-400 transition-colors duration-300">
                        Salvar Avaliação
                    </button>
                </div>
            </div>
        </form>
        <div v-else>
            <div class="form-section">
                <h3 class="section-title">1 - CICLO DE AVALIAÇÃO</h3>
                <input type="text" :value="evaluationForm.periodo_avaliado" class="form-input" readonly>
            </div>
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
            <div class="form-section">
                <h3 class="section-title">3 - COMPETÊNCIAS AVALIADAS</h3>
                <div v-for="group in form.group_questions" :key="group.id" class="mb-8">
                    <h4 class="text-lg font-semibold mb-2">{{ group.name }}</h4>
                    <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left font-semibold">Questão</th>
                                <th class="px-4 py-2 text-center font-semibold" style="width: 110px;">Nota</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="question in group.questions" :key="question.id" class="border-t">
                                <td class="px-4 py-3 align-middle">{{ question.text_content }}</td>
                                <td class="px-0 py-2 align-middle">
                                    <span class="block mx-auto text-blue-800 font-bold rounded text-lg bg-blue-100"
                                        style="width: 70px; min-width: 70px; padding: 0.5rem 0; text-align: center;">
                                        {{
                                            props.answers.find(ans => ans.question_id === question.id)?.score ?? '-'
                                        }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="form-section">
                <h3 class="section-title">4 - EVIDÊNCIAS</h3>
                <textarea :value="props.evidencias" class="form-input w-full" rows="5" readonly></textarea>
            </div>
        <div class="form-section">
            <h3 class="section-title">Assinatura</h3>
            <div class="flex flex-col justify-center items-center pt-8">
                <img
                    v-if="props.assinatura_base64"
                    :src="props.assinatura_base64"
                    alt="Assinatura do Servidor"
                    class="border border-gray-400 rounded-md w-full h-48 object-contain bg-white max-w-lg"
                />
                <p class="font-medium text-gray-700 mt-2 text-center">ASSINATURA DO SERVIDOR</p>
                <p class="text-right w-full max-w-lg text-gray-600 mt-2 text-sm">{{ dataEnvio }}</p>
            </div>
        </div>
            <div class="form-section bg-blue-50 p-6 rounded-lg text-right">
                <div class="font-bold text-2xl text-gray-800">
                    Pontuação Total:
                    <span class="text-blue-600">{{ grandTotal }}</span>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>
