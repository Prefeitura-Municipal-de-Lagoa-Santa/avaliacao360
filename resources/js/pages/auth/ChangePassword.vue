<script setup lang="ts">
import { useForm, Head, Link } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import { ArrowLeftIcon } from 'lucide-vue-next';

const form = useForm({
  password: '',
  password_confirmation: '',
});

function submit() {
  form.post(route('password.update'));
}
</script>

<template>
  <Head title="Trocar Senha" />

  <DashboardLayout pageTitle="Trocar Senha">

    <!-- Formulário -->
    <div class="max-w-lg mx-auto bg-white p-6 rounded-lg shadow space-y-6">
      <h2 class="text-xl font-semibold text-gray-800 text-center">Defina sua nova senha</h2>

      <form @submit.prevent="submit" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Nova senha</label>
          <input
            v-model="form.password"
            type="password"
            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring focus:ring-indigo-200 focus:border-indigo-500"
          />
          <p v-if="form.errors.password" class="text-sm text-red-600 mt-1">{{ form.errors.password }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">Confirme a senha</label>
          <input
            v-model="form.password_confirmation"
            type="password"
            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring focus:ring-indigo-200 focus:border-indigo-500"
          />
        </div>

        <button
          type="submit"
          :disabled="form.processing"
          class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-4 rounded-md transition"
        >
          <span v-if="form.processing">Salvando...</span>
          <span v-else>Salvar Senha</span>
        </button>
      </form>
    </div>
  </DashboardLayout>
</template>
