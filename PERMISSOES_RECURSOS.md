# Sistema de Permissões para Recursos de Avaliação

## Visão Geral

O sistema de permissões para recursos foi implementado com base em duas roles principais:

### 🔑 **RH (Role: "recourse")**
- **Acesso Dashboard**: ✅ Vê estatísticas de TODOS os recursos do sistema
- **Acesso Listagem**: ✅ Visualiza TODOS os recursos independente de responsável
- **Permissões**:
  - ✅ Ver todos os recursos independente de responsável
  - ✅ Atribuir responsáveis (apenas pessoas com role "Comissão")
  - ✅ Remover responsáveis
  - ✅ Responder recursos
  - ✅ Marcar como em análise
  - ✅ Gerenciar anexos

### 👥 **Comissão (Role: "Comissão")**
- **Acesso Dashboard**: ✅ Vê estatísticas APENAS dos recursos pelos quais é responsável
- **Acesso Listagem**: ✅ Visualiza APENAS recursos pelos quais é responsável
- **Permissões**:
  - ✅ Ver recursos onde foi atribuído como responsável
  - ❌ NÃO pode atribuir novos responsáveis
  - ❌ NÃO pode remover responsáveis
  - ✅ Responder recursos (apenas os seus)
  - ✅ Marcar como em análise (apenas os seus)
  - ✅ Gerenciar anexos de resposta

## Interface Diferenciada por Papel

### 📊 **Dashboard**
- **RH**: "Dashboard de Recursos" com totais globais
- **Comissão**: "Meus Recursos para Análise" com totais filtrados + aviso informativo

### 📋 **Listagem**
- **RH**: "Recursos [Status]" com coluna de responsáveis visível
- **Comissão**: "Meus Recursos [Status]" com aviso informativo

### 🏷️ **Labels dos Cards**
- **RH**: "Recursos Abertos", "Recursos em Análise", etc.
- **Comissão**: "Recursos Atribuídos", "Em Minha Análise", "Deferidos por Mim", etc.

## Implementação Técnica

### Funções Helper Criadas

```php
// Verifica se é RH
private function isRH(): bool

// Verifica se é responsável por um recurso específico
private function isResponsibleForRecourse(EvaluationRecourse $recourse): bool

// Verifica se pode acessar um recurso (RH ou responsável)
private function canAccessRecourse(EvaluationRecourse $recourse): bool
```

### Controladores Atualizados

#### DashboardController
- **`recourse()`**: Filtra dados baseado na role
  - RH: estatísticas globais
  - Comissão: estatísticas filtradas por responsabilidade

#### EvaluationRecourseController
1. **`index()`**: Filtra recursos baseado na role
2. **`review()`**: Controla acesso individual ao recurso
3. **`assignResponsible()`**: Apenas RH pode atribuir
4. **`removeResponsible()`**: Apenas RH pode remover
5. **`respond()`**: RH e responsáveis podem responder
6. **`markAnalyzing()`**: RH e responsáveis podem marcar

### Frontend Atualizado

#### Permissões (usePermissions.ts)
```typescript
// Permite que membros da Comissão acessem o dashboard de recursos
if (permission === 'recourse' && hasRole('Comissão')) {
  return true
}
```

#### Interface do Usuário
- **Menu**: Comissão agora vê o item "Recursos"
- **Dashboard**: Interface diferenciada com avisos informativos
- **Listagem**: Títulos e mensagens contextuais
- **Seção "Gerenciar Responsáveis"**: Só aparece para RH

## Fluxo de Trabalho Atualizado

1. **RH** ou **Comissão** podem acessar o dashboard de recursos
2. **RH** vê todos os recursos / **Comissão** vê apenas os seus
3. **RH** pode atribuir pessoas da **Comissão** como responsáveis
4. **Comissão** recebe acesso aos recursos atribuídos
5. **Comissão** analisa e responde apenas seus recursos
6. **RH** acompanha todo o processo com visão global

## Segurança

- ✅ Validação de permissões em todos os métodos
- ✅ Queries filtradas por relacionamentos
- ✅ Verificação de role "Comissão" antes de atribuir
- ✅ Controle de acesso no dashboard por role
- ✅ Interface contextual baseada no papel do usuário
- ✅ Mensagens de erro específicas
- ✅ Logs de auditoria para atribuições/remoções

## Navegação

- **RH**: Dashboard → Lista todos → Detalhes com gerenciamento
- **Comissão**: Dashboard → Lista apenas seus → Detalhes apenas visualização/resposta

## Teste do Sistema

1. **Login como RH**:
   - ✅ Menu "Recursos" visível
   - ✅ Dashboard mostra todos os recursos
   - ✅ Pode atribuir/remover responsáveis

2. **Login como Comissão**:
   - ✅ Menu "Recursos" visível
   - ✅ Dashboard mostra apenas recursos atribuídos
   - ✅ Interface com avisos informativos
   - ❌ Não pode gerenciar responsáveis
