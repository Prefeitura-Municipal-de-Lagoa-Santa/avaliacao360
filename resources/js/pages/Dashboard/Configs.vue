<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import * as icons from 'lucide-vue-next';
import FormCreator from '@/pages/Dashboard/Forms.vue';

// --- LÓGICA DAS NOTIFICAÇÕES (FLASH MESSAGES) ---
const page = usePage();
const flash = computed(() => page.props.flash as { success?: string; error?: string });


// --- ESTADO DA PÁGINA ---
const activeFormType = ref<string | null>(null);
const selectedYear = ref('2025');
const isSaving = ref(false); // Estado de carregamento

const formTitles: Record<string, string> = {
  autoavaliacao: 'Criar Formulário de Autoavaliação',
  chefia: 'Criar Formulário de Avaliação Chefia',
  pactuacao: 'Criar Formulário de Pactuação (PDI)',
  metas: 'Criar Formulário de Cumprimento de Metas (PDI)',
};

const formCreatorTitle = computed(() => {
  return activeFormType.value ? formTitles[activeFormType.value] : '';
});


// --- MÉTODOS DE MANIPULAÇÃO DE EVENTOS ---
function handleCancel() {
  activeFormType.value = null;
}

function handleSave(questions: Array<{ text: string; weight: number | null }>) {
  if (!activeFormType.value) return;

  const endpoint = '/formularios';
  const formTitle = formTitles[activeFormType.value];

  router.post(endpoint, {
      title: formTitle,
      year: selectedYear.value,
      type: activeFormType.value,
      questions: questions,
    }, {
      onStart: () => { isSaving.value = true; },
      onFinish: () => { isSaving.value = false; },
      onError: (errors) => {
        console.error('Erros:', errors);
        const firstError = Object.values(errors)[0];
        showMessage(firstError, 'Erro de Validação');
      },
    });
}

// --- LÓGICA DA CAIXA DE MENSAGEM (para ações locais) ---
const isMessageBoxVisible = ref(false);
const messageBoxTitle = ref('');
const messageBoxContent = ref('');

function showMessage(message: string, title: string = "Notificação") {
  messageBoxTitle.value = title;
  messageBoxContent.value = message;
  isMessageBoxVisible.value = true;
}

function hideMessage() {
  isMessageBoxVisible.value = false;
}
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

    <div v-if="!activeFormType" class="space-y-8 max-w-4xl mx-auto">
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
                <button class="btn-blue"><span>Visualizar</span><component :is="icons.EyeIcon" class="size-5" /></button>
                <button class="btn-yellow"><span>Editar</span><component :is="icons.FilePenLineIcon" class="size-5" /></button>
                <button class="btn-green" @click="activeFormType = 'autoavaliacao'"><span>Criar</span><component :is="icons.PlusIcon" class="size-5" /></button>
            </div>
          </div>
          <div class="setting-item">
            <label>Formulário de Avaliação Chefia:</label>
            <div class="button-group">
                <button class="btn-blue"><span>Visualizar</span><component :is="icons.EyeIcon" class="size-5" /></button>
                <button class="btn-yellow"><span>Editar</span><component :is="icons.FilePenLineIcon" class="size-5" /></button>
                <button class="btn-green" @click="activeFormType = 'chefia'"><span>Criar</span><component :is="icons.PlusIcon" class="size-5" /></button>
            </div>
          </div>
        </div>

        <div class="form-section">
          <h4>PDI - PLANO DE DESENVOLVIMENTO INDIVIDUAL</h4>
          <div class="setting-item">
            <label>Formulário de Pactuação (PDI):</label>
            <div class="button-group">
                <button class="btn-blue"><span>Visualizar</span><component :is="icons.EyeIcon" class="size-5" /></button>
                <button class="btn-yellow"><span>Editar</span><component :is="icons.FilePenLineIcon" class="size-5" /></button>
                <button class="btn-green" @click="activeFormType = 'pactuacao'"><span>Criar</span><component :is="icons.PlusIcon" class="size-5" /></button>
            </div>
          </div>
          <div class="setting-item">
            <label>Formulário de Cumprimento de Metas (PDI):</label>
            <div class="button-group">
                <button class="btn-blue"><span>Visualizar</span><component :is="icons.EyeIcon" class="size-5" /></button>
                <button class="btn-yellow"><span>Editar</span><component :is="icons.FilePenLineIcon" class="size-5" /></button>
                <button class="btn-green" @click="activeFormType = 'metas'"><span>Criar</span><component :is="icons.PlusIcon" class="size-5" /></button>
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

    <div v-else>
      <FormCreator
        :title="formCreatorTitle"
        :is-saving="isSaving"
        @cancel="handleCancel"
        @save="handleSave"
      />
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

