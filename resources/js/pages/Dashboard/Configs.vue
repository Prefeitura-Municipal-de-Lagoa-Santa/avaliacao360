<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import * as icons from 'lucide-vue-next';

// DEFINIÇÃO DAS PROPS
// VERSÃO CORRIGIDA

const props = defineProps<{
  forms: Record<string, {
    id: number;
    name: string;
    type: string;
    year: string;
    release: boolean;
    release_data: string | null;
    term_first: string | null;
    term_end: string | null;
  }>;
  existingYears: Array<string | number>;
}>();

// ESTADO DA PÁGINA
const page = usePage();
const flash = computed(() => page.props.flash as { success?: string; error?: string });
const selectedYear = ref(String(new Date().getFullYear()));

// ESTADO DO MODAL DE PRAZO
const isPrazoModalVisible = ref(false);
const prazoGroup = ref<'avaliacao' | 'pdi' | null>(null);
const prazoDateInicio = ref('');
const prazoDateFim = ref('');

// PROPRIEDADES COMPUTADAS
const yearOptions = computed(() => {
  const currentYear = new Date().getFullYear();
  const nextYear = currentYear + 1;
  const yearsSet = new Set([currentYear, nextYear, ...(props.existingYears || []).map(year => Number(year))]);
  return Array.from(yearsSet).sort((a, b) => b - a);
});

const formsForSelectedYear = computed(() => {
  return Object.values(props.forms).filter(form => String(form.year) === selectedYear.value);
});

const isAvaliacaoGroupReleased = computed(() => formsForSelectedYear.value.filter(form => ['autoavaliacao', 'servidor', 'chefia'].includes(form.type)).some(form => form.release));
const isPdiGroupReleased = computed(() => formsForSelectedYear.value.filter(form => ['pactuacao'].includes(form.type)).some(form => form.release));

const avaliacaoReleaseData = computed(() => {
  const f = formsForSelectedYear.value.find(form => ['autoavaliacao', 'servidor', 'chefia'].includes(form.type) && form.release_data);
  return f ? new Date(f.release_data!).toLocaleDateString('pt-BR') : 'Data não encontrada';
});

const pdiReleaseData = computed(() => {
  const f = formsForSelectedYear.value.find(form => ['pactuacao'].includes(form.type) && form.release_data);
  return f ? new Date(f.release_data!).toLocaleDateString('pt-BR') : 'Data não encontrada';
});

// MÉTODOS DE AÇÃO
const getFormForType = (type: string) => props.forms[`${selectedYear.value}_${type}`] || null;

/**
 * Esta é a função que carrega os dados no modal.
 */
function openPrazoModal(group: 'avaliacao' | 'pdi') {
  prazoGroup.value = group;
  const formTypes = group === 'avaliacao' ? ['autoavaliacao', 'servidor', 'chefia'] : ['pactuacao'];

  // 1. Procura, dentro dos formulários do ano selecionado...
  const existingForm = formsForSelectedYear.value.find(
    // ...o primeiro formulário que seja do grupo correto E que tenha as datas de prazo preenchidas.
    f => formTypes.includes(f.type) && f.term_first && f.term_end
  );

  // 2. Se um formulário com prazo for encontrado...
  if (existingForm) {
    // A correção é pegar apenas os 10 primeiros caracteres (AAAA-MM-DD) da string.
    prazoDateInicio.value = existingForm.term_first?.substring(0, 10) ?? '';
    prazoDateFim.value = existingForm.term_end?.substring(0, 10) ?? '';
  } else {
    // 3. Se nenhum formulário com prazo for encontrado, os campos ficam em branco.
    prazoDateInicio.value = '';
    prazoDateFim.value = '';
  }
  isPrazoModalVisible.value = true;
}

function handleSetPrazo() {
  if (!prazoGroup.value || !prazoDateInicio.value || !prazoDateFim.value) {
    alert('Por favor, preencha as datas de início e encerramento.');
    return;
  }
  router.post(route('configs.prazo.store'), {
    year: selectedYear.value,
    group: prazoGroup.value,
    term_first: prazoDateInicio.value,
    term_end: prazoDateFim.value,
  }, {
    onSuccess: () => { isPrazoModalVisible.value = false; },
    preserveScroll: true,
  });
}

function handleLiberar(group: 'avaliacao' | 'pdi') {
  if (confirm(`Tem certeza que deseja LIBERAR os formulários de ${group.toUpperCase()} para ${selectedYear.value}?`)) {
    router.post(route('configs.liberar.store'), { year: selectedYear.value, group: group }, { preserveScroll: true });
  }
}

