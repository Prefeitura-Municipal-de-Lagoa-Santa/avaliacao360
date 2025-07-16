<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import { route } from 'ziggy-js';

// Define as propriedades que o controller irá enviar
const props = defineProps<{
    person: {
        id: number;
        name: string;
        registration_number: string;
        bond_type: string | null;
        functional_status: string | null;
        cpf: string;
        rg_number: string | null;
        admission_date: string | null;
        dismissal_date: string | null;
        current_position: string | null;
        current_function: string | null;
        organizational_unit_id: number | null;
    };
    jobFunctions: Array<{ 
        id: number;
        name: string;
    }>;
    organizationalUnits: Array<{
        id: number;
        name: string;
    }>;
    functionalStatuses: Array<string>;
    errors: Object;
}>();

// Usa o helper 'useForm' do Inertia para gerir o estado do formulário
const form = useForm({
    name: props.person.name,
    registration_number: props.person.registration_number,
    cpf: props.person.cpf,
    bond_type: props.person.bond_type,
    functional_status: props.person.functional_status ? props.person.functional_status.toUpperCase() : null,
    rg_number: props.person.rg_number,
    admission_date: props.person.admission_date,
    dismissal_date: props.person.dismissal_date,
    current_position: props.person.current_position,
    current_function: props.person.current_function,
    organizational_unit_id: props.person.organizational_unit_id,
    job_function_id: props.person.job_function_id,
});

// Função para submeter o formulário para a rota de update
const submit = () => {
    form.put(route('people.update', props.person.id));
};

</script>

<template>
    <Head :title="'Editar ' + form.name" />
    <DashboardLayout :pageTitle="'Editar ' + form.name">

        <div class="max-w-4xl mx-auto bg-white rounded-lg shadow-md p-6 sm:p-8">
            <form @submit.prevent="submit">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">

                    <div class="space-y-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Nome Completo</label>
                            <input type="text" id="name" v-model="form.name"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                :class="{ 'border-red-500': form.errors.name }">
                            <div v-if="form.errors.name" class="text-sm text-red-600 mt-1">{{ form.errors.name }}</div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4">
                            <div>
                                <label for="registration_number" class="block text-sm font-medium text-gray-700 mb-1">Matrícula</label>
                                <input type="text" id="registration_number" v-model="form.registration_number"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    :class="{ 'border-red-500': form.errors.registration_number }">
                                <div v-if="form.errors.registration_number" class="text-sm text-red-600 mt-1">{{ form.errors.registration_number }}</div>
                            </div>
                            <div>
                                <label for="cpf" class="block text-sm font-medium text-gray-700 mb-1">CPF</label>
                                <input type="text" id="cpf" v-model="form.cpf"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    :class="{ 'border-red-500': form.errors.cpf }">
                                <div v-if="form.errors.cpf" class="text-sm text-red-600 mt-1">{{ form.errors.cpf }}</div>
                            </div>
                        </div>

                        <div>
                            <label for="organizational_unit_id" class="block text-sm font-medium text-gray-700 mb-1">Unidade Organizacional</label>
                            <select id="organizational_unit_id" v-model="form.organizational_unit_id"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                :class="{ 'border-red-500': form.errors.organizational_unit_id }">
                                <option :value="null">-- Nenhuma Unidade --</option>
                                <option v-for="unit in props.organizationalUnits" :key="unit.id" :value="unit.id">
                                    {{ unit.name }}
                                </option>
                            </select>
                             <div v-if="form.errors.organizational_unit_id" class="text-sm text-red-600 mt-1">{{ form.errors.organizational_unit_id }}</div>
                        </div>
                        <div>
                            <label for="current_position" class="block text-sm font-medium text-gray-700 mb-1">Cargo Atual</label>
                            <input type="text" id="current_position" v-model="form.current_position"
                                class="block w-full px-3 py-2 border bg-gray-100 border-gray-300 rounded-md shadow-sm focus:outline-none sm:text-sm cursor-not-allowed" readonly>
                            <p class="text-xs text-gray-500 mt-1">Campo gerenciado via importação.</p>
                        </div>
                    </div>

                    <div class="space-y-6">
                        <div>
                            <label for="bond_type" class="block text-sm font-medium text-gray-700 mb-1">Vínculo</label>
                            <input type="text" id="bond_type" v-model="form.bond_type"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4">
                            <div>
                                <label for="admission_date" class="block text-sm font-medium text-gray-700 mb-1">Data de Admissão</label>
                                <input type="date" id="admission_date" v-model="form.admission_date"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="dismissal_date" class="block text-sm font-medium text-gray-700 mb-1">Data de Demissão</label>
                                <input type="date" id="dismissal_date" v-model="form.dismissal_date"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            </div>
                        </div>

                        <div>
                            <label for="functional_status" class="block text-sm font-medium text-gray-700 mb-1">Situação Funcional</label>
                            <select id="functional_status" v-model="form.functional_status"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                :class="{ 'border-red-500': form.errors.functional_status }">
                                <option :value="null">-- Selecione um status --</option>
                                <option v-for="status in props.functionalStatuses" :key="status" :value="status">
                                    {{ status }}
                                </option>
                            </select>
                            <div v-if="form.errors.functional_status" class="text-sm text-red-600 mt-1">{{ form.errors.functional_status }}</div>
                        </div>

                        <div>
                            <label for="current_function" class="block text-sm font-medium text-gray-700 mb-1">Função Atual</label>
                            <select  class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                    id="job_function" 
                    v-model="form.job_function_id" 
                    >
                    <option :value="null">Nenhuma função atribuída</option>
                    
                    <option 
                        v-for="func in props.jobFunctions" 
                        :key="func.id" 
                        :value="func.id"
                    >
                        {{ func.name }}
                    </option>
                </select>
                            <p class="text-xs text-gray-500 mt-1">Campo gerenciado via importação.</p>
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex justify-end gap-4 border-t pt-5">
                    <Link :href="route('people.index')"
                        class="inline-flex justify-center items-center px-4 py-2 border text-sm font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 border-gray-300 bg-white text-gray-700 hover:bg-gray-50 focus:ring-indigo-500">
                    Cancelar
                    </Link>
                    <button type="submit"
                        class="inline-flex justify-center items-center px-4 py-2 border text-sm font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 border-transparent bg-indigo-600 text-white hover:bg-indigo-700 focus:ring-indigo-500"
                        :disabled="form.processing">
                        <span v-if="form.processing">Salvando...</span>
                        <span v-else>Salvar Alterações</span>
                    </button>
                </div>
            </form>
        </div>
    </DashboardLayout>
</template>