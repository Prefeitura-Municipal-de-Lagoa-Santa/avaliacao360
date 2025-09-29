<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import { route } from 'ziggy-js';
import { ArrowLeftIcon, SearchIcon, UserIcon } from 'lucide-vue-next';

interface User {
  id: number;
  name: string;
  email: string;
  cpf: string | null;
  username: string;
}

const props = defineProps<{
  users: User[];
}>();

// Estado para busca
const searchTerm = ref('');

// Estado para controlar qual usuário está sendo editado
const editingUserId = ref<number | null>(null);

// Formulário para atualizar CPF
const cpfForm = useForm({
  cpf: '',
});

// Usuários filtrados pela busca
const filteredUsers = computed(() => {
  if (!searchTerm.value) {
    return props.users;
  }
  
  const term = searchTerm.value.toLowerCase();
  return props.users.filter(user => 
    user.name.toLowerCase().includes(term) ||
    user.email.toLowerCase().includes(term) ||
    (user.cpf && user.cpf.includes(term))
  );
});

// Função para formatar CPF
function formatCpf(cpf: string) {
  if (!cpf) return '';
  const numbers = cpf.replace(/\D/g, '');
  if (numbers.length !== 11) return cpf;
  return numbers.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
}

// Função para aplicar máscara de CPF durante a digitação
function applyCpfMask(event: Event) {
  const input = event.target as HTMLInputElement;
  let value = input.value.replace(/\D/g, '');
  
  if (value.length <= 11) {
    value = value.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
    value = value.replace(/(\d{3})(\d{3})(\d{3})(\d{1})$/, '$1.$2.$3-$4');
    value = value.replace(/(\d{3})(\d{3})(\d{2})$/, '$1.$2.$3');
    value = value.replace(/(\d{3})(\d{2})$/, '$1.$2');
    
    cpfForm.cpf = value;
  }
}

// Função para iniciar a edição de CPF
function startEditing(user: User) {
  editingUserId.value = user.id;
  cpfForm.cpf = user.cpf ? formatCpf(user.cpf) : '';
  cpfForm.clearErrors();
}

// Função para cancelar a edição
function cancelEditing() {
  editingUserId.value = null;
  cpfForm.reset();
  cpfForm.clearErrors();
}

// Função para salvar o CPF
function saveCpf(userId: number) {
  const cleanCpf = cpfForm.cpf.replace(/\D/g, '');
  
  if (cleanCpf.length !== 11) {
    cpfForm.setError('cpf', 'CPF deve ter 11 dígitos');
    return;
  }
  
  cpfForm.transform(data => ({
    cpf: cleanCpf
  })).put(route('admin.users.update-cpf', userId), {
    preserveScroll: true,
    onSuccess: () => {
      editingUserId.value = null;
      cpfForm.reset();
    },
  });
}

function goBack() {
  if (window.history.length > 1) {
    window.history.back();
  } else {
    router.get(route('admin'));
  }
}
</script>

<template>
  <DashboardLayout page-title="Gerenciar CPF de Usuários">
    <Head title="Gerenciar CPF de Usuários" />
    
    <div class="bg-white shadow rounded-lg p-6">
      <!-- Cabeçalho -->
      <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">
          <UserIcon class="inline-block w-6 h-6 mr-2" />
          Gerenciar CPF de Usuários
        </h2>
        <button @click="goBack" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white text-sm font-medium rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors">
          <ArrowLeftIcon class="w-4 h-4 mr-2" />
          Voltar
        </button>
      </div>

      <!-- Barra de busca -->
      <div class="mb-6">
        <div class="relative max-w-md">
          <SearchIcon class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
          <input
            v-model="searchTerm"
            type="text"
            placeholder="Buscar por nome, email ou CPF..."
            class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
          />
        </div>
      </div>

      <!-- Tabela de usuários -->
      <div class="overflow-x-auto">
        <table class="w-full text-sm table-auto">
          <thead>
            <tr class="bg-gray-100 text-left uppercase text-xs text-gray-600">
              <th class="px-4 py-3">Nome</th>
              <th class="px-4 py-3">Email</th>
              <th class="px-4 py-3">Username</th>
              <th class="px-4 py-3">CPF Atual</th>
              <th class="px-4 py-3">Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="user in filteredUsers"
              :key="user.id"
              class="border-b hover:bg-gray-50"
            >
              <td class="px-4 py-3 font-medium">{{ user.name }}</td>
              <td class="px-4 py-3">{{ user.email }}</td>
              <td class="px-4 py-3">{{ user.username }}</td>
              <td class="px-4 py-3">
                <span v-if="user.cpf" class="text-gray-900">{{ formatCpf(user.cpf) }}</span>
                <span v-else class="text-gray-400 italic">Não informado</span>
              </td>
              <td class="px-4 py-3">
                <!-- Modo de visualização -->
                <div v-if="editingUserId !== user.id">
                  <button
                    @click="startEditing(user)"
                    class="inline-flex items-center px-3 py-1 bg-blue-600 text-white text-xs font-medium rounded hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1 transition-colors"
                  >
                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                    </svg>
                    {{ user.cpf ? 'Alterar' : 'Definir' }} CPF
                  </button>
                </div>
                
                <!-- Modo de edição -->
                <div v-else class="flex items-center gap-2">
                  <div class="flex flex-col">
                    <input
                      v-model="cpfForm.cpf"
                      @input="applyCpfMask"
                      type="text"
                      placeholder="000.000.000-00"
                      maxlength="14"
                      class="px-2 py-1 text-xs border border-gray-300 rounded focus:ring-blue-500 focus:border-blue-500"
                      :class="{ 'border-red-300 focus:border-red-500 focus:ring-red-500': cpfForm.errors.cpf }"
                    />
                    <span v-if="cpfForm.errors.cpf" class="text-xs text-red-600 mt-1">
                      {{ cpfForm.errors.cpf }}
                    </span>
                  </div>
                  <button
                    @click="saveCpf(user.id)"
                    :disabled="cpfForm.processing"
                    class="px-2 py-1 bg-green-600 text-white text-xs font-medium rounded hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-1 transition-colors disabled:opacity-50"
                  >
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                  </button>
                  <button
                    @click="cancelEditing"
                    :disabled="cpfForm.processing"
                    class="px-2 py-1 bg-gray-600 text-white text-xs font-medium rounded hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-1 transition-colors disabled:opacity-50"
                  >
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
        
        <!-- Mensagem quando não há usuários -->
        <div v-if="filteredUsers.length === 0" class="text-center py-8">
          <UserIcon class="mx-auto w-12 h-12 text-gray-400 mb-3" />
          <p class="text-gray-500">Nenhum usuário encontrado</p>
        </div>
      </div>
    </div>
  </DashboardLayout>
</template>

<style scoped>
/* Adicionar estilos customizados se necessário */
</style>
