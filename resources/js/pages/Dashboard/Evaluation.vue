<script setup lang="ts">
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import DashboardCard from '@/components/DashboardCard.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';
import * as icons from 'lucide-vue-next';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogClose,
} from '@/components/ui/dialog';

// Props recebidas do controller
const props = defineProps<{
  prazo: { term_first: string; term_end: string; } | null;
  selfEvaluationVisible: boolean;
  bossEvaluationVisible: boolean;
  teamEvaluationVisible: boolean;
  selfEvaluationCompleted: boolean;
  bossEvaluationCompleted: boolean;
  teamEvaluationCompleted: boolean;
  selfEvaluationRequestId?: number | null;
  bossEvaluationRequestId?: number | null;
  teamEvaluationRequestId?: number | null;
}>();

// Computed para checar se está dentro do prazo
const dentroDoPrazo = computed(() => {
  if (!props.prazo?.term_first || !props.prazo?.term_end) return false;
  const hoje = new Date();
  const inicio = new Date(props.prazo.term_first);
  const fim = new Date(props.prazo.term_end);

  hoje.setHours(0, 0, 0, 0);
  inicio.setHours(0, 0, 0, 0);
  fim.setHours(0, 0, 0, 0);

  return hoje >= inicio && hoje <= fim;
});

// Estado do diálogo de aviso
const isDialogOpen = ref(false);
const dialogMessage = ref('');
const isManagerCardVisible = ref(false);

// Checagem para painel de gestor (opcional)
onMounted(async () => {
  try {
    const response = await fetch(route('evaluations.status'));
    const data = await response.json();
    isManagerCardVisible.value = data.available;
  } catch (error) {
    console.error("Erro ao verificar status de gestor:", error);
  }
});

// Formata o prazo para "dd/mm - dd/mm"
function formatPrazo(prazo: { term_first: string; term_end: string; } | null): string {
  if (!prazo) return 'Não definido';
  const options: Intl.DateTimeFormatOptions = { day: '2-digit', month: '2-digit' };

  const inicio = new Date(prazo.term_first);
  inicio.setDate(inicio.getDate() - 1);

  const fim = new Date(prazo.term_end);
  fim.setDate(fim.getDate() - 1);

  const inicioFmt = inicio.toLocaleDateString('pt-BR', options);
  const fimFmt = fim.toLocaleDateString('pt-BR', options);

  return `${inicioFmt} - ${fimFmt}`;
}

function goToCalendar() {
  router.get(route('calendar'));
}

async function handleChefiaEvaluationClick() {
  try {
    const response = await fetch(route('evaluations.chefia.status'));
    const data = await response.json();
    if (data.available) {
      router.get(route('evaluations.chefia.show'));
    } else {
      dialogMessage.value = data.message;
      isDialogOpen.value = true;
    }
  } catch (error) {
    console.error('Erro ao verificar status da avaliação:', error);
    dialogMessage.value = 'Ocorreu um erro ao verificar a disponibilidade da avaliação. Tente novamente.';
    isDialogOpen.value = true;
  }
}

async function handleAutoavaliacaoClick() {
  try {
    const response = await fetch(route('evaluations.autoavaliacao.status'));
    const data = await response.json();
    if (data.available) {
      router.get(route('evaluations.autoavaliacao.show'));
    } else {
      dialogMessage.value = data.message;
      isDialogOpen.value = true;
    }
  } catch (error) {
    console.error('Erro ao verificar status da autoavaliação:', error);
    dialogMessage.value = 'Ocorreu um erro ao verificar a disponibilidade da avaliação. Tente novamente.';
    isDialogOpen.value = true;
  }
}

function handleManagerEvaluationClick() {
  router.get(route('evaluations.subordinates.list'));
}

// Função dinâmica para visualizar resultado
function showEvaluationResult(evaluationRequestId: number | null | undefined) {
  if (!evaluationRequestId) return;
  router.get(route('evaluations.autoavaliacao.result', { evaluationRequest: evaluationRequestId }));
}


function showDetailsForDeadline() {
  alert('Ação de exemplo. (Substitua este alert por um modal ou navegação)');
}
</script>

<template>
  <Head title="Dashboard" />
  <DashboardLayout pageTitle="Dashboard de Avaliação">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">

      <!-- Card Autoavaliação -->
      <DashboardCard
        label="Autoavaliação"
        iconBgColor="#1d82c4"
        :buttonAction="props.selfEvaluationCompleted
          ? () => showEvaluationResult(props.selfEvaluationRequestId)
          : handleAutoavaliacaoClick"
        :buttonText="props.selfEvaluationCompleted ? 'Ver respostas' : 'Começar agora'"
        v-if="dentroDoPrazo && (props.selfEvaluationVisible || props.selfEvaluationCompleted)"
      >
        <template #icon>
          <icons.ListTodo />
        </template>
      </DashboardCard>

      <!-- Card Avaliação Chefia -->
      <DashboardCard
        label="Avaliação Chefia"
        iconBgColor="#ef4444"
        :buttonAction="props.bossEvaluationCompleted
          ? () => showEvaluationResult(props.bossEvaluationRequestId)
          : handleChefiaEvaluationClick"
        :buttonText="props.bossEvaluationCompleted ? 'Ver respostas' : 'Começar agora'"
        v-if="dentroDoPrazo && (props.bossEvaluationVisible || props.bossEvaluationCompleted)"
      >
        <template #icon>
          <icons.ListTodo />
        </template>
      </DashboardCard>

      <!-- Card Avaliar Equipe -->
      <DashboardCard
        label="Avaliar Equipe"
        iconBgColor="#8b5cf6"
        :buttonAction="props.teamEvaluationCompleted
          ? () => showEvaluationResult(props.teamEvaluationRequestId)
          : handleManagerEvaluationClick"
        :buttonText="props.teamEvaluationCompleted ? 'Ver respostas' : 'Começar agora'"
        v-if="dentroDoPrazo && (props.teamEvaluationVisible || props.teamEvaluationCompleted)"
      >
        <template #icon>
          <icons.Users />
        </template>
      </DashboardCard>

      <!-- Card Minhas Avaliações (sempre visível) -->
      <DashboardCard
        label="Minhas Avaliações"
        iconBgColor="#15B2CB"
        buttonText="Ver Resultados"
        :buttonAction="showDetailsForDeadline"
      >
        <template #icon>
          <icons.ListTodo />
        </template>
      </DashboardCard>

      <!-- Card Prazo da Avaliação (sempre visível) -->
      <DashboardCard
        :value="formatPrazo(props.prazo)"
        iconBgColor="#ef4444"
        :buttonAction="goToCalendar"
        buttonText="Ver Calendário"
      >
        <template #icon>
          <icons.CalendarDays />
        </template>
      </DashboardCard>

      <!-- Diálogo de aviso -->
      <Dialog v-model:open="isDialogOpen">
        <DialogContent class="sm:max-w-md bg-white">
          <DialogHeader>
            <DialogTitle class="text-lg font-semibold text-gray-900">Aviso</DialogTitle>
            <DialogDescription class="mt-2 text-sm text-gray-600">
              {{ dialogMessage }}
            </DialogDescription>
          </DialogHeader>
          <DialogFooter class="mt-6">
            <DialogClose as-child>
              <button
                type="button"
                class="w-full sm:w-auto inline-flex justify-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
              >
                Entendido
              </button>
            </DialogClose>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  </DashboardLayout>
</template>