function handleCreate(type: string) { router.get(route('configs.create', { year: selectedYear.value, type: type })); }
function handleEdit(formId: number) { router.get(route('configs.edit', { formulario: formId })); }
function handleView(formId: number) { router.get(route('configs.show', { formulario: formId })); }
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
          <select id="form-year" v-model="selectedYear" class="form-select rounded-md border-gray-300 text-black">
            <option v-for="year in yearOptions" :key="year" :value="year">
              {{ year }}
            </option>
          </select>
        </div>

        <div class="form-section">
          <h4>AVALIAÇÃO</h4>

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
                <button v-if="getFormForType('autoavaliacao') && !isAvaliacaoGroupReleased"
                  @click="handleEdit(getFormForType('autoavaliacao').id)" class="btn-yellow">
                  <span>Editar</span>
                  <component :is="icons.FilePenLineIcon" class="size-5" />
                </button>
                <button v-else-if="!getFormForType('autoavaliacao')" @click="handleCreate('autoavaliacao')"
                  class="btn-green">
                  <span>Criar</span>
                  <component :is="icons.PlusIcon" class="size-5" />
                </button>
              </div>
            </div>

            <div class="setting-item">
              <label>Formulário de Avaliação do Servidor:</label>
              <div class="button-group">
                <button v-if="getFormForType('servidor')" @click="handleView(getFormForType('servidor').id)"
                  class="btn-blue">
                  <span>Visualizar</span>
                  <component :is="icons.EyeIcon" class="size-5" />
                </button>
                <button v-if="getFormForType('servidor') && !isAvaliacaoGroupReleased"
                  @click="handleEdit(getFormForType('servidor').id)" class="btn-yellow">
                  <span>Editar</span>
                  <component :is="icons.FilePenLineIcon" class="size-5" />
                </button>
                <button v-else-if="!getFormForType('servidor')" @click="handleCreate('servidor')" class="btn-green">
                  <span>Criar</span>
                  <component :is="icons.PlusIcon" class="size-5" />
                </button>
              </div>
            </div>

            <div class="setting-item">
              <label>Formulário de Avaliação do Superior:</label>
              <div class="button-group">
                <button v-if="getFormForType('chefia')" @click="handleView(getFormForType('chefia').id)"
                  class="btn-blue">
                  <span>Visualizar</span>
                  <component :is="icons.EyeIcon" class="size-5" />
                </button>
                <button v-if="getFormForType('chefia') && !isAvaliacaoGroupReleased"
                  @click="handleEdit(getFormForType('chefia').id)" class="btn-yellow">
                  <span>Editar</span>
                  <component :is="icons.FilePenLineIcon" class="size-5" />
                </button>
                <button v-else-if="!getFormForType('chefia')" @click="handleCreate('chefia')" class="btn-green">
                  <span>Criar</span>
                  <component :is="icons.PlusIcon" class="size-5" />
                </button>
              </div>
            </div>

          </div>
          <div class="setting-item border-b pb-4 mb-4">
            <label>Liberar Formulario e Prazo da Avaliação:</label>
            <div v-if="!isAvaliacaoGroupReleased" class="button-group">
              <button class="btn-orange" @click="openPrazoModal('avaliacao')">
                <span>Prazo</span>
                <component :is="icons.ClockIcon" class="size-5" />
              </button>
              <button class="btn-pine" @click="handleLiberar('avaliacao')">
                <span>Liberar</span>
                <component :is="icons.SendIcon" class="size-5" />
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
            <label>Formulário de Pactuação PDI:</label>
            <div class="button-group">
              <button v-if="getFormForType('pactuacao')" @click="handleView(getFormForType('pactuacao').id)"
                class="btn-blue">
                <span>Visualizar</span>
                <component :is="icons.EyeIcon" class="size-5" />
              </button>
              <button v-if="getFormForType('pactuacao') && !isAvaliacaoGroupReleased"
                @click="handleEdit(getFormForType('pactuacao').id)" class="btn-yellow">
                <span>Editar</span>
                <component :is="icons.FilePenLineIcon" class="size-5" />
              </button>
              <button v-else-if="!getFormForType('pactuacao')" @click="handleCreate('pactuacao')" class="btn-green">
                <span>Criar</span>
                <component :is="icons.PlusIcon" class="size-5" />
              </button>
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

    <div v-if="isPrazoModalVisible" class="message-box-overlay show text-indigo-700">
      <div class="message-box">
        <h3>Definir Prazo para {{ prazoGroup?.toUpperCase() }}</h3>
        <p>Selecione a data limite para o preenchimento dos formulários de {{ prazoGroup }} para o ano de {{
          selectedYear }}.</p>

        <div class="my-4">
          <label for="prazo-date-inicio" class="block font-medium text-sm text-gray-700 mb-1">Data de início:</label>
          <input type="date" id="prazo-date-inicio" v-model="prazoDateInicio"
            class="form-input rounded-md w-full border-gray-300 shadow-sm text-black">
        </div>

        <div class="my-4">
          <label for="prazo-date-fim" class="block font-medium text-sm text-gray-700 mb-1">Data de encerramento:</label>
          <input type="date" id="prazo-date-fim" v-model="prazoDateFim"
            class="form-input rounded-md w-full border-gray-300 shadow-sm text-black">
        </div>

        <div class="flex justify-end gap-4 mt-6">
          <button @click="isPrazoModalVisible = false" class="btn-gray">Cancelar</button>
          <button @click="handleSetPrazo" class="btn-blue">Salvar Prazo</button>
        </div>
      </div>
    </div>
    <!--
    <div v-if="isMessageBoxVisible" class="message-box-overlay show">
    </div> -->
  </DashboardLayout>
</template>