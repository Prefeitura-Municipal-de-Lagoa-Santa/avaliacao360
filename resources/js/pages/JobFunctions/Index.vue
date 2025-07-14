<script setup lang="ts">
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps<{
    jobFunctions: Array<{
        id: number;
        name: string;
        code: string;
        type: string;
    }>;
    types: Record<string, string>; // { chefe: "Gestor", assessor_1: "...", ... }
}>();

function updateType(id: number, type: string) {
    router.patch(route('funcoes.updateType', id), { type });
}
</script>

<template>
    <Head title="Gerenciar Grupos de Funções" />
    <DashboardLayout pageTitle="Gerenciamento de Grupos de Funções">
        <div class="mb-6">
            <h2 class="text-2xl font-bold">Funções — Gerencie o grupo (type)</h2>
            <p class="text-sm text-gray-500">Selecione o grupo para cada função na tabela abaixo.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full border border-gray-200 rounded-lg shadow-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700">Função</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700">Código</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700">Grupo atual</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-700">Alterar grupo</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="func in props.jobFunctions" :key="func.id" class="border-t hover:bg-gray-50">
                        <td class="px-3 py-2 text-sm">{{ func.name }}</td>
                        <td class="px-3 py-2 text-sm">{{ func.code }}</td>
                        <td class="px-3 py-2 text-sm">
                            <span class="px-2 py-1 rounded bg-gray-100 text-gray-700 text-xs">
                                {{ props.types[func.type] || func.type }}
                            </span>
                        </td>
                        <td class="px-3 py-2">
                            <select
                                class="w-full border rounded px-2 py-1 text-sm"
                                :value="func.type"
                                @change="updateType(func.id, $event.target.value)"
                            >
                                <option v-for="(label, value) in props.types" :key="value" :value="value">{{ label }}</option>
                            </select>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </DashboardLayout>
</template>
