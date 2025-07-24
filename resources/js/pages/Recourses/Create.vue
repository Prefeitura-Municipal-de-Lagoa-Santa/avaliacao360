<script setup lang="ts">
import { useForm, Head } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import { ref } from 'vue';
import { route } from 'ziggy-js';

const props = defineProps<{
  evaluation: {
    id: number;
    year: string;
    person: string;
  };
}>();

const fileInput = ref<HTMLInputElement | null>(null);

const form = useForm({
  text: '',
  attachments: [] as File[],
});

function submit() {
  const data = new FormData();
  data.append('text', form.text);
  form.attachments.forEach((file, i) => {
    data.append(`attachments[${i}]`, file);
  });

  form.post(route('recourses.store', props.evaluation.id), {
    onSuccess: () => {
      form.reset();
      alert('Recurso enviado com sucesso!');
    },
  });
}

function triggerFileInput() {
  fileInput.value?.click();
}

function handleFiles(event: Event) {
  const files = (event.target as HTMLInputElement).files;
  if (files) {
    form.attachments.push(...Array.from(files));
  }
}

function removeFile(index: number) {
  form.attachments.splice(index, 1);
}
</script>

<template>
  <Head title="Abrir Recurso" />
  <DashboardLayout page-title="Abrir Recurso">
    <div class="space-y-6 max-w-2xl mx-auto bg-white shadow rounded p-6">
      <h2 class="text-lg font-semibold">Recurso para Avaliação de {{ evaluation.year }}</h2>
      <p class="text-sm text-gray-600">Pessoa avaliada: {{ evaluation.person }}</p>

      <form @submit.prevent="submit" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Texto do Recurso</label>
          <textarea v-model="form.text" class="w-full border rounded p-2" rows="4" required />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">Anexos</label>
          <div class="flex gap-2 items-center mt-2">
            <button type="button" @click="triggerFileInput" class="px-4 py-1 bg-gray-200 text-sm rounded hover:bg-gray-300">
              Selecionar Arquivos
            </button>
            <input ref="fileInput" type="file" multiple class="hidden" @change="handleFiles" />
          </div>
          <ul class="mt-2 space-y-1">
            <li v-for="(file, index) in form.attachments" :key="index" class="flex justify-between items-center bg-gray-50 border px-3 py-1 rounded">
              <span class="text-sm truncate">{{ file.name }}</span>
              <button type="button" @click="removeFile(index)" class="text-xs text-red-600 hover:underline">Remover</button>
            </li>
          </ul>
        </div>

        <div>
          <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
            Enviar Recurso
          </button>
        </div>
      </form>
    </div>
  </DashboardLayout>
</template>
