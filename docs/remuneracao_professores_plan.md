# Plano de Implementação: Sistema de Remuneração Flexível de Professores

O objetivo é permitir que um professor da Camerata possa ser remunerado de múltiplas formas simultaneamente ou isoladas:
1. **Salário Fixo:** Um valor fixo mensal independente de aulas/turmas.
2. **Valor por Hora/Aula:** Um valor multiplicador pela quantidade de horas ou aulas ministradas no período.
3. **Comissão Percentual:** Um percentual sobre o valor financeiro arrecadado por uma turma específica (requer a definição do valor da turma/curso).

## Arquitetura Proposta (Banco de Dados)

### 1. Atualizar a tabela `professores`
Adicionaremos novos campos financeiros ao perfil do professor para abrigar a flexibilidade.
* **`valor_hora_aula`** (`decimal(10,2)`): O valor financeiro pago ao professor por cada hora (ou aula) ministrada.
* **`salario_fixo`** (`decimal(10,2)`): O valor de salário base/mensal do professor.
* *Nota: O campo `comissao` já existe na tabela atual e será mantido para representar o percentual global padrão dele, caso não seja sobrescrito pela turma.*

### 2. Atualizar a tabela `turmas` (ou `cursos`)
Para que o cálculo de "Comissão sobre valor arrecadado" e a flexibilidade funcionem por turma:
* **`valor_mensalidade`** (`decimal(10,2)`): O preço padrão cobrado do aluno por esta turma mensalmente.
* **`percentual_comissao_especifico`** (`decimal(5,2)`): (Opcional) Permite definir uma taxa de comissão *específica* para esta turma, sobrescrevendo a comissão padrão do professor.
* **`valor_hora_aula_especifico`** (`decimal(10,2)`): (Opcional) Permite definir um valor por hora/aula *específico* para esta turma, sobrescrevendo o valor hora/aula padrão do professor.

### 3. Modelo Contábil Futuro
Durante o fechamento do mês (módulo de relatórios/pagamentos que desenvolveremos futuramente), o cálculo do "Holerite" do professor será a soma de:
```
Remuneração = (Salário Fixo) + 
              (Valor Hora Aula * Total de Horas/Aulas Concluídas) + 
              (Valor Total Arrecadado das Turmas * % Comissão)
```

## User Review Required

> [!IMPORTANT]
> **Sobre o "Valor Arrecadado":** Para simplificar nesta primeira etapa, sugiro colocarmos o `valor_mensalidade` diretamente na tabela `turmas`. Assim, sabemos quanto cada aluno paga por aquela turma específica.
> Se futuramente a escola tiver "Planos de 6 meses com desconto" e "Planos Mensais", precisaremos de uma tabela específica de `Planos` e amarrá-la na `Matrícula` do aluno.
> **Podemos começar adicionando os 2 campos no Professor (`valor_hora_aula`, `salario_fixo`) e 1 na turma (`valor_mensalidade`)?**

## Proposed Changes

### Database Migrations

#### [NEW] 2026_x_x_add_remuneration_fields_to_professores_table.php
```php
Schema::table('professores', function (Blueprint $table) {
    $table->decimal('salario_fixo', 10, 2)->nullable()->default(0)->after('id_usuario');
    $table->decimal('valor_hora_aula', 10, 2)->nullable()->default(0)->after('salario_fixo');
    // 'comissao' já existe.
});
```

#### [NEW] 2026_x_x_add_financial_fields_to_turmas_table.php
```php
Schema::table('turmas', function (Blueprint $table) {
    $table->decimal('valor_mensalidade', 10, 2)->nullable()->default(0)->after('status');
    $table->decimal('percentual_comissao_especifico', 5, 2)->nullable()->after('valor_mensalidade'); // Sobrescreve a do professor se não for null
    $table->decimal('valor_hora_aula_especifico', 10, 2)->nullable()->after('percentual_comissao_especifico'); // Sobrescreve a do professor se não for null
});
```

### Models & Requests

#### [MODIFY] app/Models/Professor.php
- Adicionar `salario_fixo` e `valor_hora_aula` ao `$fillable` e documentar com Swagger (OpenAPI Attributes).

#### [MODIFY] app/Http/Requests/Professor/StoreProfessorRequest.php e UpdateProfessorRequest.php
- Adicionar os novos campos nas regras de validação (numeric, min:0).
- Adicionar anotações Swagger.

#### [MODIFY] app/Models/Turma.php
- Adicionar `valor_mensalidade` e `percentual_comissao_especifico` ao `$fillable`.

#### [MODIFY] app/Http/Requests/Turma/StoreTurmaRequest.php e UpdateTurmaRequest.php
- Adicionar validações para as novas colunas e preencher Swagger.

