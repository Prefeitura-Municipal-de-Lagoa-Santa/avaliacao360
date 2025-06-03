<script setup lang="ts">
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import DashboardCard from '@/Components/DashboardCard.vue';
import { Head } from '@inertiajs/vue3'; // Para gerenciar o <head> da página

// Importar SVGs como componentes ou usar inline SVG
// Exemplo de como poderia ser um SVG inline ou importado
const CheckCircleIcon = `<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>`;
const ClockIcon = `<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>`;
const ChartBarIcon = `<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M18 14H6M2 5h20a1 1 0 011 1v12a1 1 0 01-1 1H2a1 1 0 01-1-1V6a1 1 0 011-1z"></path></svg>`; // Ícone de exemplo, pode ser melhorado
const CalendarIcon = `<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>`;


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
        
    </div>
  </DashboardLayout>
</template>

<style scoped>
/* Estilos específicos para a página do dashboard, se necessário */
</style>