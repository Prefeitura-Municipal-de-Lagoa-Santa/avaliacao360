<script setup lang="ts">
import { defineProps, PropType, computed } from 'vue';

interface CardProps {
  iconBgColor?: string;
  buttonBgColor?: string;
  value?: string | number;
  label: string;
  chartPlaceholderText?: string;
  buttonText?: string;
  buttonAction?: () => void;
}

const props = defineProps<CardProps>();

const iconWrapperStyle = computed(() => {
  return { backgroundColor: props.iconBgColor || '#6366f1' }; // Cor padrão se não fornecida
});

const buttonStyle = computed(() => {
  return { backgroundColor: props.buttonBgColor || '#0369a1' }; // Cor padrão se não fornecida
});
</script>

<template>
  <div class="dashboard-card bg-white rounded-xl shadow-lg p-6 flex flex-col items-center text-center border border-gray-200 hover:shadow-xl transition-shadow duration-300 h-full">
    <div
      v-if="$slots.icon"
      class="card-icon-wrapper w-16 h-16 rounded-full flex justify-center items-center mb-4 text-white text-3xl"
      :style="iconWrapperStyle"
    >
      <slot name="icon"></slot>
    </div>
    <div v-if="props.value" class="value text-xl font-bold text-sky-700 mb-2">{{ props.value }}</div>
    <div class="label text-base text-gray-600 mb-4">{{ props.label }}</div>
    <div
      v-if="props.chartPlaceholderText"
      class="chart-placeholder w-full h-36 bg-indigo-50 rounded-lg flex justify-center items-center text-indigo-700 font-semibold text-sm mb-4"
    >
      {{ props.chartPlaceholderText }}
    </div>
    <button
      v-if="props.buttonText && props.buttonAction"
      @click="props.buttonAction"
      class="card-button mt-auto px-6 py-2 text-white rounded-lg font-medium transition-colors"
      :style="buttonStyle"
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