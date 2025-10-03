<script setup lang="ts">
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import DashboardCard from '@/components/DashboardCard.vue';
import { Head, router } from '@inertiajs/vue3';
import * as icons from 'lucide-vue-next';

const props = defineProps<{
  recourse: { term_first: string; term_end: string } | null;
  totals?: {
    opened: number;
    under_analysis: number;
    responded: number;
    denied: number;
  };
  userRole?: string; // 'RH' | 'Comissão' | 'DGP'
  awaitingDgpCount?: number;
  commissionAwaitingCount?: number;
  awaitingSecretaryCount?: number;
}>();

function goToCommissionAwaiting() {
  // Comissão: listagem mostra itens que aguardam minha análise por etapa
  router.get(route('recourses.index'));
}

function goToOpenedRecourses() {
  // Para Comissão, mostra todos os recursos (sem filtro de status)
  if (props.userRole === 'Comissão') {
    router.get(route('recourses.index'));
  } else {
    // Para RH, mostra apenas recursos abertos
    router.get(route('recourses.index', { status: 'aberto' }));
  }
}

function goToUnderAnalysisRecourses() {
  router.get(route('recourses.index', { status: 'em_analise' }));
}

function goToRespondedRecourses() {
  router.get(route('recourses.index', { status: 'respondido' }));
}

function goToDeniedRecourses() {
  router.get(route('recourses.index', { status: 'indeferido' }));
}

function goToDgpAwaiting() {
  // Para DGP, a listagem ignora status e mostra por etapa, então basta ir ao index
  router.get(route('recourses.index'));
}

function goToSecretaryAwaiting() {
  // Para Secretário, a listagem ignora status e mostra por etapa, então basta ir ao index
  router.get(route('recourses.index'));
}
</script>

<template>
  <Head :title="props.userRole === 'Comissão' ? 'Recursos - Comissão' : (props.userRole === 'Secretário' ? 'Recursos - Secretário' : 'Recursos')" />
  <DashboardLayout :pageTitle="props.userRole === 'Comissão' ? 'Recursos - Comissão' : (props.userRole === 'DGP' ? 'Recursos - DGP' : (props.userRole === 'Secretário' ? 'Recursos - Secretário' : 'Dashboard de Recursos'))">

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">

      <!-- Para Comissão: apenas um card com recursos atribuídos -->
          <template v-if="props.userRole === 'Comissão'">
            <div class="col-span-full flex justify-center">
              <DashboardCard
                :value="props.commissionAwaitingCount ?? 0"
                label="Aguardando minha análise (Comissão)"
                buttonText="Ver agora"
                iconBgColor="#1d82c4"
                :buttonAction="goToCommissionAwaiting"
              >
                <template #icon><icons.FileSignature /></template>
              </DashboardCard>
            </div>
          </template>

      <!-- Para DGP/Diretor: card focado no que aguarda decisão -->
      <template v-else-if="props.userRole === 'DGP'">
        <div class="col-span-full flex justify-center">
          <DashboardCard
            :value="props.awaitingDgpCount ?? 0"
            label="Aguardando decisão (DGP)"
            buttonText="Ver agora"
            iconBgColor="#7c3aed"
            :buttonAction="goToDgpAwaiting"
          >
            <template #icon><icons.UserCheck /></template>
          </DashboardCard>
        </div>
      </template>

      <!-- Para Secretário: card focado no que aguarda decisão -->
      <template v-else-if="props.userRole === 'Secretário'">
        <div class="col-span-full flex justify-center">
          <DashboardCard
            :value="props.awaitingSecretaryCount ?? 0"
            label="Aguardando decisão (Secretário)"
            buttonText="Ver agora"
            iconBgColor="#16a34a"
            :buttonAction="goToSecretaryAwaiting"
          >
            <template #icon><icons.Stamp /></template>
          </DashboardCard>
        </div>
      </template>

      <!-- Para RH: todos os cards -->
      <template v-else-if="props.userRole === 'RH' || !props.userRole">
        <DashboardCard
          :value="props.totals?.opened ?? 0"
          label="Recursos Abertos"
          buttonText="Acessar"
          iconBgColor="#1d82c4"
          :buttonAction="goToOpenedRecourses"
        >
          <template #icon><icons.FileSignature /></template>
        </DashboardCard>

        <DashboardCard
          :value="props.totals?.under_analysis ?? 0"
          label="Recursos em Análise"
          buttonText="Acessar"
          iconBgColor="#0ea5e9"
          :buttonAction="goToUnderAnalysisRecourses"
        >
          <template #icon><icons.Loader2 class="animate-spin" /></template>
        </DashboardCard>

        <DashboardCard
          :value="props.totals?.responded ?? 0"
          label="Recursos Deferidos"
          buttonText="Ver Deferidos"
          iconBgColor="#10b981"
          :buttonAction="goToRespondedRecourses"
        >
          <template #icon><icons.CheckCircle /></template>
        </DashboardCard>

        <DashboardCard
          :value="props.totals?.denied ?? 0"
          label="Recursos Indeferidos"
          buttonText="Ver Indeferidos"
          iconBgColor="#ef4444"
          :buttonAction="goToDeniedRecourses"
        >
          <template #icon><icons.XCircle /></template>
        </DashboardCard>
      </template>

    </div>
  </DashboardLayout>
</template>
