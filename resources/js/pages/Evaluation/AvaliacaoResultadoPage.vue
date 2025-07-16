<script setup lang="ts">
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ArrowLeftIcon } from 'lucide-vue-next';

const props = defineProps({
    form: Object,
    person: Object,
    type: String,
    evaluation: Object
});

function goBack() { window.history.back(); }

// Calcule a nota final igual ao preenchimento
function calcularGrandTotal() {
    let total = 0;
    props.form.group_questions.forEach(group => {
        group.questions.forEach(question => {
            const answer = props.evaluation.answers?.find(ans => ans.question_id === question.id);
            if (answer && answer.score !== null && answer.score !== undefined) {
                const questionScore = (parseInt(answer.score) / 100.0) * group.weight;
                total += questionScore;
            }
        });
    });
    return Math.round(total);
}
const grandTotal = calcularGrandTotal();
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
        {{ props.evaluation.answers }}
        <!-- Campos dinâmicos -->
        <div class="form-section">
            <h3 class="section-title">Identificação do Servidor</h3>
            <div class="form-grid sm:grid-cols-3">
                <div class="form-field sm:col-span-3">
                    <label>NOME:</label>
                    <input type="text" :value="person.name" class="form-input" readonly>
                </div>
                <!-- outros campos, como matrícula, cargo, etc -->
            </div>
        </div>

        <!-- Tabela de perguntas e respostas -->
        <div class="form-section">
            <h3 class="section-title">Comportamentos Avaliados</h3>
            <div v-for="group in form.group_questions" :key="group.id" class="mb-8">
                <h4 class="text-lg font-semibold mb-2">{{ group.name }}</h4>
                <table class="w-full bg-white border border-gray-200 rounded-lg">
                    <thead>
                        <tr>
                            <th>Questão</th>
                            <th>Resposta</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="question in group.questions" :key="question.id">
                            <td>{{ question.text_content }}</td>
                            <td>
                                <span class="inline-block bg-blue-100 text-blue-800 font-bold px-4 py-1 rounded text-lg">
                                    {{
                                        props.evaluation.answers?.find(ans => ans.question_id === question.id)?.score ?? '-'
                                    }}
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
            <textarea :value="props.evaluation.evidencias" class="form-input w-full" rows="5" readonly></textarea>
        </div>

        <!-- Assinatura -->
        <div class="form-section">
            <h3 class="section-title">Assinatura</h3>
            <div class="flex justify-center pt-8">
                <img
                    v-if="props.evaluation.assinatura_base64"
                    :src="props.evaluation.assinatura_base64"
                    alt="Assinatura do Servidor"
                    class="border border-gray-400 rounded-md w-full h-48 object-contain bg-white"
                />
                <p class="font-medium text-gray-700 mt-2">Assinatura do Servidor</p>
            </div>
        </div>

        <!-- Pontuação final -->
        <div class="form-section bg-blue-50 p-6 rounded-lg text-right">
            <div class="font-bold text-2xl text-gray-800">Pontuação Total:
                <span class="text-blue-600">{{ grandTotal }}</span>
            </div>
        </div>
    </DashboardLayout>
</template>
