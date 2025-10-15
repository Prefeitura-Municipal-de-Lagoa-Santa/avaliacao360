<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import { route } from 'ziggy-js';
import { ref, computed, watch } from 'vue';

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
    job_function_id: number | null;
    direct_manager_id: number | null;
    sala: string | null;
    descricao_sala: string | null;
  };
  jobFunctions: Array<{ id: number; name: string }>;
  organizationalUnits: Array<{ id: number; name: string }>;
  functionalStatuses: Array<string>;
  managerOptions: Array<{ id: number; name: string; registration_number: string | null }>;
  errors: object;
  subordinates: Array<{ id: number; name: string; registration_number: string | null }>;
}>();

const form = useForm({
  name: props.person.name,
  registration_number: props.person.registration_number,
  cpf: props.person.cpf,
  bond_type: props.person.bond_type,
  functional_status: props.person.functional_status?.toUpperCase() || null,
  rg_number: props.person.rg_number,
  admission_date: props.person.admission_date,
  dismissal_date: props.person.dismissal_date,
  current_position: props.person.current_position,
  organizational_unit_id: props.person.organizational_unit_id,
  job_function_id: props.person.job_function_id,
  direct_manager_id: props.person.direct_manager_id,
  sala: props.person.sala,
  descricao_sala: props.person.descricao_sala,
});

// Armazena o chefe original para detectar mudanças
const originalManagerId = ref(props.person.direct_manager_id);
// Controla se está regenerando avaliações
const regeneratingEvaluations = ref(false);

// Computed para verificar se o chefe foi alterado mas ainda não foi salvo
const managerChangedUnsaved = computed(() => {
  return form.direct_manager_id !== originalManagerId.value;
});

// Função para regerar avaliações automaticamente
const regenerateEvaluationsAuto = (oldManagerId: number | null, newManagerId: number | null) => {
  regeneratingEvaluations.value = true;
  
  const regenerateForm = useForm({
    old_manager_id: oldManagerId,
    new_manager_id: newManagerId,
  });
  
  regenerateForm.post(route('people.regenerate-evaluations', props.person.id), {
    onSuccess: () => {
      regeneratingEvaluations.value = false;
      // Atualiza o chefe original após sucesso
      originalManagerId.value = newManagerId;
    },
    onError: () => {
      regeneratingEvaluations.value = false;
    }
  });
};

const submit = () => {
  const oldManagerId = originalManagerId.value;
  const newManagerId = form.direct_manager_id;
  const managerWasChanged = newManagerId !== oldManagerId;
  
  form.put(route('people.update', props.person.id), {
    onSuccess: () => {
      // Se o chefe foi alterado, regenera automaticamente as avaliações
      if (managerWasChanged) {
        regenerateEvaluationsAuto(oldManagerId, newManagerId);
      }
    }
  });
};

// Auto-seleciona "Chefe(Cedido)" quando Situação Funcional = CEDIDO e nenhuma função estiver definida
watch(() => form.functional_status, (newVal) => {
  const isCedido = (newVal || '').toString().toUpperCase() === 'CEDIDO';
  if (isCedido && (form.job_function_id === null || form.job_function_id === undefined)) {
    const chefeCedido = props.jobFunctions.find(j => j.name === 'Chefe(Cedido)');
    if (chefeCedido) {
      form.job_function_id = chefeCedido.id;
    }
  }
});
</script>

