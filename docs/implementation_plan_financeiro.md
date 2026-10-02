# Plano de Implementação: Módulo Financeiro (Contas a Receber)

Este plano visa estruturar o módulo financeiro do sistema, permitindo a criação, listagem e gerenciamento de `Contas` (receitas e despesas), além de automatizar a geração destas contas a partir de eventos do sistema, como a cobrança de figurinos de espetáculos e mensalidades.

## Estratégia Geral
As tabelas `contas` e `categorias_contas` já existem, e a tabela `contas` possui a estrutura necessária (`valor`, `data_vencimento`, `status`, `id_aluno`, `tipo: receita/despesa`). 
Não precisaremos criar um Model novo para *Mensalidade*, pois uma mensalidade nada mais é do que um registro em `Conta` com `tipo = receita`, associado a um `id_aluno` e uma `CategoriaConta` específica (ex: "Mensalidade"). O mesmo vale para os "Figurinos".

## Proposed Changes

### 1. Modelagem (Models)
#### [MODIFY] app/Models/Conta.php
- Adicionar no `$fillable` os campos que faltam (ex: `id_aluno`, `id_professor`, `tipo`) caso não estejam presentes.
- Criar os relacionamentos:
  - `public function aluno()`
  - `public function professor()`
  - `public function categoria()`

#### [MODIFY] app/Models/CategoriaConta.php
- Garantir que o fillable esteja correto (`nome`, `tipo`, `descricao`).

### 2. Controladores e Requests (CRUD de Contas)
#### [NEW] app/Http/Controllers/Api/ContaController.php
- Criar o controlador com métodos `index`, `store`, `show`, `update`, `destroy`.
- Implementar as anotações do Swagger para cada rota.
- Filtrar listagens opcionalmente por `id_aluno` ou `status` (pendente, pago, etc).

#### [NEW] app/Http/Requests/Conta/StoreContaRequest.php e UpdateContaRequest.php
- Validar as regras de negócio:
  - `id_categoria` (exists)
  - `valor` (numeric, > 0)
  - `data_vencimento` (date)
  - `status` (in: pendente, pago, vencido, cancelado)
  - `tipo` (in: receita, despesa)
  - `id_aluno` (nullable, exists:alunos)

### 3. Rotas da API
#### [MODIFY] routes/api.php
- Registrar `Route::apiResource('contas', ContaController::class);`
- Registrar `Route::apiResource('categorias-contas', CategoriaContaController::class);` (se necessário para a interface gerenciar categorias).

### 4. Automação: Figurinos -> Contas a Receber
#### [NEW] app/Observers/ApresentacaoAlunoObserver.php
- Criar um *Observer* para escutar eventos na tabela/model `ApresentacaoAluno`.
- Método **`saved`**:
  - Quando um aluno for associado a uma apresentação e um `valor_figurino` for preenchido (> 0), o Observer verificará se já existe uma `Conta` gerada para esse figurino (usando a tabela associativa ou pela descrição/observação).
  - Se não existir Conta e se o `pago_figurino` for *falso*, criamos uma `Conta` do tipo "receita" no valor do figurino, com vencimento padrão (ou calculado), status "pendente", atrelado ao `$aluno->id` e à categoria "Figurino".
  - Se `pago_figurino` = *true*, podemos criar/atualizar a `Conta` direto com o status "pago" e `data_pagamento`.
- Registrar o Observer em `EventServiceProvider` ou `AppServiceProvider`.

### 5. Automação: Mensalidades -> Contas a Receber (Opcional/Planejado)
- O fluxo de mensalidades também poderá observar a matrícula de alunos nas turmas (`MatriculaObserver`) e gerar contas recorrentes de acordo com o valor da turma, data de matrícula e número de meses estipulados no projeto (a definir regra exata de faturamento mês a mês vs faturamento anual com parcelas).

## Verification Plan
### Automated Tests
- Criar `Tests/Feature/Api/ContaControllerTest.php` para garantir o CRUD financeiro.
- Criar testes unitários para o `ApresentacaoAlunoObserver` garantindo que:
  - Salvar `ApresentacaoAluno` com valor X gera uma Conta.
  - Atualizar `ApresentacaoAluno` para `pago_figurino=true` marca a Conta como "paga".

### Manual Verification
- Testar a criação de Categorias de Conta via Insomnia/Postman.
- Ao salvar um Aluno em Apresentação, verificar se a conta financeira é espelhada no banco de dados com a flag *pendente*.
