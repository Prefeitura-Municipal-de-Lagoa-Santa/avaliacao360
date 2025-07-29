<script setup lang="ts">
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import { Head, router } from '@inertiajs/vue3';

defineProps<{
  pdisToFill: any[],
  pdisToSign: any[],
  pdisCompleted: any[],
}>();
 

const goToPdiForm = (id: number) => {
    router.get(route('pdi.show', id));
}

</script>

<template>
    <Head title="Meus PDIs" />
    <DashboardLayout pageTitle="Plano de Desenvolvimento Individual">
        <div v-if="pdisToFill.length > 0" class="mb-8">
            <h2 class="text-xl font-bold mb-4">PDIs Pendentes de Preenchimento (Sua Equipe)</h2>
            <div class="bg-white p-4 rounded-lg shadow">
                <div v-for="pdi in pdisToFill" :key="pdi.id" class="flex justify-between items-center p-2 border-b">
                    <span>{{ pdi.person.name }} - Ano: {{ pdi.pdi.year }}</span>
                    <button @click="goToPdiForm(pdi.id)" class="btn btn-blue">Preencher</button>
                </div>
            </div>
        </div>

        <div v-if="pdisToSign.length > 0" class="mb-8">
            <h2 class="text-xl font-bold mb-4">PDIs para sua Ciência e Assinatura</h2>
             <div class="bg-white p-4 rounded-lg shadow">
                <div v-for="pdi in pdisToSign" :key="pdi.id" class="flex justify-between items-center p-2 border-b">
                    <span>Gestor: {{ pdi.manager.name }} - Ano: {{ pdi.pdi.year }}</span>
                    <button @click="goToPdiForm(pdi.id)" class="btn btn-green">Revisar e Assinar</button>
                </div>
            </div>
        </div>

         <div v-if="pdisCompleted.length > 0" class="mb-8">
            <h2 class="text-xl font-bold mb-4">Histórico de PDIs</h2>
             <div class="bg-white p-4 rounded-lg shadow">
                <div v-for="pdi in pdisCompleted" :key="pdi.id" class="flex justify-between items-center p-2 border-b">
                     <span>{{ pdi.person.id === $page.props.auth.user.id ? `Gestor: ${pdi.manager.name}` : `Servidor: ${pdi.person.name}`}} - Ano: {{ pdi.pdi.year }}</span>
                    <button @click="goToPdiForm(pdi.id)" class="btn btn-blue">Visualizar</button>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>