<template>
  <Head :title="`Editar ${form.name}`" />
  <DashboardLayout :pageTitle="`Editar ${form.name}`">
    <div class="max-w-4xl mx-auto bg-white rounded-lg shadow-md p-6 sm:p-8">
      <form @submit.prevent="submit">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
          <!-- Coluna Esquerda -->
          <div class="space-y-6">
            <!-- Nome -->
            <div>
              <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Nome Completo</label>
              <input id="name" type="text" v-model="form.name"
                class="block w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                :class="{ 'border-red-500': form.errors.name }" />
              <div v-if="form.errors.name" class="text-sm text-red-600 mt-1">{{ form.errors.name }}</div>
            </div>

            <!-- Matrícula e CPF -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4">
              <div>
                <label for="registration_number" class="block text-sm font-medium text-gray-700 mb-1">Matrícula</label>
                <input id="registration_number" type="text" v-model="form.registration_number"
                  class="block w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                  :class="{ 'border-red-500': form.errors.registration_number }" />
                <div v-if="form.errors.registration_number" class="text-sm text-red-600 mt-1">{{ form.errors.registration_number }}</div>
              </div>
              <div>
                <label for="cpf" class="block text-sm font-medium text-gray-700 mb-1">CPF</label>
                <input id="cpf" type="text" v-model="form.cpf"
                  class="block w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                  :class="{ 'border-red-500': form.errors.cpf }" />
                <div v-if="form.errors.cpf" class="text-sm text-red-600 mt-1">{{ form.errors.cpf }}</div>
              </div>
            </div>

            <!-- Unidade Organizacional -->
            <div>
              <label for="organizational_unit_id" class="block text-sm font-medium text-gray-700 mb-1">Unidade Organizacional</label>
              <select id="organizational_unit_id" v-model="form.organizational_unit_id"
                class="block w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                :class="{ 'border-red-500': form.errors.organizational_unit_id }">
                <option :value="null">-- Nenhuma Unidade --</option>
                <option v-for="unit in organizationalUnits" :key="unit.id" :value="unit.id">{{ unit.name }}</option>
              </select>
              <div v-if="form.errors.organizational_unit_id" class="text-sm text-red-600 mt-1">{{ form.errors.organizational_unit_id }}</div>
            </div>

            <!-- Informações da Sala -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4">
              <div>
                <label for="sala" class="block text-sm font-medium text-gray-700 mb-1">Número/Código da Sala</label>
                <input id="sala" type="text" v-model="form.sala"
                  class="block w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                  :class="{ 'border-red-500': form.errors.sala }" 
                  placeholder="Ex: 101, A-203, S01..." />
                <div v-if="form.errors.sala" class="text-sm text-red-600 mt-1">{{ form.errors.sala }}</div>
              </div>
              <div>
                <label for="descricao_sala" class="block text-sm font-medium text-gray-700 mb-1">Descrição/Nome da Sala</label>
                <input id="descricao_sala" type="text" v-model="form.descricao_sala"
                  class="block w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                  :class="{ 'border-red-500': form.errors.descricao_sala }" 
                  placeholder="Ex: Recepção, Diretoria, Almoxarifado..." />
                <div v-if="form.errors.descricao_sala" class="text-sm text-red-600 mt-1">{{ form.errors.descricao_sala }}</div>
              </div>
            </div>

            <!-- Cargo Atual -->
            <div>
              <label for="current_position" class="block text-sm font-medium text-gray-700 mb-1">Cargo Atual</label>
              <input id="current_position" type="text" v-model="form.current_position"
                class="block w-full px-3 py-2 border rounded-md bg-gray-100 shadow-sm text-gray-700 cursor-not-allowed sm:text-sm"
                readonly />
              <p class="text-xs text-gray-500 mt-1">Campo gerenciado via importação.</p>
            </div>
          </div>

          <!-- Coluna Direita -->
          <div class="space-y-6">
            <!-- Vínculo -->
            <div>
              <label for="bond_type" class="block text-sm font-medium text-gray-700 mb-1">Vínculo</label>
              <input id="bond_type" type="text" v-model="form.bond_type"
                class="block w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" />
            </div>

            <!-- Admissão / Demissão -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4">
              <div>
                <label for="admission_date" class="block text-sm font-medium text-gray-700 mb-1">Data de Admissão</label>
                <input id="admission_date" type="date" v-model="form.admission_date"
                  class="block w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" />
              </div>
              <div>
                <label for="dismissal_date" class="block text-sm font-medium text-gray-700 mb-1">Data de Demissão</label>
                <input id="dismissal_date" type="date" v-model="form.dismissal_date"
                  class="block w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" />
              </div>
            </div>

            <!-- Situação Funcional -->
            <div>
              <label for="functional_status" class="block text-sm font-medium text-gray-700 mb-1">Situação Funcional</label>
              <select id="functional_status" v-model="form.functional_status"
                class="block w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                :class="{ 'border-red-500': form.errors.functional_status }">
                <option :value="null">-- Selecione um status --</option>
                <option v-for="status in functionalStatuses" :key="status" :value="status">{{ status }}</option>
              </select>
              <div v-if="form.errors.functional_status" class="text-sm text-red-600 mt-1">{{ form.errors.functional_status }}</div>
            </div>

            <!-- Função Atual -->
            <div>
              <label for="job_function_id" class="block text-sm font-medium text-gray-700 mb-1">Função Atual</label>
              <select id="job_function_id" v-model="form.job_function_id"
                class="block w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                <option :value="null">Nenhuma função atribuída</option>
                <option v-for="func in jobFunctions" :key="func.id" :value="func.id">{{ func.name }}</option>
              </select>
              <p class="text-xs text-gray-500 mt-1">Campo gerenciado via importação.</p>
            </div>

            <!-- Chefe Imediato -->
            <div>
              <label for="direct_manager_id" class="block text-sm font-medium text-gray-700 mb-1">Chefe Imediato</label>
              <select id="direct_manager_id" v-model="form.direct_manager_id"
                class="block w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                :class="{ 'border-red-500': form.errors.direct_manager_id }">
                <option :value="null">-- Nenhum Chefe --</option>
                <option v-for="manager in managerOptions" :key="manager.id" :value="manager.id">
                  {{ manager.registration_number ? `${manager.registration_number} - ${manager.name}` : manager.name }}
                </option>
              </select>
              <div v-if="form.errors.direct_manager_id" class="text-sm text-red-600 mt-1">{{ form.errors.direct_manager_id }}</div>
              
              <!-- Aviso quando o chefe foi alterado -->
              <div v-if="managerChangedUnsaved" class="mt-3 p-3 bg-blue-50 border border-blue-200 rounded-md">
                <div class="flex">
                  <svg class="w-5 h-5 text-blue-400 mr-2" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                  </svg>
                  <div>
                    <p class="text-sm font-medium text-blue-800">Chefe alterado!</p>
                    <p class="text-sm text-blue-700">As avaliações serão regeneradas automaticamente após salvar.</p>
                  </div>
                </div>
              </div>

              <!-- Status de regeneração das avaliações -->
              <div v-if="regeneratingEvaluations" class="mt-3 p-3 bg-green-50 border border-green-200 rounded-md">
                <div class="flex">
                  <svg class="w-5 h-5 text-green-400 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  <div>
                    <p class="text-sm font-medium text-green-800">Regenerando avaliações...</p>
                    <p class="text-sm text-green-700">Transferindo avaliações para o novo chefe.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Ações -->
        <div class="mt-8 flex justify-end gap-4 border-t pt-5">
          <Link :href="route('people.evaluations', person.id)"
            class="inline-flex justify-center items-center px-4 py-2 border text-sm font-medium rounded-md shadow-sm bg-blue-600 border-blue-600 text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            Ver Avaliações
          </Link>
          <Link :href="route('people.index')"
            class="inline-flex justify-center items-center px-4 py-2 border text-sm font-medium rounded-md shadow-sm bg-white border-gray-300 text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
            Cancelar
          </Link>
          <button type="submit"
            class="inline-flex justify-center items-center px-4 py-2 border text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
            :disabled="form.processing">
            <span v-if="form.processing">Salvando...</span>
            <span v-else>Salvar Alterações</span>
          </button>
        </div>
      </form>
      <div class="mt-12">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Subordinados</h2>

        <div v-if="subordinates.length > 0" class="bg-white border rounded shadow-sm divide-y">
            <div
            v-for="person in subordinates"
            :key="person.id"
            class="px-4 py-3 flex justify-between items-center hover:bg-gray-50"
            >
            <div>
                <p class="text-sm font-medium text-gray-900">{{ person.name }}</p>
                <p class="text-xs text-gray-500">Matrícula: {{ person.registration_number ?? '---' }}</p>
            </div>
            <Link :href="route('people.edit', person.id)" class="text-indigo-600 text-sm hover:underline">Editar</Link>
            </div>
        </div>

        <div v-else class="text-gray-500 text-sm">
            Nenhum subordinado encontrado.
        </div>
    </div>
    </div>
  </DashboardLayout>
</template>