## Verification Plan
1. Executar as migrations.
2. Atualizar todos os testes de repositório e feature (Controllers) para enviar os novos campos numéricos de salário, hora/aula e mensalidade.
3. Verificar a documentação do Swagger para confirmar que refletem os novos atributos opcionais flexíveis.
# Plano de Implementação: Regras de Cálculo da Folha de Pagamento (Holerite)

Este documento detalha o algoritmo e a hierarquia (Fallback) que o sistema utilizará para gerar os contracheques mensais dos professores, garantindo que o valor correto seja aplicado dependendo do contrato estabelecido (Fixo, Hora/Aula, Comissão ou Misto).

## 1. Regra de Hierarquia (Fallback)

Para garantir flexibilidade sem complexidade para o usuário, o sistema adotará a hierarquia "**Do mais específico para o mais genérico**".

### 1.1 Hierarquia de Comissão (%)
Quando o sistema for calcular a comissão de uma turma específica:
1. **Regra Específica (Turma):** O sistema verifica primeiro se a turma possui `percentual_comissao_especifico` preenchido (maior que zero). Se sim, usa-se este valor.
2. **Regra Padrão (Professor):** Se a turma não tiver exceção, o sistema busca a `comissao` (percentual padrão) diretamente no cadastro do professor.
3. Se nenhum dos dois for encontrado, a comissão calculada é zero.

### 1.2 Hierarquia de Hora/Aula ($)
Quando o sistema for calcular o pagamento por aulas dadas numa turma:
1. **Regra Específica (Turma):** Verifica se a turma possui `valor_hora_aula_especifico`. Se sim, usa este valor.
2. **Regra Padrão (Professor):** Se a turma for nula nesse campo, o sistema aplica o `valor_hora_aula` cadastrado no perfil global do professor.
3. Se nenhum dos dois for encontrado, o pagamento por hora/aula é zero.

### 1.3 Regra de Exclusividade (Conflitos)
Como o sistema permite que as colunas de "Comissão" e "Hora/Aula" coexistam, a definição principal de negócio proposta é a **Soma Integral**:
Se um professor possuir **ambos** os valores válidos (ex: ganha uma hora/aula fixa + 10% do que arrecadar), **ambos os valores são somados no holerite final**.

> [!TIP]
> **Como evitar dupla cobrança?**
> A gestão da escola deve preencher apenas o modelo desejado. Se o professor de violão for remunerado APENAS por hora, sua `comissao` deve ser 0 no cadastro.

---

## 2. Algoritmo de Fechamento Base (Pseudo-código do Módulo)

Quando a rota de geração do holerite (ex: `POST /api/relatorios/holerite/{mes}`) for chamada, o cálculo matemático obedecerá à seguinte pipeline:

### Passo A: Base Fixa
```php
$holeriteTotal = $professor->salario_fixo; // Já começa com a base garantida (que pode ser zero)
```

### Passo B: Loop pelas Aulas Concluídas
O sistema buscará todas as `aulas_turmas` onde `id_professor` = X, `status` = 'concluida' e a `data` esteja dentro do Mês apurado.

```php
foreach ($aulasConcluidas as $aula) {
    $turma = $aula->turma;
    $duracaoHoras = calcularDiferencaHoras($aula->hora_inicio, $aula->hora_termino); // Ex: 1.5 horas

    // --- CÁLCULO DA HORA/AULA ---
    $valorHoraAula = $turma->valor_hora_aula_especifico ?? $professor->valor_hora_aula;
    $pagamentoHoraAula = $duracaoHoras * $valorHoraAula;
    
    // Incrementa no holerite
    $holeriteTotal += $pagamentoHoraAula;
}
```

### Passo C: Cálculo da Comissão (Mensalidades Arrecadadas)
O percentual recai sobre o "Valor da Turma". *Nota: Na implementação futura onde existirão matrículas gerando boletos (Contas a Receber), a métrica perfeita é a conta PAGA pelo aluno.*

```php
// Simplificação considerando o valor tabelado da turma
$turmasAtivasMes = $professor->turmas()->where('status', 'em_andamento')->get();

foreach ($turmasAtivasMes as $turma) {
    // Busca a taxa
    $percentualComissao = $turma->percentual_comissao_especifico ?? $professor->comissao;
    
    // Faturamento bruto projetado da Turma = (Qtd de Alunos Matriculados Ativos) * ($turma->valor_mensalidade)
    $faturamentoTurma = $turma->alunos->count() * $turma->valor_mensalidade;
    
    // Cálculo do Professor
    $pagamentoComissao = $faturamentoTurma * ($percentualComissao / 100);
    
    // Incrementa no holerite
    $holeriteTotal += $pagamentoComissao;
}
```

## User Review Required

> [!NOTE]
> Este documento representa o futuro **Módulo Financeiro de Fechamento de Professores**. Confirme se o Pseudo-código expressa corretamente a forma atual em que o RH/Financeiro da Camerata realiza (ou deseja realizar) o acerto de contas com o corpo docente ao final de cada mês.
