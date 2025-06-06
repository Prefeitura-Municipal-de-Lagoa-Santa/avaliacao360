<script setup lang="ts">
import { ref, computed } from 'vue';
import DashboardLayout from '@/layouts/DashboardLayout.vue'; // Your existing layout
import { Head } from '@inertiajs/vue3'; // For managing the page <head>

// --- ESTADO REATIVO ---
const today = new Date();
const currentYear = ref(today.getFullYear());
// getMonth() é 0-indexado (Janeiro = 0, Dezembro = 11)
const currentMonth = ref(today.getMonth());

// --- ESTADO DA CAIXA DE MENSAGEM (Existente) ---
const isMessageBoxVisible = ref(false);
const messageBoxTitle = ref('');
const messageBoxContent = ref('');

function showCalendarMessage(message: string, title: string = "Notificação") {
  messageBoxTitle.value = title;
  messageBoxContent.value = message;
  isMessageBoxVisible.value = true;
}

function hideCalendarMessage() {
  isMessageBoxVisible.value = false;
}

// --- NAVEGAÇÃO DO CALENDÁRIO (Implementada) ---
function previousMonth() {
  currentMonth.value--;
  if (currentMonth.value < 0) {
    currentMonth.value = 11; // Dezembro
    currentYear.value--;
  }
}

function nextMonth() {
  currentMonth.value++;
  if (currentMonth.value > 11) {
    currentMonth.value = 0; // Janeiro
    currentYear.value++;
  }
}

// --- PROPRIEDADES COMPUTADAS PARA VALORES DINÂMICOS ---

// Formata o cabeçalho do mês/ano
const displayedMonthYear = computed(() => {
  const date = new Date(currentYear.value, currentMonth.value);
  return date.toLocaleString('pt-BR', { month: 'long', year: 'numeric' }).replace(' de ', ' ');
});

// Gera a grade de dias para o mês atual
const calendarGridDays = computed(() => {
  const year = currentYear.value;
  const month = currentMonth.value;

  const firstDayOfMonth = new Date(year, month, 1);
  const daysInMonth = new Date(year, month + 1, 0).getDate();
  const startDayOfWeek = firstDayOfMonth.getDay(); // 0 = Domingo, 1 = Segunda, ...

  const days = [];

  // 1. Adiciona células vazias para os dias antes do início do mês
  for (let i = 0; i < startDayOfWeek; i++) {
    days.push({ type: 'empty' });
  }

  // 2. Adiciona os dias do mês
  for (let day = 1; day <= daysInMonth; day++) {
    const isToday = day === today.getDate() &&
                    month === today.getMonth() &&
                    year === today.getFullYear();

    days.push({
      type: 'day',
      number: day,
      isCurrent: isToday
    });
  }

  return days;
});

</script>

<template>
  <Head title="Calendário" />
  <DashboardLayout pageTitle="Calendário de Eventos">
    <div class="calendar-container bg-white p-4 sm:p-6 rounded-lg shadow">
      <div class="calendar-header">
        <button @click="previousMonth" class="px-3 py-2 sm:px-4 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition-colors flex items-center text-sm font-medium">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
          </svg>
          <span class="hidden sm:inline ml-1"></span>
        </button>
        <h2 class="text-base sm:text-xl font-semibold text-gray-800 capitalize text-center">
          {{ displayedMonthYear }}
        </h2>
        <button @click="nextMonth" class="px-3 py-2 sm:px-4 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition-colors flex items-center text-sm font-medium">
          <span class="hidden sm:inline mr-1"></span>
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
          </svg>
        </button>
      </div>

      <div class="calendar-grid">
        <div class="calendar-day-name"><span class="hidden sm:inline">Dom</span><span class="sm:hidden">D</span></div>
        <div class="calendar-day-name"><span class="hidden sm:inline">Seg</span><span class="sm:hidden">S</span></div>
        <div class="calendar-day-name"><span class="hidden sm:inline">Ter</span><span class="sm:hidden">T</span></div>
        <div class="calendar-day-name"><span class="hidden sm:inline">Qua</span><span class="sm:hidden">Q</span></div>
        <div class="calendar-day-name"><span class="hidden sm:inline">Qui</span><span class="sm:hidden">Q</span></div>
        <div class="calendar-day-name"><span class="hidden sm:inline">Sex</span><span class="sm:hidden">S</span></div>
        <div class="calendar-day-name"><span class="hidden sm:inline">Sáb</span><span class="sm:hidden">S</span></div>

        <div v-for="(day, index) in calendarGridDays" :key="index"
             :class="['calendar-day', { 'empty': day.type === 'empty', 'current-day': day.isCurrent }]">
          
          <template v-if="day.type === 'day'">
            {{ day.number }}

            <div class="events-container">
              <div v-if="day.number === 25 && currentMonth === 5" 
                   class="event highlight" 
                   @click="showCalendarMessage('Prazo final para Autoavaliação de Gestores.', 'Evento Importante')">
                <span class="hidden sm:inline">Autoavaliação</span>
              </div>
              
              <div v-if="day.number === 27 && currentMonth === 5" 
                   class="event" 
                   @click="showCalendarMessage('Reunião de Calibração de Feedback.', 'Reunião')">
                <span class="hidden sm:inline">Reunião</span>
              </div>
            </div>
          </template>
        </div>
      </div>
    </div>

    <div v-if="isMessageBoxVisible" class="message-box-overlay show">
  <div class="message-box">
    <h3 class="text-lg font-semibold mb-3">{{ messageBoxTitle }}</h3>
    <p class="text-sm text-gray-700 mb-5">{{ messageBoxContent }}</p>
    <button @click="hideCalendarMessage"
      class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 text-sm font-medium">
      Fechar
    </button>
  </div>
</div>
  </DashboardLayout>
</template>
