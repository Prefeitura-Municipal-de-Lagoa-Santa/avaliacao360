<script setup lang="ts">
import { ref, watch } from 'vue';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import DashboardCard from '@/components/DashboardCard.vue';
import { Head, router } from '@inertiajs/vue3';
import * as icons from 'lucide-vue-next';

const props = defineProps<{
  selectedYear: number;
  availableYears: number[];
  prazoAvaliacao: { term_first: string; term_end: string } | null;
  prazoPdi: { term_first: string; term_end: string } | null;
  dashboardStats: {
    completedAssessments: number;
    pendingAssessments: number;
    unansweredAssessments: number;
    overallProgress: string;
  };
}>();

const selectedYear = ref(props.selectedYear);
const availableYears = ref(props.availableYears);

watch(selectedYear, (year) => {
  router.get(route('dashboard.index'), { year }, {
    preserveScroll: true,
    preserveState: true,
    replace: true,
  });
});

function formatPrazo(prazo: { term_first: string; term_end: string } | null): string {
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
  router.get(route('evaluations.pending'));
}

function showDetailsForCompleted() {
  router.get(route('evaluations.completed'));
}

function showDetailsForUnanswered() {
  router.get(route('evaluations.unanswered'));
}
</script>

<template>
  <Head title="Dashboard" />
  <DashboardLayout pageTitle="Dashboard de Avaliação">

    <!-- Dropdown de Ano -->
    <div class="flex justify-end mb-6">
      <label class="font-semibold mr-2 text-gray-700">Ano:</label>
      <select v-model="selectedYear" class="border rounded px-3 py-1 text-gray-700">
        <option v-for="year in availableYears" :key="year" :value="year">
          {{ year }}
        </option>
      </select>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
      <!-- Avaliações Concluídas -->
      <DashboardCard
        :value="props.dashboardStats.completedAssessments"
        label="Avaliações Concluídas"
        iconBgColor="#10b981"
        :buttonAction="showDetailsForCompleted"
        buttonText="Visualizar"
      >
        <template #icon>
          <icons.CheckCircle class="w-8 h-8" />
        </template>
      </DashboardCard>

      <!-- Avaliações Pendentes (durante o prazo) -->
      <DashboardCard
        v-if="props.dashboardStats.pendingAssessments > 0"
        :value="props.dashboardStats.pendingAssessments"
        label="Avaliações Pendentes"
        iconBgColor="#f97316"
        :buttonAction="showDetailsForDeadline"
        buttonText="Ver Pendências"
      >
        <template #icon>
          <icons.CircleAlert />
        </template>
      </DashboardCard>

      <!-- Avaliações Sem Resposta (prazo encerrado) -->
      <DashboardCard
        v-if="props.dashboardStats.unansweredAssessments > 0"
        :value="props.dashboardStats.unansweredAssessments"
        label="Sem Resposta (fora do prazo)"
        iconBgColor="#dc2626"
        :buttonAction="showDetailsForUnanswered"
        buttonText="Ver Detalhes"
      >
        <template #icon>
          <icons.AlertTriangle />
        </template>
      </DashboardCard>

      <!-- Progresso Geral -->
      <DashboardCard
        :value="props.dashboardStats.overallProgress"
        label="Progresso Geral"
        iconBgColor="#6366f1"
      >
        <template #icon>
          <icons.ChartNoAxesCombined />
        </template>
      </DashboardCard>

      <!-- Prazo Avaliação -->
      <DashboardCard
        :value="formatPrazo(props.prazoAvaliacao)"
        label="Data Avaliação"
        iconBgColor="#ef4444"
      >
        <template #icon>
          <icons.CalendarDays />
        </template>
      </DashboardCard>

      <!-- Prazo PDI -->
      <DashboardCard
        :value="formatPrazo(props.prazoPdi)"
        label="Data PDI"
        iconBgColor="#f97316"
      >
        <template #icon>
          <icons.CalendarClock />
        </template>
      </DashboardCard>
    </div>
  </DashboardLayout>
</template>
