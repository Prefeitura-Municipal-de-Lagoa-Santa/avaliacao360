<script setup lang="ts">
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import DashboardCard from '@/components/DashboardCard.vue';
import { Head, router } from '@inertiajs/vue3';
import * as icons from 'lucide-vue-next';

// Recebe as props de prazo do controller. Podem ser nulas.
const props = defineProps<{
  prazoAvaliacao: { term_first: string; term_end: string; } | null;
  prazoPdi: { term_first: string; term_end: string; } | null;
}>();

// Função para formatar o prazo para exibição
function formatPrazo(prazo: { term_first: string; term_end: string; } | null): string {
  if (!prazo) return 'Não definido';
 
  const options: Intl.DateTimeFormatOptions = { day: '2-digit', month: '2-digit' };
  
  const inicio = new Date(prazo.term_first).toLocaleDateString('pt-BR', options);
  const fim = new Date(prazo.term_end).toLocaleDateString('pt-BR', options);
  
  return `${inicio} - ${fim}`;
}

function goToCalendar() {
  router.get(route('calendar'));
}

// Importar SVGs como componentes ou usar inline SVG
// Exemplo de como poderia ser um SVG inline ou importado
const CheckCircleIcon = `<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>`;
const ClockIcon = `<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>`;
const ChartBarIcon = `<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M18 14H6M2 5h20a1 1 0 011 1v12a1 1 0 01-1 1H2a1 1 0 01-1-1V6a1 1 0 011-1z"></path></svg>`; // Ícone de exemplo, pode ser melhorado
const CalendarIcon = `<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>`;
const EvalutionIcon = `<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M17 16l-4 4m0 0l-4-4m4 4V8"></path></svg>`;

// Dados para os cards (poderiam vir do controller Laravel via props)
const dashboardData = {
  completedAssessments: 12,
  pendingAssessments: 3,
  overallProgress: '85%',
  nextDeadline: '25/06',
};

function showDetailsForDeadline() {
  // Lógica para mostrar detalhes, talvez usando um modal.
  // Substituindo o alert por um console.log ou um componente de modal.
  console.log('Detalhes do próximo prazo: Autoavaliação de Gestores.');
  // Em um app real, você usaria um componente de Modal aqui.
  // Ex: modalStore.openModal({ title: 'Próximo Prazo', content: 'Autoavaliação de Gestores.' });
  alert('Detalhes do próximo prazo: Autoavaliação de Gestores. (Substitua este alert por um modal)');
}
</script>

<template>
  <Head title="Dashboard" />
  <DashboardLayout pageTitle="Dashboard de Avaliação">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
      
      <DashboardCard
        :value="dashboardData.completedAssessments"
        label="Avaliações Concluídas"
        iconBgColor="#10b981"
        :buttonAction="showDetailsForDeadline"
        buttonText="Visualizar"
      >
        <template #icon>
          <div v-html="CheckCircleIcon"></div>
        </template>
      </DashboardCard>

      <DashboardCard
        :value="dashboardData.pendingAssessments"
        label="Avaliações Pendentes"
        iconBgColor="#f97316"
        :buttonAction="showDetailsForDeadline"
        buttonText="Ver Pendências"
      >
        <template #icon>
          <icons.CircleAlert>
          </icons.CircleAlert>
         
        </template>
      </DashboardCard>

      <DashboardCard
        :value="dashboardData.overallProgress"
        label="Progresso Geral"
        iconBgColor="#6366f1"
        :buttonAction="showDetailsForDeadline"
        buttonText="Ver Progresso"
      >
        <template #icon>
          <icons.ChartNoAxesCombined>
          </icons.ChartNoAxesCombined>
        </template>
      </DashboardCard>

      <DashboardCard
        props.prazoAvaliacao
        label="Data Avaliação"
        :value="formatPrazo(props.prazoAvaliacao)"
        iconBgColor="#ef4444"
        :buttonAction="goToCalendar"
        buttonText="Ver Calendário"
      >
        <template #icon>
          <icons.CalendarDays />
        </template>
      </DashboardCard>
      
      <DashboardCard
        props.prazoPdi
        :value="formatPrazo(props.prazoPdi)"
        label="Data PDI"
        iconBgColor="#f97316"
        :buttonAction="goToCalendar"
        buttonText="Ver Calendário"
      >
        <template #icon>
          <icons.CalendarClock />
        </template>
      </DashboardCard>
    </div>
  </DashboardLayout>
</template>

