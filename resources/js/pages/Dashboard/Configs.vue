<script setup lang="ts">
import { ref, computed } from 'vue';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import { Head } from '@inertiajs/vue3';
import * as icons from 'lucide-vue-next';

// --- Estado Reativo para a Caixa de Mensagem (Notificações Gerais) ---
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


// --- Estado para a interface de Upload de Usuários ---
const uploadStep = ref('idle'); // 'idle', 'loading', 'preview', 'success', 'error'
const uploadSummary = ref(null);
const detailedChanges = ref([]);
const uploadErrors = ref([]);
const tempFilePath = ref('');
const fileInput = ref<HTMLInputElement | null>(null);
const isDetailsVisible = ref(false);
const originalFileName = ref('');


// --- Funções para o Upload ---

// 1. Aciona o clique no input de arquivo
function triggerFileInput() {
  resetUploadState();
  fileInput.value?.click();
}

// 2. Lida com o arquivo selecionado e envia para o preview no backend
async function handleFileChange(event: Event) {
  const target = event.target as HTMLInputElement;
  const file = target.files?.[0];

  if (!file) return;

  originalFileName.value = file.name;
  uploadStep.value = 'loading';

  const formData = new FormData();
  formData.append('file', file);

  try {
    const response = await fetch('/users/upload/preview', {
      method: 'POST',
      body: formData,
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
    });

    const result = await response.json();

    if (!response.ok) {
        throw new Error(result.message || 'Erro ao processar o arquivo.');
    }

    uploadSummary.value = result.summary;
    detailedChanges.value = result.detailed_changes;
    uploadErrors.value = result.errors;
    tempFilePath.value = result.temp_file_path;
    uploadStep.value = 'preview';

  } catch (error) {
    console.error('Erro no preview:', error);
    uploadErrors.value = [error.message || 'Não foi possível conectar ao servidor.'];
    uploadStep.value = 'error';
  } finally {
    if (target) target.value = '';
  }
}

// 3. Envia a confirmação para o backend
async function confirmUpload() {
  uploadStep.value = 'loading';

  try {
    const response = await fetch('/users/upload/confirm', {
        method: 'POST',
        body: JSON.stringify({ temp_file_path: tempFilePath.value }),
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
    });

    const result = await response.json();

    if (!response.ok) {
        throw new Error(result.message || 'Erro ao confirmar a operação.');
    }
    
    uploadStep.value = 'success';

  } catch (error) {
    console.error('Erro na confirmação:', error);
    uploadErrors.value = [error.message || 'Não foi possível conectar ao servidor.'];
    uploadStep.value = 'error';
  }
}

// 4. Reseta o estado para o início
function resetUploadState() {
  uploadStep.value = 'idle';
  uploadSummary.value = null;
  detailedChanges.value = [];
  uploadErrors.value = [];
  tempFilePath.value = '';
  originalFileName.value = '';
  isDetailsVisible.value = false;
}

// Propriedades computadas para fácil acesso no template
const hasChanges = computed(() => {
    if (!uploadSummary.value) return false;
    return uploadSummary.value.new > 0 || uploadSummary.value.updated > 0;
});
</script>

