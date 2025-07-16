<script setup lang="ts">
import { ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import * as icons from 'lucide-vue-next'
import { debounce } from 'lodash'
import { route } from 'ziggy-js'

const props = defineProps<{
    pendingRequests: {
        data: Array<{
            id: number,
            type: string,
            form_name: string,
            avaliado: string,
            avaliador: string,
            created_at: string,
        }>,
        links: Array<any>
    },
    filters: {
        search: string | null
    }
}>()

const search = ref(props.filters.search ?? '')

watch(search, debounce((value: string | null) => {
    router.get(route('evaluations.pending'), { search: value }, {
        preserveState: true,
        replace: true,
    })
}, 300))
</script>

<template>
  <Head title="Avaliações Pendentes" />
  <DashboardLayout pageTitle="Avaliações Pendentes (Todos)">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4">
      <div class="relative w-full sm:w-72">
        <input v-model="search" type="text" placeholder="Pesquisar avaliado ou avaliador..."
          class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
          <icons.SearchIcon class="size-5 text-gray-400" />
        </div>
      </div>
    </div>

    <div v-if="props.pendingRequests.data.length === 0" class="text-gray-500">
      Nenhuma avaliação pendente encontrada.
    </div>
    <div v-else class="overflow-x-auto">
      <table class="min-w-full bg-white shadow rounded-lg">
        <thead>
          <tr>
            <th class="px-4 py-2 text-left">Avaliado</th>
            <th class="px-4 py-2 text-left">Avaliador</th>
            <th class="px-4 py-2 text-left">Tipo</th>
            <th class="px-4 py-2 text-left">Formulário</th>
            <th class="px-4 py-2 text-left">Data</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="req in props.pendingRequests.data" :key="req.id" class="border-t">
            <td class="px-4 py-2">{{ req.avaliado }}</td>
            <td class="px-4 py-2">{{ req.avaliador }}</td>
            <td class="px-4 py-2 capitalize">{{ req.type }}</td>
            <td class="px-4 py-2">{{ req.form_name }}</td>
            <td class="px-4 py-2">{{ req.created_at }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Paginação -->
    <div v-if="props.pendingRequests.links && props.pendingRequests.links.length > 3" class="mt-6 flex justify-center">
      <div class="flex flex-wrap -mb-1">
        <template v-for="(link, key) in props.pendingRequests.links" :key="key">
          <div v-if="link.url === null" class="mr-1 mb-1 px-4 py-3 text-sm leading-4 text-gray-400 border rounded"
            v-html="link.label" />
          <Link v-else
            class="mr-1 mb-1 px-4 py-3 text-sm leading-4 border rounded hover:bg-white focus:border-indigo-500 focus:text-indigo-500"
            :class="{ 'bg-indigo-500 text-white': link.active }" :href="link.url" v-html="link.label" />
        </template>
      </div>
    </div>
  </DashboardLayout>
</template>
