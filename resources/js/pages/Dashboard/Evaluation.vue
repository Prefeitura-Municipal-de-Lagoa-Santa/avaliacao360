<script setup lang="ts">
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import DashboardCard from '@/components/DashboardCard.vue';
import { Head } from '@inertiajs/vue3';
import * as icons from 'lucide-vue-next';

// Recebe a prop 'prazo' do controller
const props = defineProps<{
  prazo: { term_first: string; term_end: string; } | null;
}>();
// Função para formatar o prazo para exibição
function formatPrazo(prazo: { term_first: string; term_end: string; } | null): string {
  if (!prazo) return 'Não definido';
 
  const options: Intl.DateTimeFormatOptions = { day: '2-digit', month: '2-digit' };
  
  const inicio = new Date(prazo.term_first).toLocaleDateString('pt-BR', options);
  const fim = new Date(prazo.term_end).toLocaleDateString('pt-BR', options);
  
  return `${inicio} - ${fim}`;
}

// Importar SVGs como componentes ou usar inline SVG
// Exemplo de como poderia ser um SVG inline ou importado
const CheckCircleIcon = `<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>`;
const ClockIcon = `<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>`;
const ChartBarIcon = `<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M18 14H6M2 5h20a1 1 0 011 1v12a1 1 0 01-1 1H2a1 1 0 01-1-1V6a1 1 0 011-1z"></path></svg>`; // Ícone de exemplo, pode ser melhorado
const CalendarIcon = `<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>`;


// Dados para os cards (poderiam vir do controller Laravel via props)
const dashboardData = {
  
  pendingAssessments: 3,
  overallProgress: '85%',
  nextDeadline: '25/06 - 30/06',
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
        
        label="Autoavaliação"
        iconBgColor="#1d82c4"
        :buttonAction="showDetailsForDeadline"
        buttonText="Começar agora"
      >
        <template #icon>
          <icons.ListTodo>
          </icons.ListTodo>
          <div ></div>
        </template>
      </DashboardCard>
      <DashboardCard
        
        label="Avaliação Chefia"
        iconBgColor="#ef4444"
        buttonText="Começar agora"
        :buttonAction="showDetailsForDeadline"
      >
        <template #icon>
          <icons.ListTodo>
          </icons.ListTodo>
          <div ></div>
        </template>
      </DashboardCard>
      <DashboardCard
        
        label="Minhas Avaliações"
        iconBgColor="#15B2CB"
        buttonText="Ver Resultados"
        :buttonAction="showDetailsForDeadline"
      >
        <template #icon>
          <icons.ListTodo>
          </icons.ListTodo>
          <div ></div>
        </template>
      </DashboardCard>
      
      <DashboardCard
        props.prazo
        :value="formatPrazo(props.prazo)"
        label="Prazo"
        iconBgColor="#ef4444"
        buttonText="Ver Detalhes"
      >
        <template #icon>
          <icons.CalendarDays />
        </template>
      </DashboardCard>
    </div>
  </DashboardLayout>
</template>

