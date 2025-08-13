<script setup lang="ts">
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import DashboardCard from '@/components/DashboardCard.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue'; // onMounted não é mais necessário para isso
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

// Props recebidas do controller (agora com a lógica correta)
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
  recourseLink?: string | null;
 
}>();

// A lógica de `onMounted` para verificar o status dos cards foi removida,
// pois as props já vêm corretas do backend.

// Estado do diálogo de aviso
const isDialogOpen = ref(false);
const dialogMessage = ref('');

// Computed para checar se está dentro do prazo
const dentroDoPrazo = computed(() => {
  if (!props.prazo?.term_first || !props.prazo?.term_end) return false;
  
  try {
    const hoje = new Date();
    const inicio = new Date(props.prazo.term_first + 'T12:00:00');
    const fim = new Date(props.prazo.term_end + 'T12:00:00');
    
    // Se ainda são inválidas, retorna false
    if (isNaN(inicio.getTime()) || isNaN(fim.getTime())) {
      return false;
    }

    hoje.setHours(0, 0, 0, 0);
    inicio.setHours(0, 0, 0, 0);
    fim.setHours(0, 0, 0, 0);
    
    // Adiciona 1 dia ao fim do prazo
    fim.setDate(fim.getDate() + 1);

    return hoje >= inicio && hoje <= fim;
  } catch (error) {
    console.error('Erro ao verificar prazo:', error, props.prazo);
    return false;
  }
});


// Formata o prazo para "dd/mm - dd/mm"
function formatPrazo(prazo: { term_first: string; term_end: string; } | null): string {
  if (!prazo || !prazo.term_first || !prazo.term_end) return 'Não definido';
  
  try {
    // Agora que o backend está enviando formato Y-m-d, podemos usar diretamente
    const inicio = new Date(prazo.term_first + 'T12:00:00');
    const fim = new Date(prazo.term_end + 'T12:00:00');
    
    // Se ainda são inválidas, retorna erro
    if (isNaN(inicio.getTime()) || isNaN(fim.getTime())) {
      console.error('Datas inválidas:', { term_first: prazo.term_first, term_end: prazo.term_end });
      return 'Data inválida';
    }
    
    const options: Intl.DateTimeFormatOptions = { 
      day: '2-digit', 
      month: '2-digit'
    };
    
    const inicioFmt = inicio.toLocaleDateString('pt-BR', options);
    const fimFmt = fim.toLocaleDateString('pt-BR', options);
    
    return `${inicioFmt} - ${fimFmt}`;
  } catch (error) {
    console.error('Erro ao formatar prazo:', error, prazo);
    return 'Erro na data';
  }
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
  router.get(route('evaluations.history'));
}
</script>

<template>

  <Head title="Dashboard" />
  <DashboardLayout pageTitle="Dashboard de Avaliação">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 items-stretch">

      <DashboardCard :label="props.selfEvaluationCompleted ? 'Autoavaliação - Concluída' : 'Autoavaliação'"
        :iconBgColor="props.selfEvaluationCompleted ? '#22c55e' : '#1d82c4'"
        :buttonBgColor="props.selfEvaluationCompleted ? '#16a34a' : '#0369a1'" :buttonAction="props.selfEvaluationCompleted
          ? () => showEvaluationResult(props.selfEvaluationRequestId)
          : handleAutoavaliacaoClick" :buttonText="props.selfEvaluationCompleted ? 'Ver respostas' : 'Começar agora'"
        v-if="dentroDoPrazo && (props.selfEvaluationVisible || props.selfEvaluationCompleted)">
        <template #icon>
          <icons.ListTodo />
        </template>
      </DashboardCard>

      <DashboardCard :label="props.bossEvaluationCompleted ? 'Avaliação Chefia - Concluída' : 'Avaliação Chefia'"
        :iconBgColor="props.bossEvaluationCompleted ? '#22c55e' : '#ef4444'"
        :buttonBgColor="props.bossEvaluationCompleted ? '#16a34a' : '#dc2626'" :buttonAction="props.bossEvaluationCompleted
          ? () => showEvaluationResult(props.bossEvaluationRequestId)
          : handleChefiaEvaluationClick"
        :buttonText="props.bossEvaluationCompleted ? 'Ver respostas' : 'Começar agora'"
        v-if="dentroDoPrazo && (props.bossEvaluationVisible || props.bossEvaluationCompleted)">
        <template #icon>
          <icons.ListTodo />
        </template>
      </DashboardCard>

      <DashboardCard :label="props.teamEvaluationCompleted ? 'Avaliar Equipe - Concluído' : 'Avaliar Equipe'"
        :iconBgColor="props.teamEvaluationCompleted ? '#22c55e' : '#8b5cf6'"
        :buttonBgColor="props.teamEvaluationCompleted ? '#16a34a' : '#7c3aed'"
        :buttonAction="handleManagerEvaluationClick"
        :buttonText="props.teamEvaluationCompleted ? 'Ver respostas' : 'Começar agora'"
        v-if="dentroDoPrazo && (props.teamEvaluationVisible || props.teamEvaluationCompleted)">
        <template #icon>
          <icons.Users />
        </template>
      </DashboardCard>

      <DashboardCard label="Minhas Notas" iconBgColor="#15B2CB" buttonText="Ver Resultados"
        :buttonAction="showDetailsForDeadline">
        <template #icon>
          <icons.ListTodo />
        </template>
      </DashboardCard>

      <DashboardCard label="Data Avaliação" :value="formatPrazo(props.prazo)" iconBgColor="#ef4444">
        <template #icon>
          <icons.CalendarDays />
        </template>
      </DashboardCard>

      <DashboardCard v-if="props.recourseLink" label="Acompanhar Recurso" iconBgColor="#059669" buttonText="Visualizar"
        :buttonAction="() => props.recourseLink && router.get(props.recourseLink)">
        <template #icon>
          <icons.FileSearch />
        </template>
      </DashboardCard>


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
              <button type="button"
                class="w-full sm:w-auto inline-flex justify-center rounded-md border border-transparent bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                Entendido
              </button>
            </DialogClose>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  </DashboardLayout>
</template>