# Atualização: Campos de Sala na Edição de Pessoas

## Descrição
Foi adicionada a funcionalidade de editar os campos relacionados à sala/localização das pessoas no formulário de edição.

## Campos Implementados

### 1. Campo "Sala"
- **Tipo**: Texto livre
- **Descrição**: Número ou código da sala onde a pessoa está alocada
- **Exemplos**: "101", "A-203", "S01", "Térreo"
- **Validação**: Opcional, máximo 255 caracteres

### 2. Campo "Descrição da Sala" 
- **Tipo**: Texto livre
- **Descrição**: Nome ou descrição funcional da sala
- **Exemplos**: "Recepção", "Diretoria", "Almoxarifado", "Sala de Reuniões"
- **Validação**: Opcional, máximo 255 caracteres

## Funcionalidades Implementadas

### Backend (Laravel)
- ✅ Campos já existiam no modelo `Person`
- ✅ Validação já implementada no `PersonController@update`
- ✅ Campos já eram passados para o frontend

### Frontend (Vue.js)
- ✅ Campos adicionados na interface `People/Edit.vue`
- ✅ Formulário atualizado para incluir os novos campos
- ✅ Validação de erro integrada
- ✅ Layout responsivo (2 colunas em telas maiores)

## Localização na Interface

Os campos de sala foram adicionados logo após a "Unidade Organizacional" no formulário de edição de pessoas, organizados em duas colunas:
- **Coluna 1**: Número/Código da Sala
- **Coluna 2**: Descrição/Nome da Sala

## Estrutura do Banco de Dados

Os campos já existiam na tabela `people`:
```sql
- `sala` VARCHAR(255) NULLABLE
- `descricao_sala` VARCHAR(255) NULLABLE
```

## Como Usar

1. Acesse a lista de pessoas
2. Clique em "Editar" em qualquer pessoa
3. Localize a seção "Informações da Sala" (após Unidade Organizacional)
4. Preencha os campos conforme necessário:
   - **Número/Código da Sala**: Identificador da sala (ex: 101, A-203)
   - **Descrição/Nome da Sala**: Nome funcional (ex: Recepção, Diretoria)
5. Salve as alterações

## Observações

- Ambos os campos são opcionais
- Os campos são utilizados na lógica de organograma e hierarquia
- A informação de sala é importante para a atribuição automática de chefias
- Os dados podem ser importados via CSV através dos campos `SALA` e `DESCRICAO_SALA`
