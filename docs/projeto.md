# Planejamento de Implementação - Backend SaaS Escolas de Artes

Este documento descreve a arquitetura e o plano de implementação para o backend do sistema SaaS para Escolas de Artes, utilizando Laravel 11.

## 1. Visão Geral
- **Framework**: Laravel 11
- **Tipo de Aplicação**: API Restful
- **Documentação**: Swagger (OpenAPI 3.0) via `darkaonline/l5-swagger`
- **Ambiente**: Local (sem Docker, rodando via `php artisan serve`)
- **Banco de Dados**: MySQL (Baseado no schema fornecido)

## 2. Padrões de Projeto e Arquitetura

### 2.1. Value Objects
Serão criados na pasta `app/Domain/ValueObjects` para encapsular a lógica de validação e formatação de dados sensíveis e formatados.
- **CPF**: Validação de formato e algoritmo de dígitos verificadores.
- **Email**: Validação de formato.
- **Telefone / Whatsapp**: Formatação e validação de números.

### 2.2. Design Patterns
A implementação utilizará os padrões solicitados para resolver problemas específicos de domínio:

- **Observer**:
    - *Uso*: `UserObserver`.
    - *Objetivo*: Ao criar um `User`, verificar o `role` e criar automaticamente o registro na tabela correspondente (`alunos`, `professores`, etc). Isso mantém o Controller limpo e garante a integridade dos dados relacionais.

- **Strategy**:
    - *Canais de notificação*: `NotificationStrategyInterface` (Core) implementada por `EmailNotificationStrategy` e `WhatsappNotificationStrategy` (Notificações), escolhidas em tempo de execução pelo `NotificationChannelResolver`. Um novo canal é uma nova classe marcada com a tag `notification.channels`, sem alterar o controller.
    - *Recorrência*: `RecorrenciaStrategyInterface` (Core) com `Diaria`, `Semanal`, `Quinzenal` e `Mensal`, usadas na geração de contas repetidas e de aulas recorrentes.
    - *Filtros de situação de contas*: `SituacaoContaFiltro` (Financeiro) para `a_vencer`, `vencendo_hoje`, `atrasadas` e `pagas`.

- **Adapter**:
    - *Ports & Adapters entre módulos*: interfaces em `Core/Contracts` (ex.: `GerarMensalidadesPort`) implementadas por adapters do módulo dono do dado (ex.: `Financeiro/Adapters/GerarMensalidadesAdapter`).
    - *WhatsApp*: `WhatsappGatewayInterface` (Notificações) implementada por `WhatsappHttpApiAdapter`, que concentra todas as chamadas HTTP à API externa.

- **Proxy**:
    - *Cache de direitos do plano*: `CachedPlanoEntitlementResolver` envolve o `PlanoEntitlementResolver` (mesma interface) e guarda o resultado em cache, invalidado quando a instituição ou o plano mudam.

- **Policies**:
    - Regras de acesso por papel (aluno, responsável, professor) ficam em Policies e em escopos `visivelPara` dos models, em vez de `if ($user->role === ...)` nos controllers. Administradores são liberados via `Gate::before`.

### 2.3. Validação (Form Request)
Todas as operações de escrita (POST, PUT, PATCH) utilizarão classes `FormRequest` dedicadas (`app/Http/Requests`) para validação, garantindo que os Controllers recebam apenas dados válidos.

### 2.4. Documentação (Swagger)
Utilizaremos anotações PHPDoc nos Controllers e Models para gerar a documentação Swagger automaticamente.

## 3. Estrutura do Banco de Dados (Migrations)

O schema fornecido será traduzido em Migrations do Laravel, organizadas em ordem de dependência:

1.  `users` (com enum roles)
2.  `professores`, `alunos`, `responsaveis` (FKs para users)
3.  `medidas_alunos`, `responsaveis_alunos`
4.  `cursos`, `niveis`
5.  `turmas` (FKs para cursos, niveis, professores)
6.  `turma_horarios`, `matriculas`
7.  `aulas_turmas`, `materiais_turmas`
8.  `aulas_presencas`
9.  `leads`, `categorias_contas`, `contas`
10. `espetaculos`, `apresentacoes`, `apresentacao_aluno`

## 4. Plano de Fases

### Fase 1: Setup e Infraestrutura
- Instalar Laravel 11.
- Configurar `.env` e Banco de Dados.
- Instalar `l5-swagger`.
- Criar estrutura de pastas para `Domain` (Value Objects).

### Fase 2: Core Domain & Patterns
- Implementar `ValueObjects` (CPF, Email, Telefone).
- Criar Interfaces para Strategy e Adapter.
- Implementar `UserObserver`.

### Fase 3: Migrations e Models
- Criar todas as migrations baseadas no script SQL.
- Criar Models com relacionamentos (Eloquent).

### Fase 4: API e Controllers
- **Auth**: Login (Sanctum).
- **Users Context**: CRUD de Alunos/Professores com `FormRequest`.
- **Academic Context**: Gestão de Turmas e Matrículas.
- **Financial Context**: Lançamento de Contas.
- **Event Context**: Gestão de Espetáculos e Apresentações.

### Fase 5: Documentação API
- Adicionar anotações Swagger.
- Gerar JSON/UI do Swagger.
