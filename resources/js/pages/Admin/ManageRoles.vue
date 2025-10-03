<script setup lang="ts">
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import { route } from 'ziggy-js';
import { ArrowLeftIcon } from 'lucide-vue-next';

const props = defineProps<{
  users: Array<{
    id: number;
    name: string;
    email: string;
    roles: Array<{ name: string }>;
  }>;
  availableRoles: string[];
}>();

// Estado para seleção de papel por usuário
const selectedRoles = ref<Record<number, string>>({});

// Inicializa os papéis (sem incluir 'servidor' como selecionável)
props.users.forEach((user) => {
  const customRole = user.roles.find(r => r.name.toLowerCase() !== 'servidor');
  selectedRoles.value[user.id] = customRole ? customRole.name : '';
});

function assignRole(userId: number) {
  const role = selectedRoles.value[userId];
  console.log('Enviando:', { userId, role });

  router.post(
    route('users.assign-role', userId),
    { role },
    {
      preserveScroll: true,
      onSuccess: () => {
      },
    }
  );
}

function goBack() {
  if (window.history.length > 1) {
    window.history.back();
  } else {
    router.get(route('recourses.dashboard'));
  }
}
</script>

<template>
  <DashboardLayout page-title="Gerenciar Papéis de Usuário">
    <Head title="Gerenciar Papéis" />
    <div class="overflow-x-auto bg-white shadow rounded p-4">
        <div class="detail-page-header flex justify-between items-center">
        <h2 class="text-2xl font-bold text-gray-800">
          Editar Pápeis
        </h2>
        <button @click="goBack" class="back-btn">
          <ArrowLeftIcon class="size-4 mr-2" />
          Voltar
        </button>
      </div>
      <table class="w-full text-sm table-auto">
        <thead>
          <tr class="bg-gray-100 text-left uppercase text-xs text-gray-600">
            <th class="px-4 py-2">Nome</th>
            <th class="px-4 py-2">Email</th>
            <th class="px-4 py-2">Papéis Atuais</th>
            <th class="px-4 py-2">Gerenciar Papel</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="user in props.users"
            :key="user.id"
            class="border-b hover:bg-gray-50"
          >
            <td class="px-4 py-2">{{ user.name }}</td>
            <td class="px-4 py-2">{{ user.email }}</td>
            <td class="px-4 py-2 capitalize">
              <span v-if="user.roles.length">
                {{ user.roles.map(r => r.name).join(', ') }}
              </span>
              <span v-else>Nenhum</span>
            </td>
            <td class="px-4 py-2 flex items-center gap-2">
              <select v-model="selectedRoles[user.id]" class="border rounded px-2 py-1 text-sm">
                <option value="">Remover papel</option>
                <option value="RH">RH</option>
                <option value="Comissão">Comissão</option>
                <option value="Diretor RH">Diretor RH</option>
                <option value="Secretario Gestão">Secretario Gestão</option>
              </select>
              <button
                class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700"
                @click="assignRole(user.id)"
              >
                Atribuir
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </DashboardLayout>
</template>
