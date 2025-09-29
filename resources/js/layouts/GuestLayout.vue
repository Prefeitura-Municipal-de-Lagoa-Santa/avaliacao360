<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue';

// Adiciona uma classe ao <body> quando o layout é "montado" (ou seja, entra em uso)
onMounted(() => {
  document.body.classList.add('auth-page-active');
});

// Remove a classe do <body> quando o layout é "desmontado" (ao navegar para outra página)
onUnmounted(() => {
  document.body.classList.remove('auth-page-active');
});
</script>

<template>
  <!-- Este é o container que vai centralizar o conteúdo do slot (sua página de login) -->
  <div class="auth-container">
    <slot />
  </div>
</template>

<style>
/* Estes são os estilos globais que pertencem a este layout.
  Não usamos 'scoped' aqui porque precisamos estilizar o body e um container principal.
*/
.auth-page-active {
    font-family: 'Inter', sans-serif;
    background: linear-gradient(to bottom right, #2E3A6C, #4EC0E6);
    overflow: hidden; /* Evita barras de rolagem desnecessárias */
}

.auth-container {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    width: 100%;
    padding: 20px;
    box-sizing: border-box;
}
</style>
