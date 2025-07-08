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

function isChecked(role, permission) {
  return role.permissions.some(p => p.id == permission.id); // == para lidar com int/uuid/string
}

function togglePermission(role, permission, checked) {
  let permissionIds = role.permissions.map(p => p.id);

  if (checked) {
    if (!permissionIds.includes(permission.id)) permissionIds.push(permission.id);
  } else {
    permissionIds = permissionIds.filter(id => id != permission.id);
  }

  // FILTRA valores nulos, undefined e string vazia
  permissionIds = permissionIds.filter(id => !!id);

  router.put(`/admin/roles/${role.id}/permissions`, { permissions: permissionIds }, { preserveScroll: true });
}

</script>

<template>

  <Head title="Admin" />
  <DashboardLayout pageTitle="Admin - Permissões">
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
              :checked="isChecked(role, permission)" @change="togglePermission(role, permission, $event.target.checked)"
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
