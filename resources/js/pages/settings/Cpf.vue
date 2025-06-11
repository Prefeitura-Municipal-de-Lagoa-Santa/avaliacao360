<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type User } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription, CardFooter } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

// Define as props que o componente espera receber do controller
const props = defineProps<{
    user: User;
    errors: Record<string, string>;
}>();

// Pega a mensagem de aviso (warning) enviada pelo middleware
const flash = computed(() => usePage().props.flash as { warning?: string });

// Inicializa o formulário com o CPF do usuário (se existir)
const form = useForm({
    cpf: props.user.cpf || '',
});

// Define o breadcrumb para a página
const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Configurações',
        href: '#',
    },
     {
        title: 'Meu CPF',
        href: '/settings/cpf', // Ajuste a rota se necessário
    },
];

// Função para submeter o formulário
const submitCpf = () => {
    // A rota 'profile.update' geralmente é usada para atualizar o perfil.
    // Se você tiver uma rota específica para o CPF, altere aqui.
    form.patch('/profile', { // ou a rota que atualiza o usuário
        preserveScroll: true,
        onSuccess: () => {
            // O redirecionamento após o sucesso será tratado pelo backend
            // ou pela visita do Inertia que o middleware irá permitir.
        },
    });
};

</script>

<template>
    <Head title="Preencher CPF" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col items-center justify-center gap-4 rounded-xl p-4">
            
            <Card class="w-full max-w-md">
                <CardHeader>
                    <CardTitle>CPF Obrigatório</CardTitle>
                    <CardDescription>
                        Para continuar a usar o sistema, por favor, informe seu CPF.
                    </CardDescription>
                </CardHeader>
                <form @submit.prevent="submitCpf">
                    <CardContent class="space-y-4">
                        <!-- Mensagem de aviso do Middleware -->
                        <div v-if="flash?.warning" class="mb-4 rounded-md border border-yellow-400 bg-yellow-50 p-4 text-sm text-yellow-700 dark:bg-yellow-900/20 dark:text-yellow-300">
                            {{ flash.warning }}
                        </div>
                        
                        <div class="space-y-2">
                            <Label for="cpf">CPF</Label>
                            <Input
                                id="cpf"
                                v-model="form.cpf"
                                placeholder="000.000.000-00"
                                required
                                autofocus
                            />
                            <!-- Exibe o erro de validação para o campo CPF -->
                            <p v-if="form.errors.cpf" class="text-sm text-red-600 dark:text-red-400">
                                {{ form.errors.cpf }}
                            </p>
                        </div>
                    </CardContent>
                    <CardFooter>
                        <Button type="submit" :disabled="form.processing" class="w-full">
                            {{ form.processing ? 'Salvando...' : 'Salvar e Continuar' }}
                        </Button>
                    </CardFooter>
                </form>
            </Card>

        </div>
    </AppLayout>
</template>
