<script setup lang="ts">
import { ref, computed, onMounted, watch  } from 'vue';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeftIcon } from 'lucide-vue-next';
import SignaturePad from 'signature_pad';

const props = defineProps<{
    pdiRequest: {
        id: number;
        pdi_id: number; // Incluído para referência
        manager_id: number; // Incluído para referência
        person_id: number; // Incluído para referência
        pdi: {
            id: number;
            year: number;
            form: {
                id: number;
                name: string;
                group_questions: {
                    id: number;
                    name: string;
                    questions: {
                        id: number;
                        text_content: string;
                    }[]
                }[]
            };
        };
        person: {
            id: number;
            name: string;
            current_position: string | null;
            job_function: { name: string } | null;
        };
        manager: {
            id: number;
            name: string;
            job_function: { name: string } | null;
        };
        status: string;
        manager_signature_base64: string | null;
        person_signature_base64: string | null;
    };
    pdiAnswers: {
        question_id: number;
        response_content: string;
    }[] | null;
    loggedPerson: {
        id: number;
        name: string;
        cpf: string;
    } | null;
    canInteract?: boolean;
}>();

const page = usePage();

// ID da pessoa logada (corrigido para usar a pessoa do backend)
const loggedInPersonId = computed(() => {
    return props.loggedPerson?.id;
});
// --- CORREÇÃO: Refs e instâncias de assinatura separadas ---
const managerSignatureCanvas = ref<HTMLCanvasElement | null>(null);
const employeeSignatureCanvas = ref<HTMLCanvasElement | null>(null);
let managerSignaturePad: SignaturePad | null = null;
let employeeSignaturePad: SignaturePad | null = null;
const isSigned = ref(false);
// -----------------------------------------------------------

const initialAnswers: Record<string, string> = {};
if (props.pdiRequest.pdi.form?.group_questions) {
    props.pdiRequest.pdi.form.group_questions.forEach(group => {
        group.questions.forEach(question => {
            initialAnswers[`question-${question.id}`] = ''; // Inicia respostas como vazias
        });
    });
}

const form = useForm({
    answers: initialAnswers,
    signature_base64: '',
    status: props.pdiRequest.status,
    
});

watch(() => props.pdiRequest.id, (newId) => {
    form.reset();
    const newAnswers: Record<string, string> = {};
    if (props.pdiRequest.pdi.form?.group_questions) {
        props.pdiRequest.pdi.form.group_questions.forEach(group => {
            group.questions.forEach(question => {
                newAnswers[`question-${question.id}`] = ''; // Inicia como vazio
            });
        });
    }
    // Atualiza o objeto 'answers' dentro do 'form' com a nova estrutura
    form.answers = newAnswers;
    // Se o novo PDI já tiver respostas salvas (ex: servidor a dar ciência),
    // preenche o formulário com elas.
    if (props.pdiAnswers) {
        props.pdiAnswers.forEach(answer => {
            if (form.answers.hasOwnProperty(`question-${answer.question_id}`)) {
                form.answers[`question-${answer.question_id}`] = answer.response_content;
            }
        });
    }
});

const isManagerFilling = computed(() => {
    return props.pdiRequest.status === 'pending_manager_fill' &&
           props.pdiRequest.manager_id === loggedInPersonId.value &&
           (props.canInteract !== false); // Verificar se pode interagir
});

const isEmployeeSigning = computed(() => {
    return props.pdiRequest.status === 'pending_employee_signature' &&
           props.pdiRequest.person_id === loggedInPersonId.value &&
           (props.canInteract !== false); // Verificar se pode interagir
});

const isCompleted = computed(() => props.pdiRequest.status === 'completed');
const isReadOnly = computed(() => {
    return !isManagerFilling.value && !isEmployeeSigning.value;
});


