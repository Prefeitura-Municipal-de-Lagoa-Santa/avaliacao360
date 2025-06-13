<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import * as icons from 'lucide-vue-next';
import { debounce } from 'lodash';
import { route } from 'ziggy-js';

// Define as propriedades que o controller irá enviar
const props = defineProps<{
  people: {
    data: Array<{
      id: number;
      name: string;
      registration_number: string;
      cpf: string;
      bond_type: string;
    }>;
    links: Array<{
      url: string | null;
      label: string;
      active: boolean;
    }>;
  };
  filters: {
    search: string | null;
  };
}>();

// Estado reativo para a caixa de pesquisa
const search = ref(props.filters.search);

// Função para pesquisar com debounce (espera o utilizador parar de digitar)
watch(search, debounce((value: string | null) => {
  router.get(route('persons.index'), { search: value }, {
    preserveState: true,
    replace: true,
  });
}, 300));

</script>

<template>
  <Head title="Gestão de Pessoas" />
  <DashboardLayout pageTitle="Gestão de Pessoas">

    <!-- Cabeçalho e Barra de Pesquisa -->
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4">
      <div class="relative w-full sm:w-72">
        <input 
          v-model="search"
          type="text" 
          placeholder="Pesquisar por nome, matrícula ou CPF..."
          class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
        />
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
          <icons.SearchIcon class="size-5 text-gray-400" />
        </div>
      </div>
      <Link :href="route('configs')" class="btn btn-secondary w-full sm:w-auto">
        <icons.ArrowLeftIcon class="size-5 mr-2" />
        Voltar para Configurações
      </Link>
    </div>

    <!-- Tabela de Pessoas -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm text-left text-gray-600">
          <thead class="text-xs text-gray-700 uppercase bg-gray-50">
            <tr>
              <th scope="col" class="px-6 py-3">Nome</th>
              <th scope="col" class="px-6 py-3">Matrícula</th>
              <th scope="col" class="px-6 py-3">CPF</th>
              <th scope="col" class="px-6 py-3">Vínculo</th>
              <th scope="col" class="px-6 py-3">
                <span class="sr-only">Ações</span>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="people.data.length === 0">
              <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                Nenhuma pessoa encontrada.
              </td>
            </tr>
            <tr v-for="person in people.data" :key="person.id" class="bg-white border-b hover:bg-gray-50">
              <th scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap">
                {{ person.name }}
              </th>
              <td class="px-6 py-4">
                {{ person.registration_number }}
              </td>
              <td class="px-6 py-4">
                {{ person.cpf }}
              </td>
              <td class="px-6 py-4">
                {{ person.bond_type }}
              </td>
              <td class="px-6 py-4 text-right">
                <Link :href="route('persons.edit', person.id)" class="font-medium text-indigo-600 hover:underline">Editar</Link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
    
    <!-- Paginação -->
    <div v-if="people.links.length > 3" class="mt-6 flex justify-center">
        <div class="flex flex-wrap -mb-1">
            <template v-for="(link, key) in people.links" :key="key">
                <div v-if="link.url === null" class="mr-1 mb-1 px-4 py-3 text-sm leading-4 text-gray-400 border rounded" v-html="link.label" />
                <Link v-else class="mr-1 mb-1 px-4 py-3 text-sm leading-4 border rounded hover:bg-white focus:border-indigo-500 focus:text-indigo-500" :class="{ 'bg-indigo-500 text-white': link.active }" :href="link.url" v-html="link.label" />
            </template>
        </div>
    </div>

  </DashboardLayout>
</template>
