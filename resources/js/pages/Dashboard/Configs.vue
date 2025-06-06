<script setup lang="ts">
import { ref } from 'vue';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import { Head } from '@inertiajs/vue3';

// --- Estado Reativo para a Caixa de Mensagem ---
const isMessageBoxVisible = ref(false);
const messageBoxTitle = ref('');
const messageBoxContent = ref('');

// Função para mostrar a caixa de mensagem (versão Vue)
function showMessage(message: string, title: string = "Notificação") {
  messageBoxTitle.value = title;
  messageBoxContent.value = message;
  isMessageBoxVisible.value = true;
}

// Função para esconder a caixa de mensagem (versão Vue)
function hideMessage() {
  isMessageBoxVisible.value = false;
}
</script>

<template>

  <Head title="Configurações" />
  <DashboardLayout pageTitle="Configurações">
    <div class="space-y-8 max-w-4xl mx-auto">
      <div class="settings-section">
        <h3>Formulários</h3>
        <div class="setting-item">
          <label for="form-year">Ano:</label>
          <select id="form-year"
            @change="showMessage('Ano dos formulários alterado para: ' + ($event.target as HTMLSelectElement).value)">
            <option value="2024" selected>2025</option>
            <option value="2024">2024</option>
            <option value="2023">2023</option>
          </select>
        </div>
        <div class="form-section">
          <h4>AVALIAÇÃO</h4>
          <div class="setting-item">
            <label for="form-autoavaliacao">Formulário de Autoavaliação:</label>
            <button id="form-autoavaliacao"
              @click="showMessage('Acessando Formulário de Autoavaliação para o ano selecionado.')">Carregar</button>
          </div>
          <div class="setting-item">
            <label for="form-avaliacaochefia">Formulário de Avaliação Chefia:</label>
            <button id="form-avaliacaochefia"
              @click="showMessage('Acessando Formulário de Avaliação Chefia para o ano selecionado.')">Carregar</button>
          </div>
          <div class="setting-item">
            <label>Liberar formulário:</label>
            <div class="button-group">
              <button class="btn-yellow" @click="showMessage('Definir prazo para o formulário.')">Prazo</button>
              <button class="btn-green" @click="showMessage('Formulário liberado para preenchimento.')">Liberar</button>
            </div>
          </div>
          <div class="form-section">
            <h4>PDI - PLANO DE DESENVOLVIMENTO INDIVIDUAL</h4>
            <div class="setting-item">
              <label for="form-pactuacao">Formulário de Pactuação (PDI):</label>
              <button id="form-pactuacao"
                @click="showMessage('Acessando Formulário de Pactuação (PDI) para o ano selecionado.')">Carregar</button>
            </div>
            <div class="setting-item">
              <label for="form-metas">Formulário de Cumprimento de Metas (PDI):</label>
              <button id="form-metas"
                @click="showMessage('Acessando Formulário de Cumprimento de Metas (PDI) para o ano selecionado.')">Carregar</button>
            </div>
             <div class="setting-item">
            <label>Liberar formulário:</label>
            <div class="button-group">
              <button class="btn-yellow" @click="showMessage('Definir prazo para o formulário.')">Prazo</button>
              <button class="btn-green" @click="showMessage('Formulário liberado para preenchimento.')">Liberar</button>
            </div>
          </div>
          </div>
        </div>
      </div>

      <div class="settings-section">
        <h3>Configurações de Conta</h3>

        <div class="setting-item">
          <label for="theme">Tema:</label>
          <select id="theme" @change="showMessage('Tema alterado para: ' + ($event.target as HTMLSelectElement).value)">
            <option value="light" selected>Claro</option>
            <option value="dark">Escuro</option>
          </select>
        </div>
        <div class="setting-item">
          <label for="change-password">Alterar Senha:</label>
          <button id="change-password"
            @click="showMessage('Redirecionando para a página de alteração de senha.')">Alterar</button>
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
