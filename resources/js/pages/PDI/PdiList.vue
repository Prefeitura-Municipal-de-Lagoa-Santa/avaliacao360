<script setup lang="ts">
import { ref } from 'vue'
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import * as icons from 'lucide-vue-next'
import { route } from 'ziggy-js'
import {
  Dialog,
  DialogTrigger,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
  DialogClose,
} from '@/components/ui/dialog';

defineProps<{
  pdisToFill: any[],
  pdisToSign: any[],
  pdisPendingEmployeeSignature: any[],
  pdisCompleted: any[],
}>();

// Variáveis para modal informativo
const showInfoModal = ref(false)
const infoModalTitle = ref('')
const infoModalMessage = ref('')
 

const goToPdiForm = (id: number, pdi?: any) => {
    // Para visualização, sempre permitir acesso ao PDI
    // A verificação de interação será feita dentro do formulário
    router.get(route('pdi.show', id));
}

function showInfoDialog(pdi: any) {
  if (pdi.is_out_of_deadline) {
    if (pdi.status === 'pending_manager_fill') {
      infoModalTitle.value = 'PDI Fora do Prazo de Preenchimento'
      
      let message = 'Este PDI está fora do prazo de preenchimento.'
      
      if (pdi.exception_date_first && pdi.exception_date_end) {
        const exceptionStart = new Date(pdi.exception_date_first).toLocaleDateString('pt-BR')
        const exceptionEnd = new Date(pdi.exception_date_end).toLocaleDateString('pt-BR')
        message += ` Foi liberado para o período de ${exceptionStart} a ${exceptionEnd}, mas este período já expirou.`
      }

      message += ' Para poder preencher, por gentileza entre em contato com o RH para liberar o PDI com um novo prazo.'

      infoModalMessage.value = message
      
    } else if (pdi.status === 'pending_employee_signature') {
      infoModalTitle.value = 'PDI Fora do Prazo de Assinatura'
      
      let message = 'Este PDI está fora do prazo de assinatura.'
      
      if (pdi.exception_date_first && pdi.exception_date_end) {
        const exceptionStart = new Date(pdi.exception_date_first).toLocaleDateString('pt-BR')
        const exceptionEnd = new Date(pdi.exception_date_end).toLocaleDateString('pt-BR')
        message += ` Foi liberado para o período de ${exceptionStart} a ${exceptionEnd}, mas este período já expirou.`
      }
      
      message += ' Para poder assinar, é necessário que um administrador libere o PDI com um novo prazo.'
      
      infoModalMessage.value = message
    }
  } else {
    // Dentro do prazo mas não pode interagir (caso futuro ou outras regras)
    infoModalTitle.value = 'PDI Ainda Não Disponível'
    infoModalMessage.value = 'Este PDI ainda não está disponível para preenchimento/assinatura. O período de interação ainda não foi iniciado ou há outras restrições. Aguarde até o período correto ou entre em contato com o administrador.'
  }
  
  showInfoModal.value = true
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
                        <div class="flex items-center gap-2 mt-1">
                            <div v-if="pdi.can_interact" class="text-sm text-orange-600">
                                Status: Aguardando preenchimento pelo gestor
                            </div>
                            <div v-else class="flex items-center gap-1">
                                <icons.AlertCircleIcon class="size-4 text-red-500" />
                                <span class="text-sm text-red-600">Fora do prazo - Clique em "Preencher" para mais informações</span>
                            </div>
                        </div>
                    </div>
                    <button @click="goToPdiForm(pdi.id, pdi)" 
                            :class="pdi.can_interact ? 'btn btn-blue' : 'btn btn-orange'"
                            :title="pdi.can_interact ? 'Preencher PDI' : 'Ver motivo do bloqueio'">
                        {{ pdi.can_interact ? 'Preencher' : 'Preencher' }}
                    </button>
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
                        <div class="flex items-center gap-2 mt-1">
                            <div v-if="pdi.can_interact" class="text-sm text-purple-600">
                                Status: Preenchido pelo gestor, aguardando sua assinatura
                            </div>
                            <div v-else class="flex items-center gap-1">
                                <icons.AlertCircleIcon class="size-4 text-red-500" />
                                <span class="text-sm text-red-600">Fora do prazo - Clique em "Revisar e Assinar" para mais informações</span>
                            </div>
                        </div>
                    </div>
                    <button @click="goToPdiForm(pdi.id, pdi)" 
                            :class="pdi.can_interact ? 'btn btn-green' : 'btn btn-orange'"
                            :title="pdi.can_interact ? 'Revisar e Assinar PDI' : 'Ver motivo do bloqueio'">
                        {{ pdi.can_interact ? 'Revisar e Assinar' : 'Revisar e Assinar' }}
                    </button>
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
                    <button @click="goToPdiForm(pdi.id, pdi)" class="btn btn-blue">Visualizar</button>
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
                    <button @click="goToPdiForm(pdi.id, pdi)" class="btn btn-blue">Visualizar</button>
                </div>
            </div>
        </div>

        <!-- Mensagem quando não há PDIs -->
        <div v-if="pdisToFill.length === 0 && pdisToSign.length === 0 && pdisPendingEmployeeSignature.length === 0 && pdisCompleted.length === 0" class="bg-white p-8 rounded-lg shadow text-center">
            <h3 class="text-lg font-medium text-gray-500 mb-2">Nenhum PDI encontrado</h3>
            <p class="text-gray-400">Não há PDIs disponíveis para você no momento.</p>
        </div>
    </DashboardLayout>

    <!-- Dialog Informativo -->
    <Dialog :open="showInfoModal" @update:open="showInfoModal = false">
        <DialogContent class="sm:max-w-lg bg-white">
            <DialogHeader>
                <DialogTitle class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                    <icons.InfoIcon v-if="!infoModalTitle.includes('Fora do Prazo')" class="size-5 text-blue-500" />
                    <icons.AlertCircleIcon v-else class="size-5 text-orange-500" />
                    {{ infoModalTitle }}
                </DialogTitle>
                <DialogDescription class="mt-3 text-sm text-gray-600 leading-relaxed">
                    {{ infoModalMessage }}
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="mt-6">
                <DialogClose as-child>
                    <button type="button" class="w-full sm:w-auto inline-flex justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors duration-200">
                        Entendi
                    </button>
                </DialogClose>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>