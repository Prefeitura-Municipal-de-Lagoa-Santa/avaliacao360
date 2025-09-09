<script setup lang="ts">
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps<{
  roles: Array<{
    id: number | string,
    name: string,
    permissions: Array<{ id: number | string, name: string }>,
  }>,
  permissions: Array<{ id: number | string, name: string }>,
}>();

function isChecked(role: any, permission: any) {
  return role.permissions.some((p: any) => p.id == permission.id); // == para lidar com int/uuid/string
}

function togglePermission(role: any, permission: any, checked: boolean) {
  let permissionIds = role.permissions.map((p: any) => p.id);

  if (checked) {
    if (!permissionIds.includes(permission.id)) permissionIds.push(permission.id);
  } else {
    permissionIds = permissionIds.filter((id: any) => id != permission.id);
  }

  // FILTRA valores nulos, undefined e string vazia
  permissionIds = permissionIds.filter((id: any) => !!id);

  router.put(`/admin/roles/${role.id}/permissions`, { permissions: permissionIds }, { preserveScroll: true });
}

</script>

<template>

  <Head title="Admin" />
  <DashboardLayout pageTitle="Admin - Permissões">
    <!-- Botões de administração -->
    <div class="mb-6 flex justify-between items-center">
      <h1 class="text-2xl font-bold text-gray-900">Gerenciar Permissões</h1>
      <div class="flex gap-3">
        <button
          @click="router.visit('/admin/manage-user-cpf')"
          class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors"
        >
          <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
          </svg>
          Trocar CPF de Usuário
        </button>
        <button
          @click="router.visit('/activity-logs')"
          class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
        >
          <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
          </svg>
          Ver Logs de Atividade
        </button>
      </div>
    </div>
    
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
      <div v-for="role in props.roles" :key="role.id"
        class="bg-white rounded-2xl shadow-xl p-6 flex flex-col gap-4 border hover:border-purple-400 transition"
        style="min-width:0;">
        <div class="flex items-center justify-between mb-2 min-w-0">
          <h2 class="text-lg font-bold text-purple-700 truncate max-w-full min-w-0" :title="role.name">
            {{ role.name }}
          </h2>
        </div>
        <div class="flex flex-col gap-2">
          <div v-for="permission in props.permissions" :key="permission.id" class="flex items-center gap-2 min-w-0">
            <input type="checkbox" class="form-checkbox h-5 w-5 accent-purple-700 transition shrink-0"
              :checked="isChecked(role, permission)" @change="togglePermission(role, permission, ($event.target as HTMLInputElement).checked)"
              :id="`role-${role.id}-perm-${permission.id}`"
              :aria-label="`Permissão ${permission.name} para ${role.name}`" />
            <label :for="`role-${role.id}-perm-${permission.id}`"
              class="select-none text-gray-700 cursor-pointer break-all max-w-full min-w-0" :title="permission.name">
              {{ permission.name }}
            </label>
          </div>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>
