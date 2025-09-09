<script setup lang="ts">
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import { Head, router } from '@inertiajs/vue3';

defineProps<{
  pdisToFill: any[],
  pdisToSign: any[],
  pdisPendingEmployeeSignature: any[],
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
                    <div class="flex-1">
                        <span class="font-medium">{{ pdi.person.name }}</span>
                        <span class="text-gray-600"> - Ano: {{ pdi.pdi.year }}</span>
                        <div class="text-sm text-orange-600 mt-1">
                            Status: Aguardando preenchimento pelo gestor
                        </div>
                    </div>
                    <button @click="goToPdiForm(pdi.id)" class="btn btn-blue">Preencher</button>
                </div>
            </div>
        </div>

        <div v-if="pdisToSign.length > 0" class="mb-8">
            <h2 class="text-xl font-bold mb-4">PDIs para sua Ciência e Assinatura</h2>
             <div class="bg-white p-4 rounded-lg shadow">
                <div v-for="pdi in pdisToSign" :key="pdi.id" class="flex justify-between items-center p-2 border-b">
                    <div class="flex-1">
                        <span class="font-medium">Gestor: {{ pdi.manager.name }}</span>
                        <span class="text-gray-600"> - Ano: {{ pdi.pdi.year }}</span>
                        <div class="text-sm text-purple-600 mt-1">
                            Status: Preenchido pelo gestor, aguardando sua assinatura
                        </div>
                    </div>
                    <button @click="goToPdiForm(pdi.id)" class="btn btn-green">Revisar e Assinar</button>
                </div>
            </div>
        </div>

        <!-- Nova seção: PDIs preenchidos pelo gestor aguardando assinatura do funcionário -->
        <div v-if="pdisPendingEmployeeSignature.length > 0" class="mb-8">
            <h2 class="text-xl font-bold mb-4">PDIs Preenchidos (Aguardando Assinatura do Servidor)</h2>
            <div class="bg-white p-4 rounded-lg shadow">
                <div v-for="pdi in pdisPendingEmployeeSignature" :key="pdi.id" class="flex justify-between items-center p-2 border-b">
                    <div class="flex-1">
                        <span class="font-medium">{{ pdi.person.name }}</span>
                        <span class="text-gray-600"> - Ano: {{ pdi.pdi.year }}</span>
                        <div class="text-sm text-blue-600 mt-1">
                            Status: Preenchido pelo gestor, aguardando ciência do servidor
                        </div>
                    </div>
                    <button @click="goToPdiForm(pdi.id)" class="btn btn-blue">Visualizar</button>
                </div>
            </div>
        </div>

         <div v-if="pdisCompleted.length > 0" class="mb-8">
            <h2 class="text-xl font-bold mb-4">PDIs Concluídos</h2>
             <div class="bg-white p-4 rounded-lg shadow">
                <div v-for="pdi in pdisCompleted" :key="pdi.id" class="flex justify-between items-center p-2 border-b">
                    <div class="flex-1">
                        <span class="font-medium">{{ pdi.person.name }}</span>
                        <span class="text-gray-600"> - Ano: {{ pdi.pdi.year }}</span>
                        <div class="text-sm text-green-600 mt-1">
                            Status: Concluído e assinado por ambas as partes
                        </div>
                    </div>
                    <button @click="goToPdiForm(pdi.id)" class="btn btn-blue">Visualizar</button>
                </div>
            </div>
        </div>

        <!-- Mensagem quando não há PDIs -->
        <div v-if="pdisToFill.length === 0 && pdisToSign.length === 0 && pdisPendingEmployeeSignature.length === 0 && pdisCompleted.length === 0" class="bg-white p-8 rounded-lg shadow text-center">
            <h3 class="text-lg font-medium text-gray-500 mb-2">Nenhum PDI encontrado</h3>
            <p class="text-gray-400">Não há PDIs disponíveis para você no momento.</p>
        </div>
    </DashboardLayout>
</template>