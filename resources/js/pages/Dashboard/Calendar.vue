<script setup lang="ts">
import { ref, computed } from 'vue';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import { Head } from '@inertiajs/vue3';

// --- DEFINIÇÃO DE TIPOS (Interfaces) ---
// Define como é um objeto de evento de prazo
interface DeadlineEvent {
  start: string;
  end: string;
  title: string;
  group: 'avaliacao' | 'pdi' | 'divulgacao' | 'ciencia';
}
// Define como é um objeto de dia no calendário
interface CalendarDay {
  type: 'day' | 'empty';
  number?: number;
  isCurrent?: boolean;
}

// --- PROPS ---
// Usa a interface DeadlineEvent para tipar a prop
const props = defineProps<{
  deadlineEvents: Array<DeadlineEvent>
}>();


// --- ESTADO REATIVO ---
const today = new Date();
today.setHours(0, 0, 0, 0);
const currentYear = ref(today.getFullYear());
const currentMonth = ref(today.getMonth());

// --- ESTADO DA CAIXA DE MENSAGEM ---
const isMessageBoxVisible = ref(false);
const messageBoxTitle = ref('');
const messageBoxContent = ref('');

function showCalendarMessage(message: string, title: string) {
  messageBoxTitle.value = title;
  messageBoxContent.value = message;
  isMessageBoxVisible.value = true;
}
function hideCalendarMessage() {
  isMessageBoxVisible.value = false;
}

// --- NAVEGAÇÃO DO CALENDÁRIO ---
function previousMonth() {
  currentMonth.value--;
  if (currentMonth.value < 0) {
    currentMonth.value = 11;
    currentYear.value--;
  }
}
function nextMonth() {
  currentMonth.value++;
  if (currentMonth.value > 11) {
    currentMonth.value = 0;
    currentYear.value++;
  }
}

// --- MÉTODOS DE LÓGICA ---
/**
 * Verifica e retorna os eventos para um dia específico.
 * Adicionamos o tipo de retorno: Array<DeadlineEvent>
 */
function getEventsForDay(dayNumber: number, month: number, year: number): Array<DeadlineEvent> {
  const checkDate = new Date(year, month, dayNumber);

  return props.deadlineEvents.filter(event => {
    const startDate = new Date(event.start + 'T00:00:00');
    const endDate = new Date(event.end + 'T00:00:00');
    return checkDate >= startDate && checkDate <= endDate;
  });
}

// --- PROPRIEDADES COMPUTADAS ---
const displayedMonthYear = computed<string>(() => {
  const date = new Date(currentYear.value, currentMonth.value);
  return date.toLocaleString('pt-BR', { month: 'long', year: 'numeric' }).replace(' de ', ' ');
});

/**
 * Lida com o clique em um evento e mostra a mensagem APRIMORADA.
 */
function handleEventClick(dayNumber: number, month: number, year: number, event: DeadlineEvent) {
  // Normalizar todas as datas para comparação (apenas data, sem hora)
  const clickedDateStr = `${year}-${(month + 1).toString().padStart(2, '0')}-${dayNumber.toString().padStart(2, '0')}`;
  const startDateStr = event.start;
  const endDateStr = event.end;

  let message = event.title; // Mensagem padrão para os dias no meio do período
  let title = "Período de Preenchimento"; // Título padrão do modal

  // Lógica para os grupos existentes
  if (event.group === 'avaliacao' || event.group === 'pdi') {
    const friendlyGroupName = event.group === 'avaliacao' ? 'a Avaliação' : 'o PDI';
    title = "Período de Preenchimento";
    if (clickedDateStr === startDateStr) {
      title = "Início do Prazo";
      message = `Início do período para preencher ${friendlyGroupName}.`;
    } else if (clickedDateStr === endDateStr) {
      title = "Fim do Prazo";
      message = `Último dia para preencher ${friendlyGroupName}.`;
    }
  }

  // NOVA LÓGICA para os novos grupos
  else if (event.group === 'divulgacao') {
    title = "Divulgação das Notas";
    message = "Data oficial para a divulgação das notas das avaliações.";
  }
  else if (event.group === 'ciencia') {
    title = "Ciência da Nota";
    message = "Período para os servidores tomarem ciência de suas notas.";
    if (clickedDateStr === startDateStr) {
      title = "Início do Período de Ciência";
    } else if (clickedDateStr === endDateStr) {
      title = "Fim do Período de Ciência";
    }
  }

  showCalendarMessage(message, title);
}

/**
 * Gera a grade de dias para o mês atual.
 * Adicionamos o tipo de retorno: Array<CalendarDay>
 */
const calendarGridDays = computed((): Array<CalendarDay> => {
  const year = currentYear.value;
  const month = currentMonth.value;
  const firstDayOfMonth = new Date(year, month, 1);
  const daysInMonth = new Date(year, month + 1, 0).getDate();
  const startDayOfWeek = firstDayOfMonth.getDay();
  const days: Array<CalendarDay> = [];

  for (let i = 0; i < startDayOfWeek; i++) {
    days.push({ type: 'empty' });
  }

  for (let day = 1; day <= daysInMonth; day++) {
    const isToday = day === today.getDate() && month === today.getMonth() && year === today.getFullYear();
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
        <button @click="previousMonth"
          class="px-3 py-2 sm:px-4 bg-indigo-500 text-white rounded-md hover:bg-indigo-600 transition-colors flex items-center text-sm font-medium">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
          </svg>
          <span class="hidden sm:inline ml-1"></span>
        </button>
        <h2 class="text-base sm:text-xl font-semibold text-gray-800 capitalize text-center">
          {{ displayedMonthYear }}
        </h2>
        <button @click="nextMonth"
          class="px-3 py-2 sm:px-4 bg-indigo-500 text-white rounded-md hover:bg-indigo-600 transition-colors flex items-center text-sm font-medium">
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
              <div v-for="event in getEventsForDay(day.number!, currentMonth, currentYear)" :key="event.title" :class="['event', {
                'highlight': event.group === 'avaliacao',
                'pdi-event': event.group === 'pdi',
                'divulgacao-event': event.group === 'divulgacao',
                'ciencia-event': event.group === 'ciencia'
              }]" @click="handleEventClick(day.number!, currentMonth, currentYear, event)">

                <span class="hidden sm:inline">
                  {{ event.group === 'avaliacao' ? 'Avaliação' :
                    event.group === 'pdi' ? 'PDI' :
                      event.group === 'divulgacao' ? 'Notas' :
                        event.group === 'ciencia' ? 'Ciência' : ''
                  }}
                </span>
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

