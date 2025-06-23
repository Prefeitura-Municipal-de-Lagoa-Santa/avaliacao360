import { ref } from 'vue';

// Estado reativo que será compartilhado por toda a aplicação
const isModalVisible = ref(false);
const modalContent = ref({
  type: 'success' as 'success' | 'error',
  title: '',
  message: '',
});

/**
 * Composable para gerenciar o estado global do modal de notificações.
 */
export function useFlashModal() {

  /**
   * Define o conteúdo do modal e o exibe.
   * @param type 'success' ou 'error'
   * @param message A mensagem a ser exibida.
   */
  const showFlashModal = (type: 'success' | 'error', message: string) => {
    modalContent.value = {
      title: type === 'success' ? 'Sucesso!' : 'Ocorreu um Erro!',
      message,
      type,
    };
    isModalVisible.value = true;
  };

  /**
   * Esconde o modal.
   */
  const hideFlashModal = () => {
    isModalVisible.value = false;
  };

  return {
    isModalVisible,
    modalContent,
    showFlashModal,
    hideFlashModal,
  };
}