function setupSignaturePad(canvas: HTMLCanvasElement): SignaturePad {
    const ratio = Math.max(window.devicePixelRatio || 1, 1);
    canvas.width = canvas.offsetWidth * ratio;
    canvas.height = canvas.offsetHeight * ratio;
    canvas.getContext("2d")!.scale(ratio, ratio);
    const signaturePad = new SignaturePad(canvas, { backgroundColor: 'rgb(255, 255, 255)' });
    signaturePad.addEventListener('endStroke', () => {
        isSigned.value = !signaturePad.isEmpty();
    });
    return signaturePad;
}

onMounted(() => {
    // Popula com respostas existentes (se houver)
    
    if (props.pdiAnswers) {
        props.pdiRequest.pdi.form.group_questions.forEach(group => {
            group.questions.forEach(question => {
                const answer = props.pdiAnswers?.find(a => a.question_id === question.id);
                if (answer) {
                    form.answers[`question-${question.id}`] = answer.response_content;
                }
            });
        });
    }
    

    // Configura o pad de assinatura correto
    if (isManagerFilling.value && managerSignatureCanvas.value) {
        managerSignaturePad = setupSignaturePad(managerSignatureCanvas.value);
    }
    if (isEmployeeSigning.value && employeeSignatureCanvas.value) {
        employeeSignaturePad = setupSignaturePad(employeeSignatureCanvas.value);
    }
});

// --- CORREÇÃO: Funções de limpar separadas ---
function clearManagerSignature() {
    if (managerSignaturePad) {
        managerSignaturePad.clear();
        isSigned.value = false;
    }
}

function clearEmployeeSignature() {
    if (employeeSignaturePad) {
        employeeSignaturePad.clear();
        isSigned.value = false;
    }
}
// ---------------------------------------------

function submitForm() {
    let activePad: SignaturePad | null = null;
    if (isManagerFilling.value) activePad = managerSignaturePad;
    if (isEmployeeSigning.value) activePad = employeeSignaturePad;

    if (activePad && !activePad.isEmpty()) {
        form.signature_base64 = activePad.toDataURL();
    } else {
        alert('A assinatura é obrigatória.');
        return;
    }

    const submissionRoute = route('pdi.update', { pdiRequest: props.pdiRequest.id });

    form.transform(data => {
        const payload: { signature_base64: string, answers?: any } = {
            signature_base64: data.signature_base64
        };
        if (isManagerFilling.value) {
            payload.answers = Object.entries(data.answers).map(([key, content]) => ({
                question_id: parseInt(key.split('-')[1]),
                response_content: content,
            }));
        }
        return payload;
    }).put(submissionRoute, {
        onError: (errors) => {
            console.error('Erros:', errors);
            alert('Ocorreu um erro ao salvar o PDI.');
        }
    });
}

function goBack() {
    window.history.back();
}
</script>

