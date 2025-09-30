<script setup lang="ts">
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { XIcon, CheckCircle2Icon, XCircleIcon } from 'lucide-vue-next';

const props = defineProps<{
  show: boolean;
  type: 'success' | 'error';
  title: string;
  message: string;
}>();

const emit = defineEmits(['close']);

const isSuccess = computed(() => props.type === 'success');
</script>

<template>
  <teleport to="body">
    <transition
      enter-active-class="transition ease-out duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition ease-in duration-200"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="show" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm" @click="$emit('close')"></div>
    </transition>

    <transition
      enter-active-class="transition ease-out duration-300"
      enter-from-class="opacity-0 scale-95"
      enter-to-class="opacity-100 scale-100"
      leave-active-class="transition ease-in duration-200"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-95"
    >
      <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="relative w-full max-w-md overflow-hidden rounded-lg bg-white shadow-2xl">
          <div class="flex items-start p-6" :class="isSuccess ? 'bg-green-50' : 'bg-red-50'">
            <div
              class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full"
              :class="isSuccess ? 'bg-green-100' : 'bg-red-100'"
            >
              <CheckCircle2Icon v-if="isSuccess" class="h-6 w-6 text-green-600" />
              <XCircleIcon v-else class="h-6 w-6 text-red-600" />
            </div>
            <div class="ml-4 text-left">
              <h3 class="text-lg font-bold" :class="isSuccess ? 'text-green-900' : 'text-red-900'">
                {{ title }}
              </h3>
              <p class="mt-1 text-sm" :class="isSuccess ? 'text-green-700' : 'text-red-700'">
                {{ message }}
              </p>
            </div>
          </div>
          <div class="flex justify-end gap-3 rounded-b-lg bg-gray-50 px-6 py-4">
            <Button
              @click="$emit('close')"
              :class="isSuccess ? 'bg-green-600 hover:bg-green-700 text-white' : 'bg-red-600 hover:bg-red-700 text-white'"
            >
              OK
            </Button>
          </div>
           <button @click="$emit('close')" class="absolute top-3 right-3 rounded-full p-1 text-gray-400 hover:bg-gray-200 transition-colors">
            <XIcon class="h-5 w-5" />
          </button>
        </div>
      </div>
    </transition>
  </teleport>
</template>