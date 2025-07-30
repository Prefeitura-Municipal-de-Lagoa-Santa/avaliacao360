<script setup lang="ts">
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import DashboardCard from '@/components/DashboardCard.vue';
import { Head, router } from '@inertiajs/vue3';
import * as icons from 'lucide-vue-next';
import { computed, ref } from 'vue';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
  DialogClose,
} from '@/components/ui/dialog';

const isDialogOpen = ref(false);
const dialogTitle = ref('');
const dialogMessage = ref('');

// Recebe a prop 'prazoPdi' do controller
const props = defineProps<{
  prazoPdi: { term_first: string; term_end: string; } | null;
  pdiStatus: 'available' | 'pending_manager' | 'not_released' | 'user_not_found';
  managedServers?: Array<{ id: number; name: string; cpf: string }> ;
  isManager?: boolean;
}>();
console.log(props);
const isPdiAvailable = computed(() => props.pdiStatus === 'available');
const pdiIconBgColor = computed(() => isPdiAvailable.value ? '#1d82c4' : '#9ca3af');

function showDialog(title: string, message: string) {
  dialogTitle.value = title;
  dialogMessage.value = message;
  isDialogOpen.value = true;
}

// Define a mensagem a ser exibida no card de Pactuação
const pdiMessage = computed(() => {
  if (props.pdiStatus === 'pending_manager') {
    return 'Aguardando preenchimento do gestor.';
  }
  if (props.pdiStatus === 'not_released') {
    return 'PDI do ciclo atual não liberado.';
  }
  return ''; // Sem mensagem se estiver disponível
});


function handleAccessClick() {
  if (props.isManager) {
    goToPdiList();
  } else if (props.pdiStatus === 'available') {
    router.get(route('pdi.index'));
  } else if (props.pdiStatus === 'user_not_found') {
    showDialog(
      'Acesso Bloqueado', 
      'Seu cadastro de servidor não foi encontrado. Por favor, entre em contato com o setor de RH.'
    );
  } else if (props.pdiStatus === 'pending_manager') {
    showDialog(
      'Acesso Pendente', 
      'O PDI ainda não foi preenchido pelo seu gestor. Somente após o preechimento estará disponível para revisão e assinatura.'
    );
  } else if (props.pdiStatus === 'not_released') {
    showDialog(
      'PDI Não Liberado', 
      'O PDI para o ciclo atual ainda não foi liberado pela administração.'
    );
  }
}

// Função para formatar o prazo para exibição
function formatPrazo(prazo: { term_first: string; term_end: string; } | null): string {
  if (!prazo) return 'Não definido';
 
  const options: Intl.DateTimeFormatOptions = { day: '2-digit', month: '2-digit' };
  
  const inicio = new Date(prazo.term_first).toLocaleDateString('pt-BR', options);
  const fim = new Date(prazo.term_end).toLocaleDateString('pt-BR', options);
  
  return `${inicio} - ${fim}`;
}

function goToPdiList() {
  router.get(route('pdi.index'));
}

function goToCalendar() {
  router.get(route('calendar'));
}

</script>

<template>
  <Head title="PDI" />
  <DashboardLayout pageTitle="Dashboard de PDI">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
      
      <DashboardCard 
        label="Pactuação"
        buttonText="Acessar"
        :buttonAction="handleAccessClick"
        :iconBgColor="pdiIconBgColor"
      >
        <template #icon>
          <icons.FileSignature v-if="isPdiAvailable" />
          <icons.FileLock v-else />
        </template>
      </DashboardCard>
      
      <DashboardCard value="85%" label="Progresso" buttonText="Ver Progresso" iconBgColor="#6366f1">
        <template #icon><icons.ChartNoAxesCombined /></template>
      </DashboardCard>
      <DashboardCard
        :value="formatPrazo(props.prazoPdi)"
        label="Prazo Vigente"
        iconBgColor="#f97316"
        :buttonAction="goToCalendar"
        buttonText="Ver Calendário"
      >
        <template #icon>
          <icons.CalendarClock />
        </template>
      </DashboardCard>
    </div>

  

    <Dialog :open="isDialogOpen" @update:open="isDialogOpen = $event">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{{ dialogTitle }}</DialogTitle>
          <DialogDescription>
            {{ dialogMessage }}
          </DialogDescription>
        </DialogHeader>
        <DialogFooter>
          <DialogClose as-child>
            <button type="button" class="btn btn-blue">
              Entendido
            </button>
          </DialogClose>
        </DialogFooter>
      </DialogContent>
    </Dialog>

  </DashboardLayout>
</template>