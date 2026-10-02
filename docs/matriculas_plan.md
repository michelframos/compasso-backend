# Documentação do Módulo de Matrículas

Este documento descreve as funcionalidades, as regras de negócios e os endpoints referentes à gestão de **Matrículas** no sistema Camerata.

---

## 1. Visão Geral

O módulo de Matrículas faz a integração (relacionamento) entre um Aluno (`alunos`) e uma Turma (`turmas`). Através desse vínculo, o aluno é considerado apto para participar das aulas e ter suas presenças registradas.

### Ativo de Banco de Dados (Soft Deletes)
A tabela `matriculas` utiliza exclusão lógica (**Soft Delete**). Isso significa que, ao invés do registro ser efetivamente removido do banco de dados na exclusão de uma matrícula, a coluna `deleted_at` recebe a data de remoção, mantendo o histórico de vínculo, se for necessário preservá-lo futuramente.

---

## 2. Regras de Negócio Implementadas na API

Para garantir a integridade da gestão pedagógica, na **Criação (Store)** e na **Edição (Update)** da Turma Vinculada de uma Matrícula as seguintes regras são rigorosamente validadas pelo backend:

1. **Restrição por Status da Turma:**
   Não é possível criar ou transferir uma matrícula para uma turma se o status atual dessa turma for `concluida` ou `cancelada`. Nestes casos, a API responderá com um erro de validação informando o usuário de que essa atividade expirou.

2. **Limite Capacidade Máxima da Turma (`maximo_alunos`):**
   Ao vincular o Aluno à Turma, o sistema verifica a contagem exata de matrículas ativas presentes no momento para a turma solicitada. Caso a contagem seja *igual ou superior* ao atributo de lotação definido na entidade Turma (`maximo_alunos`), o envio será impedido e uma falha de validação será retornada.

---

## 3. Endpoints (Rotas da API)

Todas as requisições estão protegidas pelo **Laravel Sanctum**. É obrigatório enviar o Header HTTP `Authorization: Bearer {token}` para todos os endpoints listados.

| Método | Rota | Descrição |
|--------|------|-------------|
| **GET** | `/api/matriculas` | Retorna a listagem de todas as matrículas ativas e carrega as entidades de relacionamento de `aluno` e `turma`. |
| **GET** | `/api/alunos/{id_aluno}/matriculas` | Retorna a listagem de todas as matrículas de um aluno específico. |
| **GET** | `/api/turmas/{id_turma}/matriculas` | Retorna a listagem de todas as matrículas de uma turma específica. |
| **POST** | `/api/matriculas` | Cria uma nova matrícula validando as regras de limite e status. |
| **GET** | `/api/matriculas/{id}` | Retorna as informações completas de uma única matrícula específica e suas entidades correlatas. |
| **PUT** | `/api/matriculas/{id}` | Atualiza uma ou mais propriedades (campos opcionais) da matrícula e refaz as validações caso o ID da Turma seja comutado. |
| **DELETE** | `/api/matriculas/{id}` | Desabilita logicamente uma matrícula definida utilizando a política de "soft deletes". Retorna Status Code *204 No Content* em caso de sucesso. |

---

## 4. Estrutura de Envio (Payloads JSON)

Abaixo seguem exemplos de como devem ser formatados os dados enviados para a API nos métodos POST e PUT.

### 4.1. Payload de Criação (POST)

```json
{
  "id_aluno": 1,
  "id_turma": 3,
  "data": "2023-11-05",
  "status": "ativa",
  "observacoes": "Matriculou-se no início de novembro."
}
```
**Campos Obrigatórios:** `id_aluno`, `id_turma` e `data`.

### 4.2. Payload de Atualização (PUT)

```json
{
  "status": "inativa",
  "observacoes": "Aluno transferiu moradia."
}
```
Todos os campos são **opcionais** (`sometimes`). Apenas os campos enviados pelo cliente web ou aplicativo mobile serão sobrepostos com o novo valor dentro do banco.

---

## 5. Swagger
Todos os schemas de rotas com tipos explícitos para request/response JSON se encontram auto-documentados e disponíveis no painel OpenAPI/Swagger para exploração e testes visuais pelo front-end da nossa aplicação.