<template>
  <Head title="Configurações" />
  <DashboardLayout pageTitle="Configurações">
    <div class="space-y-8 max-w-4xl mx-auto">

      <!-- Seção de Formulários -->
      <div class="settings-section">
        <h3>Formulários</h3>
        <div class="setting-item">
          <label for="form-year" class="setting-label">Ano:</label>
          <div class="setting-control">
            <select id="form-year" @change="showMessage('Ano dos formulários alterado para: ' + ($event.target as HTMLSelectElement).value)">
                <option value="2025" selected>2025</option>
                <option value="2024">2024</option>
                <option value="2023">2023</option>
            </select>
          </div>
        </div>
        <div class="form-section">
          <h4>AVALIAÇÃO</h4>
          <div class="setting-item">
            <label class="setting-label">Formulário de Autoavaliação:</label>
            <div class="button-group">
                <button class="btn btn-blue" @click="showMessage('Visualizando formulário...')"><span>Visualizar</span><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg></button>
                <button class="btn btn-yellow" @click="showMessage('Editando formulário...')"><span>Editar</span><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg></button>
                <button class="btn btn-green" @click="showMessage('Criando formulário...')"><span>Criar</span><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg></button>
            </div>
          </div>
           <div class="setting-item">
            <label class="setting-label">Formulário de Avaliação Chefia:</label>
            <div class="button-group">
                <button class="btn btn-blue" @click="showMessage('Visualizando formulário...')"><span>Visualizar</span><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg></button>
                <button class="btn btn-yellow" @click="showMessage('Editando formulário...')"><span>Editar</span><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg></button>
                <button class="btn btn-green" @click="showMessage('Criando formulário...')"><span>Criar</span><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg></button>
            </div>
          </div>
           <div class="setting-item">
            <label class="setting-label">Liberar formulário:</label>
            <div class="button-group">
                <button class="btn btn-orange" @click="showMessage('Definindo prazo...')"><span>Prazo</span><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg></button>
                <button class="btn btn-pine" @click="showMessage('Liberando formulário...')"><span>Liberar</span><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4"><path stroke-linecap="round" stroke-linejoin="round" d="M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.094 1.976 1.057 1.976 2.192V16.5A2.25 2.25 0 0 1 18 18.75h-2.25m-7.5-10.5H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375m-8.25-3 1.5 1.5 3-3.75" /></svg></button>
            </div>
          </div>
        </div>
        <div class="form-section">
            <h4>PDI - PLANO DE DESENVOLVIMENTO INDIVIDUAL</h4>
            <div class="setting-item">
                <label class="setting-label">Formulário de Pactuação (PDI):</label>
                <div class="button-group">
                    <button class="btn btn-blue" @click="showMessage('Visualizando formulário...')"><span>Visualizar</span><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg></button>
                    <button class="btn btn-yellow" @click="showMessage('Editando formulário...')"><span>Editar</span><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg></button>
                    <button class="btn btn-green" @click="showMessage('Criando formulário...')"><span>Criar</span><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg></button>
                </div>
            </div>
            <div class="setting-item">
                <label class="setting-label">Formulário de Cumprimento de Metas (PDI):</label>
                <div class="button-group">
                    <button class="btn btn-blue" @click="showMessage('Visualizando formulário...')"><span>Visualizar</span><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg></button>
                    <button class="btn btn-yellow" @click="showMessage('Editando formulário...')"><span>Editar</span><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg></button>
                    <button class="btn btn-green" @click="showMessage('Criando formulário...')"><span>Criar</span><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg></button>
                </div>
            </div>
            <div class="setting-item">
                <label class="setting-label">Liberar formulário:</label>
                <div class="button-group">
                    <button class="btn btn-orange" @click="showMessage('Definindo prazo...')"><span>Prazo</span><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg></button>
                    <button class="btn btn-pine" @click="showMessage('Liberando formulário...')"><span>Liberar</span><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4"><path stroke-linecap="round" stroke-linejoin="round" d="M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.094 1.976 1.057 1.976 2.192V16.5A2.25 2.25 0 0 1 18 18.75h-2.25m-7.5-10.5H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375m-8.25-3 1.5 1.5 3-3.75" /></svg></button>
                </div>
            </div>
        </div>
      </div>
      
      <!-- Seção de Gestão de Usuários (com a nova lógica) -->
      <div class="settings-section">
        <h3>Gestão de Usuários</h3>
        <div class="divide-y divide-gray-100">
            <div v-if="uploadStep === 'idle'" class="setting-item">
                <label class="setting-label">Upload de Planilha de Usuários:</label>
                <div class="setting-control">
                    <button class="btn btn-blue" @click="triggerFileInput">
                        <span>Upload</span>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" /></svg>
                    </button>
                    <input type="file" ref="fileInput" @change="handleFileChange" class="hidden" accept=".csv,.txt">
                </div>
            </div>
             <div v-if="uploadStep === 'idle'" class="setting-item">
                <label class="setting-label">Editar Usuários Manualmente:</label>
                <div class="setting-control">
                    <button class="btn btn-yellow" @click="showMessage('Redirecionando para edição manual...')">
                        <span>Editar</span>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" /></svg>
                    </button>
                </div>
            </div>
            <div v-if="uploadStep === 'loading'" class="loading-state">
                <svg class="animate-spin h-8 w-8 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                <p>Processando arquivo, por favor aguarde...</p>
            </div>
            <div v-if="uploadStep === 'preview' && uploadSummary" class="preview-section">
                <h4>Resumo do Upload: {{ originalFileName }}</h4>
                <p class="preview-description">Verifique as alterações propostas antes de confirmar. Nenhuma alteração foi salva ainda.</p>
                <div class="summary-grid">
                    <div class="summary-item new"><strong>{{ uploadSummary.new }}</strong><span>Novos Usuários</span></div>
                    <div class="summary-item updated"><strong>{{ uploadSummary.updated }}</strong><span>Usuários a Atualizar</span></div>
                    <div class="summary-item unchanged"><strong>{{ uploadSummary.unchanged }}</strong><span>Inalterados</span></div>
                    <div class="summary-item errors"><strong>{{ uploadSummary.errors }}</strong><span>Linhas com Erros</span></div>
                </div>
                <div v-if="uploadErrors.length > 0" class="error-details">
                    <p><strong>Erros encontrados no arquivo:</strong></p>
                    <ul><li v-for="(error, index) in uploadErrors" :key="index">{{ error }}</li></ul>
                </div>
                <div v-if="detailedChanges.length > 0" class="details-toggle">
                    <button @click="isDetailsVisible = !isDetailsVisible">{{ isDetailsVisible ? 'Esconder Detalhes' : 'Ver Detalhes das Alterações' }}</button>
                </div>
                <div v-if="isDetailsVisible" class="detailed-changes-container text-gray-800">
                    <div v-for="item in detailedChanges" :key="item.registration_number" class="change-item">
                        <strong :class="item.status">{{ item.status === 'new' ? 'NOVO' : 'ATUALIZADO' }}</strong>
                        <span>: {{ item.name }} (Matrícula: {{ item.registration_number }})</span>
                        <ul v-if="item.status === 'updated'" class="field-changes">
                            <li v-for="(change, field) in item.changes" :key="field"><strong>{{ field }}:</strong> de '{{ change.from }}' para '{{ change.to }}'</li>
                        </ul>
                    </div>
                </div>
                <div class="preview-actions">
                    <button class="btn-action-gray" @click="resetUploadState">Cancelar</button>
                    <button class="btn-action-green" @click="confirmUpload" :disabled="!hasChanges">Confirmar Alterações</button>
                </div>
            </div>
             <div v-if="uploadStep === 'success' || uploadStep === 'error'" class="final-state">
                <div v-if="uploadStep === 'success'" class="success-message">
                    <h4>Operação concluída com sucesso!</h4>
                    <p>Os usuários foram importados para o sistema.</p>
                </div>
                 <div v-if="uploadStep === 'error'" class="error-message">
                    <h4>Ocorreu um erro</h4>
                     <p v-for="(error, index) in uploadErrors" :key="index" class="text-gray-800">{{ error }}</p>
                </div>
                 <button class="btn btn-blue" @click="resetUploadState">Novo Upload</button>
             </div>
        </div>
      </div>
    </div>

    <!-- Caixa de Mensagem Geral -->
    <div v-if="isMessageBoxVisible" class="message-box-overlay show" @click="hideMessage">
      <div class="message-box" @click.stop>
        <h3>{{ messageBoxTitle }}</h3>
        <p>{{ messageBoxContent }}</p>
        <button @click="hideMessage">Fechar</button>
      </div>
    </div>
  </DashboardLayout>
