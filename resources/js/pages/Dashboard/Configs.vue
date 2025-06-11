<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import * as icons from 'lucide-vue-next';

// 1. Props recebidas do Controller (agora com os novos campos)
const props = defineProps<{
  forms: Record<string, {
    id: number;
    name: string;
    type: string;
    year: string;
    release: boolean;
    release_data: string | null;
    term: string | null;
    questions: any[];
  }>;
}>();

// --- LÓGICA DE NOTIFICAÇÕES (FLASH MESSAGES) ---
const page = usePage();
const flash = computed(() => page.props.flash as { success?: string; error?: string });

// --- ESTADO DA PÁGINA ---
const selectedYear = ref(String(new Date().getFullYear())); // Ano atual por padrão

// --- ESTADO DO MODAL DE PRAZO ---
const isPrazoModalVisible = ref(false);
const prazoGroup = ref<'avaliacao' | 'pdi' | null>(null);
const prazoDate = ref('');

// --- COMPUTED PROPERTIES PARA GERENCIAR O ESTADO DOS GRUPOS ---
const formsForSelectedYear = computed(() => {
    return Object.values(props.forms).filter(form => form.year === selectedYear.value);
});

// Verifica se o grupo AVALIAÇÃO (autoavaliacao, servidor, chefia) foi liberado
const isAvaliacaoGroupReleased = computed(() => {
    return formsForSelectedYear.value
        .filter(form => ['autoavaliacao', 'servidor', 'chefia'].includes(form.type))
        .some(form => form.release);
});

// Verifica se o grupo PDI (pactuacao, metas) foi liberado
const isPdiGroupReleased = computed(() => {
    return formsForSelectedYear.value
        .filter(form => ['pactuacao', 'metas'].includes(form.type))
        .some(form => form.release);
});

// Pega a data de liberação do grupo AVALIAÇÃO
const avaliacaoReleaseData = computed(() => {
    const releasedForm = formsForSelectedYear.value.find(form => ['autoavaliacao', 'servidor', 'chefia'].includes(form.type) && form.release_data);
    return releasedForm ? new Date(releasedForm.release_data).toLocaleDateString('pt-BR') : 'Data não encontrada';
});

// Pega a data de liberação do grupo PDI
const pdiReleaseData = computed(() => {
    const releasedForm = formsForSelectedYear.value.find(form => ['pactuacao', 'metas'].includes(form.type) && form.release_data);
    return releasedForm ? new Date(releasedForm.release_data).toLocaleDateString('pt-BR') : 'Data não encontrada';
});


// --- MÉTODOS DE AÇÃO ---
const getFormForType = (type: string) => {
  const key = `${selectedYear.value}_${type}`;
  return props.forms[key] || null;
};

function handleCreate(type: string) {
  router.get(route('configs.create', { year: selectedYear.value, type: type }));
}

function handleEdit(formId: number) {
  router.get(route('configs.edit', { formulario: formId }));
}

function handleView(formId: number) {
  router.get(route('configs.show', { formulario: formId }));
}

function handleLiberar(group: 'avaliacao' | 'pdi') {
    if (confirm(`Tem certeza que deseja LIBERAR os formulários de ${group.toUpperCase()} para ${selectedYear.value}? Após liberados, eles não poderão mais ser editados.`)) {
        router.post(route('configs.liberar.store'), {
            year: selectedYear.value,
            group: group
        }, { preserveScroll: true });
    }
}

function openPrazoModal(group: 'avaliacao' | 'pdi') {
    prazoGroup.value = group;
    const formTypes = group === 'avaliacao' ? ['autoavaliacao', 'servidor', 'chefia'] : ['pactuacao', 'metas'];
    const existingForm = formsForSelectedYear.value.find(f => formTypes.includes(f.type) && f.term);
    prazoDate.value = existingForm ? existingForm.term.split(' ')[0] : '';
    isPrazoModalVisible.value = true;
}

function handleSetPrazo() {
    if (!prazoGroup.value || !prazoDate.value) {
        alert('Por favor, selecione uma data.');
        return;
    }
    router.post(route('configs.prazo.store'), {
        year: selectedYear.value,
        group: prazoGroup.value,
        term: prazoDate.value,
    }, {
        onSuccess: () => { isPrazoModalVisible.value = false; },
        preserveScroll: true
    });
}

