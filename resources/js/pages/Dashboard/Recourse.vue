<script setup lang="ts">
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import DashboardCard from '@/components/DashboardCard.vue';
import { Head, router } from '@inertiajs/vue3';
import * as icons from 'lucide-vue-next';

// Recebe a prop 'recourse' do controller
const props = defineProps<{
  recourse: { term_first: string; term_end: string; } | null;
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
  <Head title="Recursos" />
  <DashboardLayout pageTitle="Dashboard de Recursos">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
      
      <DashboardCard value="0" label="Recursos abertos" buttonText="Acessar" iconBgColor="#1d82c4">
        <template #icon><icons.FileSignature /></template>
      </DashboardCard>

      <DashboardCard value="0" label="Recursos em analise" buttonText="Acessar" iconBgColor="#1d82c4">
        <template #icon><icons.FileSignature /></template>
      </DashboardCard>

      <DashboardCard value="85%" label="recursos analisados" buttonText="Ver Progresso" iconBgColor="#6366f1">
        <template #icon><icons.ChartNoAxesCombined /></template>
      </DashboardCard>

      <DashboardCard
        props.prazoPdi
        :value="formatPrazo(props.prazoPdi)"
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