</template>

<style scoped>
/* Estilos gerais da seção */
.settings-section {
    background-color: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
}
.settings-section h3 {
    font-size: 1.5rem;
    font-weight: 600;
    color: #1a2342;
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 0.75rem;
    margin-bottom: 1rem;
}
.form-section {
    margin-top: 1.5rem;
    border-left: 3px solid #4EC0E6;
    padding-left: 1.5rem;
}
.form-section h4 {
    font-size: 1.1rem;
    font-weight: 600;
    color: #2E3A6C;
    margin-bottom: 1rem;
}


/* Layout do item */
.setting-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 0;
    border-bottom: 1px solid #f3f4f6;
}
.setting-item:last-child {
    border-bottom: none;
}
.setting-label { font-weight: 500; color: #333; }
.setting-control { display: flex; justify-content: flex-end; gap: 0.5rem; }

select {
    padding: 0.4rem 0.8rem;
    border-radius: 8px;
    border: 1px solid #d1d5db;
}

/* Botão principal */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.4rem 0.8rem;
    border-radius: 8px;
    font-weight: 500;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.2s ease-in-out;
    background-color: #fff;
    border: 1px solid;
}
.btn:hover { color: white; }
.btn-blue { color: #3b82f6; border-color: #dbeafe; }
.btn-blue:hover { background-color: #3b82f6; border-color: #3b82f6; }
.btn-yellow { color: #f59e0b; border-color: #fef3c7; }
.btn-yellow:hover { background-color: #f59e0b; border-color: #f59e0b; }
.btn-green { color: #22c55e; border-color: #dcfce7; }
.btn-green:hover { background-color: #22c55e; border-color: #22c55e; }
.btn-orange { color: #f97316; border-color: #ffedd5; }
.btn-orange:hover { background-color: #f97316; border-color: #f97316; }
.btn-pine { color: #14b8a6; border-color: #ccfbf1; }
.btn-pine:hover { background-color: #14b8a6; border-color: #14b8a6; }

.btn:hover svg { stroke: white; }
.btn svg { stroke: currentColor; }

.button-group {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

/* Estilos para os novos estados de upload */
.loading-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem 1rem;
    gap: 1rem;
    color: #6b7280;
}
.preview-section {
    padding: 1.5rem;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background-color: #f8fafc;
    margin-top: 1rem;
}
.preview-section h4 {
    font-size: 1.25rem;
    font-weight: 600;
    color: #1a2342;
    margin-bottom: 0.5rem;
}
.preview-description {
    font-size: 0.9rem;
    color: #475569;
    margin-bottom: 1.5rem;
}
.summary-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}
@media (min-width: 768px) {
    .summary-grid { grid-template-columns: repeat(4, 1fr); }
}
.summary-item {
    background-color: white;
    padding: 1rem;
    border-radius: 8px;
    text-align: center;
    border-left: 5px solid;
}
.summary-item strong { display: block; font-size: 2rem; font-weight: 700; }
.summary-item span { font-size: 0.875rem; color: #475569; }
.summary-item.new { border-color: #22c55e; }
.summary-item.new strong { color: #22c55e; }
.summary-item.updated { border-color: #f59e0b; }
.summary-item.updated strong { color: #f59e0b; }
.summary-item.unchanged { border-color: #6b7280; }
.summary-item.unchanged strong { color: #6b7280; }
.summary-item.errors { border-color: #ef4444; }
.summary-item.errors strong { color: #ef4444; }
.error-details {
    background-color: #fee2e2;
    color: #991b1b;
    padding: 1rem;
    border-radius: 8px;
    margin-top: 1rem;
    font-size: 0.875rem;
}
.error-details ul { list-style-position: inside; }
.details-toggle { margin: 1.5rem 0 1rem; }
.details-toggle button { background: none; border: none; color: #3b82f6; font-weight: 500; cursor: pointer; }
.detailed-changes-container {
    max-height: 250px;
    overflow-y: auto;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 1rem;
    background-color: white;
    font-size: 0.875rem;
}
.change-item { padding: 0.5rem 0; border-bottom: 1px solid #f3f4f6; }
.change-item:last-child { border-bottom: none; }
.change-item > strong { font-weight: 700; padding: 0.1rem 0.4rem; border-radius: 4px; color: white; font-size: 0.75rem; }
.change-item > strong.new { background-color: #22c55e; }
.change-item > strong.updated { background-color: #f59e0b; }
.field-changes { list-style-type: none; padding-left: 1rem; margin-top: 0.5rem; font-size: 0.8rem; color: #4b5563; }
.preview-actions { display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1.5rem; }
.preview-actions button { border: none; padding: 0.6rem 1.2rem; border-radius: 8px; font-weight: 500; cursor: pointer; }
.btn-action-gray { background-color: #d1d5db; color: #1f2937; }
.btn-action-gray:hover { background-color: #9ca3af; }
.btn-action-green { background-color: #22c55e; color: white; }
.btn-action-green:hover { filter: brightness(1.1); }
.btn-action-green:disabled { background-color: #9ca3af; cursor: not-allowed; }
.final-state { text-align: center; padding: 2rem; }
.success-message h4, .error-message h4 { font-size: 1.25rem; font-weight: 600; }
.success-message h4 { color: #166534; }
.error-message h4 { color: #991b1b; }
.final-state p { margin: 0.5rem 0 1.5rem; }

/* Caixa de Mensagem Geral */
.message-box-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.5);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000;
  visibility: hidden;
  opacity: 0;
  transition: opacity 0.3s ease-in-out;
}
.message-box-overlay.show { visibility: visible; opacity: 1; }
.message-box {
  background-color: white;
  padding: 30px;
  border-radius: 15px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
  text-align: center;
  max-width: 400px;
  width: 90%;
}
.message-box h3 { font-size: 1.5rem; font-weight: 600; color: #333; margin-bottom: 15px; }
.message-box p { font-size: 1rem; color: #555; margin-bottom: 25px; }
.message-box button {
  background-color: #2E3A6C;
  color: white;
  padding: 10px 25px;
  border-radius: 10px;
  font-weight: 500;
  cursor: pointer;
  transition: background-color 0.2s ease-in-out;
}
.message-box button:hover { background-color: #1a2342; }
</style>