// Lógica da mensagem de notificação genérica
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
              <button v-if="getFormForType('autoavaliacao')" @click="handleView(getFormForType('autoavaliacao').id)" class="btn-blue"><span>Visualizar</span><component :is="icons.EyeIcon" class="size-5" /></button>
              <button v-if="getFormForType('autoavaliacao')" @click="handleEdit(getFormForType('autoavaliacao').id)" :class="isAvaliacaoGroupReleased ? 'btn-yellow-disabled' : 'btn-yellow'" :disabled="isAvaliacaoGroupReleased"><span>Editar</span><component :is="icons.FilePenLineIcon" class="size-5" /></button>
              <button v-else @click="handleCreate('autoavaliacao')" class="btn-green"><span>Criar</span><component :is="icons.PlusIcon" class="size-5" /></button>
            </div>
          </div>
          <div class="setting-item">
            <label>Formulário de Avaliação do Servidor:</label>
             <div class="button-group">
              <button v-if="getFormForType('servidor')" @click="handleView(getFormForType('servidor').id)" class="btn-blue"><span>Visualizar</span><component :is="icons.EyeIcon" class="size-5" /></button>
              <button v-if="getFormForType('servidor')" @click="handleEdit(getFormForType('servidor').id)" :class="isAvaliacaoGroupReleased ? 'btn-yellow-disabled' : 'btn-yellow'" :disabled="isAvaliacaoGroupReleased"><span>Editar</span><component :is="icons.FilePenLineIcon" class="size-5" /></button>
              <button v-else @click="handleCreate('servidor')" class="btn-green"><span>Criar</span><component :is="icons.PlusIcon" class="size-5" /></button>
            </div>
          </div>
          <div class="setting-item">
            <label>Formulário de Avaliação do Superior:</label>
            <div class="button-group">
              <button v-if="getFormForType('chefia')" @click="handleView(getFormForType('chefia').id)" class="btn-blue"><span>Visualizar</span><component :is="icons.EyeIcon" class="size-5" /></button>
              <button v-if="getFormForType('chefia')" @click="handleEdit(getFormForType('chefia').id)" :class="isAvaliacaoGroupReleased ? 'btn-yellow-disabled' : 'btn-yellow'" :disabled="isAvaliacaoGroupReleased"><span>Editar</span><component :is="icons.FilePenLineIcon" class="size-5" /></button>
              <button v-else @click="handleCreate('chefia')" class="btn-green"><span>Criar</span><component :is="icons.PlusIcon" class="size-5" /></button>
            </div>
          </div>
          <div class="setting-item border-b pb-4 mb-4">
              <label>Liberar Formulario e Prazo da Avaliação:</label>
              <div v-if="!isAvaliacaoGroupReleased" class="button-group">
                  <button class="btn-orange" @click="openPrazoModal('avaliacao')">
                      <span>Prazo</span><component :is="icons.ClockIcon" class="size-5" />
                  </button>
                  <button class="btn-pine" @click="handleLiberar('avaliacao')">
                      <span>Liberar</span><component :is="icons.SendIcon" class="size-5" />
                  </button>
              </div>
              <div v-else class="text-green-600 font-semibold flex items-center gap-2">
                  <component :is="icons.CheckCircle2Icon" class="size-5" />
                  Liberado em: {{ avaliacaoReleaseData }}
              </div>
          </div>
        </div>

        <div class="form-section">
          <h4>PDI - PLANO DE DESENVOLVIMENTO INDIVIDUAL</h4>
           
          <div class="setting-item">
            <label>Formulário de Pactuação (PDI):</label>
            <div class="button-group">
              <button v-if="getFormForType('pactuacao')" @click="handleView(getFormForType('pactuacao').id)" class="btn-blue"><span>Visualizar</span><component :is="icons.EyeIcon" class="size-5" /></button>
              <button v-if="getFormForType('pactuacao')" @click="handleEdit(getFormForType('pactuacao').id)" :class="isPdiGroupReleased ? 'btn-yellow-disabled' : 'btn-yellow'" :disabled="isPdiGroupReleased"><span>Editar</span><component :is="icons.FilePenLineIcon" class="size-5" /></button>
              <button v-else @click="handleCreate('pactuacao')" class="btn-green"><span>Criar</span><component :is="icons.PlusIcon" class="size-5" /></button>
            </div>
          </div>
          <div class="setting-item">
            <label>Formulário de Cumprimento de Metas (PDI):</label>
            <div class="button-group">
              <button v-if="getFormForType('metas')" @click="handleView(getFormForType('metas').id)" class="btn-blue"><span>Visualizar</span><component :is="icons.EyeIcon" class="size-5" /></button>
              <button v-if="getFormForType('metas')" @click="handleEdit(getFormForType('metas').id)" :class="isPdiGroupReleased ? 'btn-yellow-disabled' : 'btn-yellow'" :disabled="isPdiGroupReleased"><span>Editar</span><component :is="icons.FilePenLineIcon" class="size-5" /></button>
              <button v-else @click="handleCreate('metas')" class="btn-green"><span>Criar</span><component :is="icons.PlusIcon" class="size-5" /></button>
            </div>
          </div>
          <div class="setting-item border-b pb-4 mb-4">
              <label>Liberar Formulario e Prazo de PDI:</label>
              <div v-if="!isPdiGroupReleased" class="button-group">
                  <button class="btn-orange" @click="openPrazoModal('pdi')"><span>Prazo</span>
                  <component :is="icons.ClockIcon" class="size-5"></component>
                </button>
                  <button class="btn-pine" @click="handleLiberar('pdi')"><span>Liberar</span>
                  <component :is="icons.SendIcon" class="size-5" />
                </button>
              </div>
              <div v-else class="text-green-600 font-semibold flex items-center gap-2">
                   <component :is="icons.CheckCircle2Icon" class="size-5" />
                   Liberado em: {{ pdiReleaseData }}
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

    <div v-if="isPrazoModalVisible" class="message-box-overlay show">
      <div class="message-box">
        <h3>Definir Prazo para {{ prazoGroup?.toUpperCase() }}</h3>
        <p>Selecione a data limite para o preenchimento dos formulários de {{ prazoGroup }} para o ano de {{ selectedYear }}.</p>
        <div class="my-4">
            <label for="prazo-date" class="block font-medium text-sm text-gray-700 mb-1">Data do Prazo:</label>
            <input type="date" id="prazo-date" v-model="prazoDate" class="form-input rounded-md w-full border-gray-300 shadow-sm text-black">
        </div>
        <div class="flex justify-end gap-4 mt-6">
            <button @click="isPrazoModalVisible = false" class="btn-gray">Cancelar</button>
            <button @click="handleSetPrazo" class="btn-blue">Salvar Prazo</button>
        </div>
      </div>
    </div>

    <div v-if="isMessageBoxVisible" class="message-box-overlay show">
      </div>
  </DashboardLayout>
</template>