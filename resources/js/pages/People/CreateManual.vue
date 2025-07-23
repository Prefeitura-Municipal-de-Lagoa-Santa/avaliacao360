<script setup lang="ts">
import { watch } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import { ArrowLeftIcon } from 'lucide-vue-next';

// 1. Estado do formulário
const form = useForm({
  name: '',
  email: '',
  cpf: '',
});

// 2. Aplica máscara conforme o usuário digita
watch(() => form.cpf, (newValue) => {
  form.cpf = applyCpfMask(newValue);
});

const applyCpfMask = (value: string): string => {
  const digits = value.replace(/\D/g, '').slice(0, 11);
  if (digits.length <= 3) return digits;
  if (digits.length <= 6) return `${digits.slice(0, 3)}.${digits.slice(3)}`;
  if (digits.length <= 9) return `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6)}`;
  return `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6, 9)}-${digits.slice(9)}`;
};

// 3. Envia o formulário
const submitManual = () => {
  form.transform(data => ({
    ...data,
    cpf: data.cpf.replace(/\D/g, '') // limpa a máscara do CPF
  })).post(route('people.manual.store'), {
    onSuccess: () => form.reset(),
    onError: (errors) => {
      console.error('Erros:', errors);
    },
  });
};

// 4. Voltar
const goBack = () => router.get(route('people.index'));
</script>

<template>
  <Head title="Criar Pessoa Manual" />
  <DashboardLayout pageTitle="Criar Pessoa Manual">
    <div class="max-w-xl mx-auto bg-white p-6 rounded-lg shadow space-y-6">
      <!-- Formulário -->
      <div class="space-y-4">
        <!-- Nome -->
        <div>
          <label for="name" class="block text-sm font-medium text-gray-700">Nome</label>
          <input
            id="name"
            v-model="form.name"
            type="text"
            required
            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 bg-blue-50 text-gray-900 focus:outline-none focus:ring focus:ring-indigo-200"
          />
          <p v-if="form.errors.name" class="text-sm text-red-500 mt-1">{{ form.errors.name }}</p>
        </div>

        <!-- E-mail -->
        <div>
          <label for="email" class="block text-sm font-medium text-gray-700">E-mail</label>
          <input
            id="email"
            v-model="form.email"
            type="email"
            required
            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 bg-blue-50 text-gray-900 focus:outline-none focus:ring focus:ring-indigo-200"
          />
          <p v-if="form.errors.email" class="text-sm text-red-500 mt-1">{{ form.errors.email }}</p>
        </div>

        <!-- CPF -->
        <div>
          <label for="cpf" class="block text-sm font-medium text-gray-700">CPF</label>
          <input
            id="cpf"
            v-model="form.cpf"
            type="text"
            maxlength="14"
            required
            placeholder="000.000.000-00"
            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 bg-blue-50 text-gray-900 focus:outline-none focus:ring focus:ring-indigo-200"
          />
          <p v-if="form.errors.cpf" class="text-sm text-red-500 mt-1">{{ form.errors.cpf }}</p>
        </div>

        <!-- Alerta da senha padrão -->
        <div class="p-3 rounded-md bg-yellow-100 text-yellow-800 text-sm">
          A senha padrão para o primeiro acesso será <strong>Abc@1234</strong>. O sistema solicitará que a senha seja alterada no primeiro login.
        </div>

        <!-- Botões -->
        <div class="flex justify-end gap-2 pt-4">
          <button
            type="button"
            @click="goBack"
            class="inline-flex items-center px-4 py-2 text-sm font-medium text-indigo-600 border border-indigo-300 rounded-md hover:bg-indigo-50 transition"
          >
            <ArrowLeftIcon class="size-4 mr-1" />
            Voltar
          </button>

          <button
            type="button"
            @click="submitManual"
            class="inline-flex items-center px-6 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-md hover:bg-indigo-700 transition"
            :disabled="form.processing"
          >
            Criar
          </button>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>
