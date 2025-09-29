<script setup lang="ts">
import { computed, watch } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription, CardFooter } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

// 1. Tipos e Interfaces
interface User {
  id: number;
  name: string;
  email: string;
  cpf: string | null;
}

interface BreadcrumbItem {
  title: string;
  href?: string;
}

// 2. Props recebidas do Controller
const props = defineProps<{
    user: User;
    errors: Record<string, string>;
}>();

// --- LÓGICA DE NOTIFICAÇÕES (FLASH MESSAGES) ---
const flash = computed(() => usePage().props.flash as { success?: string; error?: string; warning?: string });

// --- ESTADO DO FORMULÁRIO ---
// Inicializa o formulário com o CPF do usuário, se já existir
const form = useForm({
    cpf: props.user.cpf || '',
});

// --- BREADCRUMBS ---
const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Configurações',
        href: '#', // Ou a rota principal de configurações, se houver
    },
    {
        title: 'Meu CPF',
    },
];

// --- MÉTODOS DE AÇÃO ---
const submitCpf = () => {
    // Usa uma rota PATCH para atualizar o perfil do usuário logado
    form.transform(data => ({
        ...data,
        cpf: data.cpf.replace(/\D/g, ''),
    })).put(route('profile.cpf.update'), {
        preserveScroll: true,
    });
};


watch(() => form.cpf, (newValue, oldValue) => {
    // Aplica a máscara e atualiza o valor no formulário
    // Isso garante que o usuário veja o CPF formatado enquanto digita
    form.cpf = applyCpfMask(newValue);
});

const applyCpfMask = (value: string): string => {
    if (!value) return '';
    // Remove todos os caracteres que não são dígitos
    const digitsOnly = value.replace(/\D/g, '');

    // Limita a 11 dígitos
    const truncatedValue = digitsOnly.slice(0, 11);

    // Aplica a formatação XXX.XXX.XXX-XX
    let maskedValue = truncatedValue;
    if (truncatedValue.length > 3) {
        maskedValue = `${truncatedValue.slice(0, 3)}.${truncatedValue.slice(3)}`;
    }
    if (truncatedValue.length > 6) {
        maskedValue = `${maskedValue.slice(0, 7)}.${maskedValue.slice(7)}`;
    }
    if (truncatedValue.length > 9) {
        maskedValue = `${maskedValue.slice(0, 11)}-${maskedValue.slice(11)}`;
    }

    return maskedValue;
};
</script>

<template>
    <Head title="Preencher CPF" />

    <DashboardLayout pageTitle="Configuração de CPF" :breadcrumbs="breadcrumbs">
        <!-- Centraliza o card na tela com um fundo claro -->
        <div class="flex h-full flex-1 flex-col items-center justify-center p-4 bg-gray-50">
            
            <!-- Mensagem de aviso vinda do Middleware -->
            <div v-if="flash && flash.warning" class="mb-4 w-full max-w-md p-4 bg-yellow-100 border border-yellow-400 text-yellow-700 rounded-lg text-center">
                {{ flash.warning }}
            </div>

            <Card class="w-full max-w-md shadow-lg bg-white">
                <CardHeader>
                    <CardTitle class="text-2xl text-gray-900">CPF Obrigatório</CardTitle>
                    <CardDescription class="text-gray-600">
                        Para continuar a usar o sistema, por favor, informe seu CPF. Este dado é essencial para a sua identificação.
                    </CardDescription>
                </CardHeader>
                <form @submit.prevent="submitCpf">
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="cpf" class="text-gray-700">Seu CPF</Label>
                            <Input
                                id="cpf"
                                v-model="form.cpf"
                                placeholder="000.000.000-00"
                                maxlength="14"
                                required
                                autofocus
                                class="text-base bg-gray-100 border-gray-200 text-gray-900 placeholder:text-gray-500"
                            />
                            <!-- Exibe o erro de validação para o campo CPF -->
                            <p v-if="form.errors.cpf" class="text-sm text-red-600">
                                {{ form.errors.cpf }}
                            </p>
                        </div>
                    </CardContent>
                    <CardFooter>
                        <Button type="submit" :disabled="form.processing" class="w-full bg-blue-600 hover:bg-blue-700 text-white">
                            <svg v-if="form.processing" class="animate-spin -ml-1 mr-3 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ form.processing ? 'Salvando...' : 'Salvar e Continuar' }}
                        </Button>
                    </CardFooter>
                </form>
            </Card>

        </div>
    </DashboardLayout>
</template>
