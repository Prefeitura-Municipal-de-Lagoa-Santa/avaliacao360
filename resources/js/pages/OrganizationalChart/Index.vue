<script setup>
import { ref, onMounted, watch, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue'; // Confirme o nome do seu layout
import { OrgChart } from 'd3-org-chart';

const props = defineProps({
  charts: Array, // Recebe um array de organogramas do controller
});

const chartContainer = ref(null);
const selectedChartId = ref(null);
let chartInstance = null;

// Encontra os dados do organograma para a secretaria selecionada no dropdown
const currentChartData = computed(() => {
  if (!selectedChartId.value || !props.charts) {
    return null;
  }
  const found = props.charts.find(c => c.id === selectedChartId.value);
  return found ? found.chartData : null;
});

// Função para inicializar e desenhar o gráfico
const drawChart = () => {
  if (chartContainer.value && currentChartData.value) {
    if (!chartInstance) {
      chartInstance = new OrgChart();
    }

    chartInstance
      .container(chartContainer.value)
      .data(currentChartData.value)
      .nodeId(d => d.id)
      .parentNodeId(d => d.parentId)
      .nodeContent(d => {
        // Lógica da aparência do nó foi simplificada para remover a lista de pessoas
        const color = getNodeColor(d.data.type);
        return `
          <div class="node-template" style="border-top-color: ${color};">
            <div class="node-title">${d.data.label}</div>
            <div class="node-type" style="background-color: ${color};">${d.data.type}</div>
          </div>`;
      })
      .initialZoom(0.8)
      .render()
      .expandAll();
  }
};

// Quando o componente é montado, seleciona a primeira secretaria da lista por defeito
onMounted(() => {
  if (props.charts && props.charts.length > 0) {
    selectedChartId.value = props.charts[0].id;
  }
});

// Observa mudanças na seleção do dropdown e redesenha o gráfico
watch(currentChartData, (newData) => {
  if (newData) {
    drawChart();
  }
}, { deep: true });

// Função de estilo para as cores dos nós
const getNodeColor = (type) => {
    const colors = {
        'Secretaria': '#7B1FA2', 'Diretoria': '#303F9F',
        'Coordenação': '#0288D1', 'Departamento': '#388E3C',
    };
    return colors[type] || '#FBC02D'; // Cor para 'Outro'
};
</script>

<style>
.chart-container-wrapper { overflow: hidden; padding: 10px; }
.chart-container { width: 100%; height: 80vh; }
.node-template {
  background-color: white; border-radius: 6px; padding: 8px 14px;
  font-family: 'Inter', sans-serif; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
  border-top: 4px solid; text-align: left; min-width: 240px;
}
.node-title { font-weight: 600; font-size: 15px; color: #2d3748; }
.node-type {
  font-size: 11px; font-weight: 500; color: #fff; border-radius: 9999px;
  padding: 2px 8px; display: inline-block; margin-top: 6px;
}
.fallback-container { text-align: center; margin-top: 40px; padding: 24px; background-color: #fff; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
.fallback-title { font-size: 1.125rem; font-weight: 600; color: #4a5568; }
.fallback-text { font-size: 0.875rem; margin-top: 8px; color: #718096; }
</style>

<template>
  <DashboardLayout>
    <Head title="Organograma" />

    <div class="p-4 sm:p-6 lg:p-8 bg-gray-100 min-h-screen">
      <div class="max-w-7xl mx-auto mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Organograma Estrutural</h1>
                <p class="text-gray-600">Visão hierárquica das unidades organizacionais.</p>
            </div>
            <!-- Dropdown para selecionar a Secretaria -->
            <div v-if="props.charts && props.charts.length > 0" class="mt-4 sm:mt-0">
                <label for="secretaria-select" class="block text-sm font-medium text-gray-700">Visualizar Secretaria:</label>
                <select id="secretaria-select" v-model="selectedChartId" class="mt-1 block w-full sm:w-72 pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                    <option v-for="chart in props.charts" :key="chart.id" :value="chart.id">
                        {{ chart.name }}
                    </option>
                </select>
            </div>
        </div>
      </div>

      <!-- Container onde o D3 irá desenhar o organograma -->
      <div v-if="currentChartData" class="chart-container-wrapper bg-white rounded-lg shadow-md">
        <div ref="chartContainer" class="chart-container"></div>
      </div>
      
      <!-- Mensagem de fallback -->
      <div v-else class="fallback-container">
        <h3 class="fallback-title">Nenhum dado para exibir</h3>
        <p class="fallback-text">Não foi possível carregar os dados do organograma ou nenhuma secretaria foi encontrada.</p>
      </div>
    </div>
  </DashboardLayout>
</template>