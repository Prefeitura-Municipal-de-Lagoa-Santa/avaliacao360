<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import SignaturePad from 'signature_pad';

const props = defineProps<{
  pdiRequest: any;
}>();

const page = usePage();
const currentUserPersonId = computed(() => page.props.auth.user.person_id); // Supondo que você tenha o ID da pessoa na sessão

const form = useForm({
    // Campos do PDI
    development_goals: props.pdiRequest.pdi.development_goals || '',
    actions_needed: props.pdiRequest.pdi.actions_needed || '',
    manager_feedback: props.pdiRequest.pdi.manager_feedback || '',
    // Assinatura
    signature_base64: '',
    // Campo para ajudar o controller a validar
    status: props.pdiRequest.status,
});

const isManagerFilling = computed(() => props.pdiRequest.status === 'pending_manager_fill' && props.pdiRequest.manager_id === currentUserPersonId.value);
const isEmployeeSigning = computed(() => props.pdiRequest.status === 'pending_employee_signature' && props.pdiRequest.person_id === currentUserPersonId.value);
const isCompleted = computed(() => props.pdiRequest.status === 'completed');
const isReadOnly = computed(() => !isManagerFilling.value && !isEmployeeSigning.value);

const canvas = ref<HTMLCanvasElement | null>(null);
let signaturePad: SignaturePad | null = null;

onMounted(() => {
    if (canvas.value && !isReadOnly.value) {
        signaturePad = new SignaturePad(canvas.value);
    }
});

function submitForm() {
    if (signaturePad && !signaturePad.isEmpty()) {
        form.signature_base64 = signaturePad.toDataURL();
    } else {
        alert('A assinatura é obrigatória.');
        return;
    }

    form.put(route('pdi.update', props.pdiRequest.id));
}
</script>

<template>
    <Head title="Formulário PDI" />
    <DashboardLayout pageTitle="Plano de Desenvolvimento Individual">
        <form @submit.prevent="submitForm">
            <div>
                <h3 class="section-title">Identificação do Servidor</h3>
                <p>Nome: {{ pdiRequest.person.name }}</p>
                </div>

            <div>
                <h3 class="section-title">Plano de Desenvolvimento</h3>
                
                <label>Metas de Desenvolvimento</label>
                <textarea v-model="form.development_goals" :readonly="!isManagerFilling"></textarea>
                
                <label>Ações Necessárias</label>
                <textarea v-model="form.actions_needed" :readonly="!isManagerFilling"></textarea>

                <label>Feedback do Gestor</label>
                <textarea v-model="form.manager_feedback" :readonly="!isManagerFilling"></textarea>
            </div>

            <div class="grid grid-cols-2 gap-8">
                <div>
                    <h4>Assinatura do Gestor</h4>
                    <img v-if="pdiRequest.manager_signature_base64" :src="pdiRequest.manager_signature_base64" />
                    <canvas v-if="isManagerFilling" ref="canvas" class="border"></canvas>
                    <p>{{ pdiRequest.manager.name }}</p>
                </div>
                <div>
                    <h4>Assinatura do Servidor</h4>
                    <img v-if="pdiRequest.person_signature_base64" :src="pdiRequest.person_signature_base64" />
                    <canvas v-if="isEmployeeSigning" ref="canvas" class="border"></canvas>
                     <p>{{ pdiRequest.person.name }}</p>
                </div>
            </div>

            <button type="submit" v-if="!isReadOnly" :disabled="form.processing">
                {{ isManagerFilling ? 'Salvar e Enviar para Ciência' : 'Confirmar Ciência e Assinar' }}
            </button>
        </form>
    </DashboardLayout>
</template>