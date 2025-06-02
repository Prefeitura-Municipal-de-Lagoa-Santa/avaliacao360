<script setup lang="ts">
import { defineProps, PropType, computed } from 'vue';

interface CardProps {
  iconBgColor?: string;
  value: string | number;
  label: string;
  chartPlaceholderText?: string;
  buttonText?: string;
  buttonAction?: () => void;
}

const props = defineProps<CardProps>();

const iconWrapperStyle = computed(() => {
  return { backgroundColor: props.iconBgColor || '#6366f1' }; // Cor padrão se não fornecida
});
</script>

<template>
  <div class="dashboard-card bg-white rounded-xl shadow-lg p-6 flex flex-col items-center text-center border border-gray-200 hover:shadow-xl transition-shadow duration-300">
    <div
      v-if="$slots.icon"
      class="card-icon-wrapper w-16 h-16 rounded-full flex justify-center items-center mb-4 text-white text-3xl"
      :style="iconWrapperStyle"
    >
      <slot name="icon"></slot>
    </div>
    <div class="value text-4xl font-bold text-indigo-600 mb-2">{{ props.value }}</div>
    <div class="label text-base text-gray-600 mb-4">{{ props.label }}</div>
    <div
      v-if="props.chartPlaceholderText"
      class="chart-placeholder w-full h-36 bg-indigo-50 rounded-lg flex justify-center items-center text-indigo-700 font-semibold text-sm mt-auto"
    >
      {{ props.chartPlaceholderText }}
    </div>
    <button
      v-if="props.buttonText && props.buttonAction"
      @click="props.buttonAction"
      class="card-button mt-5 px-6 py-2 bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 transition-colors"
    >
      {{ props.buttonText }}
    </button>
  </div>
</template>

<style scoped>
/* Estilos específicos do card, se necessário */
.dashboard-card:hover {
  transform: translateY(-4px);
}
</style>