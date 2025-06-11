// resources/js/Pages/Dashboard/Configs.vue

<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import * as icons from 'lucide-vue-next';

// 1. Props recebidas do DashboardController
const props = defineProps<{
  forms: Record<string, { id: number; name: string; type: string; year: string; questions: any[] }>;
}>();

const page = usePage();
const flash = computed(() => page.props.flash as { success?: string; error?: string });

// 2. Estado da página
const selectedYear = ref(String(new Date().getFullYear())); // Ano atual por padrão

// 3. Lógica para encontrar formulários existentes
const getFormForType = (type: string) => {
  const key = `${selectedYear.value}_${type}`;
  return props.forms[key] || null;
};

// 4. Métodos de navegação
function handleCreate(type: string) {
  router.get(route('configs.create', { year: selectedYear.value, type: type }));
}

function handleEdit(formId: number) {
  router.get(route('configs.edit', { formulario: formId }));
}

function handleView(formId: number) {
  // A rota 'show' pode levar para uma página de visualização apenas
  router.get(route('configs.show', { formulario: formId }));
}

// Lógica da mensagem de notificação (pode ser mantida)
const isMessageBoxVisible = ref(false);
const messageBoxTitle = ref('');
const messageBoxContent = ref('');
function showMessage(message: string, title: string = "Notificação") {
  messageBoxTitle.value = title;
  messageBoxContent.value = message;
  isMessageBoxVisible.value = true;
}
function hideMessage() { isMessageBoxVisible.value = false; }
</script>

<template>

  <Head title="Configurações" />
  <DashboardLayout pageTitle="Configurações">

    <div v-if="flash && flash.success" class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
      {{ flash.success }}
    </div>
    <div v-if="flash && flash.error" class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
      {{ flash.error }}
    </div>

    <div class="space-y-8 max-w-4xl mx-auto">
      <div class="settings-section">
        <h3>Formulários</h3>
        <div class="setting-item">
          <label for="form-year">Ano:</label>
          <select id="form-year" v-model="selectedYear" class="form-select rounded-md border-gray-300">
            <option value="2025">2025</option>
            <option value="2024">2024</option>
            <option value="2023">2023</option>
          </select>
        </div>

        <div class="form-section">
          <h4>AVALIAÇÃO</h4>

          <div class="setting-item">
            <label>Formulário de Autoavaliação:</label>
            <div class="button-group">

              <button v-if="getFormForType('autoavaliacao')" @click="handleView(getFormForType('autoavaliacao').id)"
                class="btn-blue">
                <span>Visualizar</span>
                <component :is="icons.EyeIcon" class="size-5" />
              </button>

              <button v-if="getFormForType('autoavaliacao')" @click="handleEdit(getFormForType('autoavaliacao').id)"
                class="btn-yellow">
                <span>Editar</span>
                <component :is="icons.FilePenLineIcon" class="size-5" />
              </button>

              <button v-else @click="handleCreate('autoavaliacao')" class="btn-green">
                <span>Criar</span>
                <component :is="icons.PlusIcon" class="size-5" />
              </button>

            </div>
          </div>

          <div class="setting-item">
            <label>Formulário de Avaliação do Servidor:</label>
            <div class="button-group">

              <button v-if="getFormForType('servidor')" @click="handleView(getFormForType('servidor').id)" class="btn-blue">
                <span>Visualizar</span>
                <component :is="icons.EyeIcon" class="size-5" />
              </button>

              <button v-if="getFormForType('servidor')" @click="handleEdit(getFormForType('servidor').id)"
                class="btn-yellow">
                <span>Editar</span>
                <component :is="icons.FilePenLineIcon" class="size-5" />
              </button>

              <button v-else @click="handleCreate('servidor')" class="btn-green">
                <span>Criar</span>
                <component :is="icons.PlusIcon" class="size-5" />
              </button>

            </div>
          </div>
        </div>
        <div class="setting-item">
            <label>Formulário de Avaliação do Superior:</label>
            <div class="button-group">

              <button v-if="getFormForType('chefia')" @click="handleView(getFormForType('chefia').id)" class="btn-blue">
                <span>Visualizar</span>
                <component :is="icons.EyeIcon" class="size-5" />
              </button>

              <button v-if="getFormForType('chefia')" @click="handleEdit(getFormForType('chefia').id)"
                class="btn-yellow">
                <span>Editar</span>
                <component :is="icons.FilePenLineIcon" class="size-5" />
              </button>

              <button v-else @click="handleCreate('chefia')" class="btn-green">
                <span>Criar</span>
                <component :is="icons.PlusIcon" class="size-5" />
              </button>

            </div>
          </div>

        <div class="form-section">
          <h4>PDI - PLANO DE DESENVOLVIMENTO INDIVIDUAL</h4>
          <div class="setting-item">
            <label>Formulário de Pactuação (PDI):</label>
            <div class="button-group">

              <button v-if="getFormForType('pactuacao')" @click="handleView(getFormForType('pactuacao').id)" class="btn-blue">
                <span>Visualizar</span>
                <component :is="icons.EyeIcon" class="size-5" />
              </button>

              <button v-if="getFormForType('pactuacao')" @click="handleEdit(getFormForType('pactuacao').id)"
                class="btn-yellow">
                <span>Editar</span>
                <component :is="icons.FilePenLineIcon" class="size-5" />
              </button>

              <button v-else @click="handleCreate('pactuacao')" class="btn-green">
                <span>Criar</span>
                <component :is="icons.PlusIcon" class="size-5" />
              </button>

            </div>
          </div>
          <div class="setting-item">
            <label>Formulário de Cumprimento de Metas (PDI):</label>
            <div class="button-group">

              <button v-if="getFormForType('metas')" @click="handleView(getFormForType('metas').id)" class="btn-blue">
                <span>Visualizar</span>
                <component :is="icons.EyeIcon" class="size-5" />
              </button>

              <button v-if="getFormForType('metas')" @click="handleEdit(getFormForType('metas').id)"
                class="btn-yellow">
                <span>Editar</span>
                <component :is="icons.FilePenLineIcon" class="size-5" />
              </button>

              <button v-else @click="handleCreate('metas')" class="btn-green">
                <span>Criar</span>
                <component :is="icons.PlusIcon" class="size-5" />
              </button>

            </div>
          </div>
          
        </div>

        <div class="form-section">
          <div class="setting-item">
            <label>Liberar formulários:</label>
            <div class="button-group">
              <button class="btn-orange" @click="showMessage('Função para definir prazo em desenvolvimento.')">
                <span>Prazo</span>
                <component :is="icons.ClockIcon" class="size-5" />
              </button>
              <button class="btn-pine" @click="showMessage('Função para liberar formulário em desenvolvimento.')">
                <span>Liberar</span>
                <component :is="icons.SendIcon" class="size-5" />
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="settings-section">
        <h3>Usuários</h3>
        <div class="setting-item">
          <label>Upload de Planilha de Usuários:</label>
          <button class="btn-blue">
            <span>Upload</span>
            <component :is="icons.UploadCloudIcon" class="size-5" />
          </button>
        </div>
        <div class="setting-item">
          <label>Editar Usuários:</label>
          <button class="btn-yellow">
            <span>Editar</span>
            <component :is="icons.UsersIcon" class="size-5" />
          </button>
        </div>
      </div>
    </div>

    <div v-if="isMessageBoxVisible" class="message-box-overlay show">
      <div class="message-box">
        <h3>{{ messageBoxTitle }}</h3>
        <p>{{ messageBoxContent }}</p>
        <button @click="hideMessage">Fechar</button>
      </div>
    </div>
  </DashboardLayout>
</template>
