<script setup lang="ts">
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import DashboardCard from '@/components/DashboardCard.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, onMounted  } from 'vue';
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

// Recebe as props do controller
const props = defineProps<{
  prazo: { term_first: string; term_end: string; } | null;
}>();

// Estado para controlar o diálogo
const isDialogOpen = ref(false);
const dialogMessage = ref('');
const isManagerCardVisible = ref(false);

onMounted(async () => {
  try {
    const response = await fetch(route('evaluations.status')); // Rota para checkManagerEvaluationStatus()
    const data = await response.json();
    isManagerCardVisible.value = data.available;
  } catch (error) {
    console.error("Erro ao verificar status de gestor:", error);
  }
});

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

/**
 * Verifica a disponibilidade do formulário e navega ou abre um diálogo.
 */
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
    dialogMessage.value = data.message;
    isDialogOpen.value = true;
  }
}

// ... props, isDialogOpen, etc.

// Adicione esta nova função para lidar com o clique na autoavaliação
async function handleAutoavaliacaoClick() {
  try {
    const response = await fetch(route('evaluations.autoavaliacao.status')); // ROTA ALTERADA
    const data = await response.json();
   
    if (data.available) {
      router.get(route('evaluations.autoavaliacao.show')); // ROTA ALTERADA
    } else {
      dialogMessage.value = data.message;
      isDialogOpen.value = true;
    }
  } catch (error) {
    console.error('Erro ao verificar status da autoavaliação:', error);
    dialogMessage.value = 'Ocorreu um erro ao verificar a disponibilidade da avaliação. Tente novamente.'; // Mensagem genérica
    isDialogOpen.value = true;
  }
}

function showDetailsForDeadline() {
  // Lógica para mostrar detalhes, talvez usando um modal.
  console.log('Ação de exemplo. Implementar lógica de modal ou navegação.');
  alert('Ação de exemplo. (Substitua este alert por um modal ou navegação)');
}

function handleManagerEvaluationClick() {
  router.get(route('evaluations.subordinates.list'));
}
</script>

<template>
  <Head title="Dashboard" />
  <DashboardLayout pageTitle="Dashboard de Avaliação">

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
         <DashboardCard
        label="Autoavaliação"
        iconBgColor="#1d82c4"
        :buttonAction="handleAutoavaliacaoClick" buttonText="Começar agora"
      >
        <template #icon>
          <icons.ListTodo />
        </template>
      </DashboardCard>
    
     
      <!-- Card de Avaliação Chefia Atualizado -->
      <DashboardCard
        label="Avaliação Chefia"
        iconBgColor="#ef4444"
        buttonText="Começar agora"
        :buttonAction="handleChefiaEvaluationClick"
      >
        <template #icon>
          <icons.ListTodo>
          </icons.ListTodo>
          <div ></div>
        </template>
      </DashboardCard>

       <DashboardCard
      
      iconBgColor="#8b5cf6"
      buttonText="Começar agora"
      :buttonAction="handleManagerEvaluationClick"
    >
      <template #icon>
        <icons.Users />
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