<template>
    <Head title="Formulário PDI" />
    <DashboardLayout pageTitle="Plano de Desenvolvimento Individual">
        <!-- Aviso quando PDI está fora do prazo -->
        <div v-if="canInteract === false" class="mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded-md">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-yellow-800">PDI Fora do Prazo</h3>
                    <div class="mt-2 text-sm text-yellow-700">
                        <p>Este PDI está fora do prazo para preenchimento/assinatura. Você pode visualizar o conteúdo, mas não pode fazer alterações.</p>
                    </div>
                </div>
            </div>
        </div>

        <form @submit.prevent="submitForm">
            <div class="mb-6 bg-white rounded-md shadow p-6">
                <div class="flex justify-between items-center border-b pb-4 mb-6">
                    <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">{{ pdiRequest.pdi.form.name }}</h1>
                    <button @click="goBack" type="button" class="back-btn">
                        <ArrowLeftIcon class="size-4 mr-2" />
                        Voltar
                    </button>
                </div>
                <h3 class="text-xl font-semibold mb-4">Identificação</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                     <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Gestor(a):</label>
                        <p class="text-gray-900">{{ pdiRequest.manager.name }}</p>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Função do Gestor(a):</label>
                        <p class="text-gray-900">{{ pdiRequest.manager.job_function?.name || 'Não informado' }}</p>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Servidor(a):</label>
                        <p class="text-gray-900">{{ pdiRequest.person.name }}</p>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Função:</label>
                        <p class="text-gray-900">{{ pdiRequest.person.job_function?.name || pdiRequest.person.current_position || 'Não informado' }}</p>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Ano:</label>
                        <p class="text-gray-900">{{ pdiRequest.pdi.year }}</p>
                    </div>
                </div>
            </div>

            <div v-for="group in pdiRequest.pdi.form.group_questions" :key="group.id" class="mb-6 bg-white rounded-md shadow p-6">
                <h3 class="text-lg font-semibold mb-4">{{ group.name }}</h3>
                <div v-for="question in group.questions" :key="question.id" class="mb-4">
                    <label :for="'question-' + question.id" class="block text-gray-700 text-sm font-bold mb-2">{{ question.text_content }}</label>
                    <textarea
                        :id="'question-' + question.id"
                        v-model="form.answers[`question-${question.id}`]"
                        :readonly="!isManagerFilling"
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        rows="3"
                    ></textarea>
                </div>
            </div>

             <div class="mb-6 bg-white rounded-md shadow p-6">
                <h3 class="text-lg font-semibold mb-4">Assinaturas</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-4">
                    <div class="text-center">
                        <p class="font-medium text-gray-700 mb-2">Assinatura do Gestor</p>
                        <img v-if="pdiRequest.manager_signature_base64" :src="pdiRequest.manager_signature_base64" alt="Assinatura do Gestor" class="border rounded-md w-full max-w-lg h-48 object-contain bg-gray-100 mx-auto" />
                        <div v-else-if="isManagerFilling" class="w-full max-w-lg mx-auto">
                           <canvas ref="managerSignatureCanvas" class="border border-gray-400 rounded-md w-full h-48 bg-white"></canvas>
                        </div>
                        <div v-else class="border rounded-md w-full max-w-lg h-48 bg-gray-100 flex items-center justify-center text-gray-500 mx-auto">Pendente</div>
                        <p class="text-gray-700 mt-2">{{ pdiRequest.manager.name }}</p>
                        <button v-if="isManagerFilling" @click.prevent="clearManagerSignature" type="button" class="btn-remove text-sm mt-2">Limpar Assinatura</button>
                    </div>

                    <div class="text-center">
                        <p class="font-medium text-gray-700 mb-2">Assinatura do Servidor</p>
                        <img v-if="pdiRequest.person_signature_base64" :src="pdiRequest.person_signature_base64" alt="Assinatura do Servidor" class="border rounded-md w-full max-w-lg h-48 object-contain bg-gray-100 mx-auto" />
                         <div v-else-if="isEmployeeSigning" class="w-full max-w-lg mx-auto">
                           <canvas ref="employeeSignatureCanvas" class="border border-gray-400 rounded-md w-full h-48 bg-white"></canvas>
                         </div>
                        <div v-else class="border rounded-md w-full max-w-lg h-48 bg-gray-100 flex items-center justify-center text-gray-500 mx-auto">Pendente</div>
                        <p class="text-gray-700 mt-2">{{ pdiRequest.person.name }}</p>
                        <button v-if="isEmployeeSigning" @click.prevent="clearEmployeeSignature" type="button" class="btn-remove text-sm mt-2">Limpar Assinatura</button>
                    </div>
                </div>
            </div>

            <div v-if="!isReadOnly" class="flex justify-end mt-8">
                <button type="submit" :disabled="form.processing || !isSigned" class="btn-green">
                    {{ isManagerFilling ? 'Salvar e Enviar para Ciência' : 'Confirmar Ciência e Assinar' }}
                </button>
            </div>
        </form>
    </DashboardLayout>
</template>
