# Seeds de demonstração — Compasso

## Como rodar

Na pasta `backend`:

```bash
# Banco limpo + seeds (recomendado na primeira vez)
php artisan migrate:fresh --seed

# Ou só as seeds (se o schema já existir)
php artisan db:seed
```

Para recriar o domínio das escolas demo (mantém users/instituições):

```bash
# Linux/macOS
DEMO_SEED_FORCE=1 php artisan db:seed --class=DemoTenantSeeder

# Windows PowerShell
$env:DEMO_SEED_FORCE='1'; php artisan db:seed --class=DemoTenantSeeder
```

## Credenciais

### Escolas demo

Senha: **`password`**

| Email | Tenant (`X-Tenant-Slug` / login) | Uso |
|-------|----------------------------------|-----|
| `super@compasso.local` | — (painel platform) | Super admin |
| `admin@trial.demo` | `escola-trial` | Trial com **todos** os módulos |
| `admin@basico.demo` | `escola-basico` | Plano Básico — só **leads** (testar UI oculta) |
| `admin@pro.demo` | `escola-pro` | Profissional — leads + financeiro + instrumentos |
| `admin@enterprise.demo` | `escola-enterprise` | Enterprise — tudo ativo |

Também existem usuários `secretaria@…` para cada escola (mesma senha).

### Escola Michel (`EscolaMichelSeeder`)

Senha: **`Redo@2018`**  
CNPJ de login: **`26.366.000/0001-01`** (slug: `escola-michel`)

| Email | Role |
|-------|------|
| `michelframos@hotmail.com` | admin |
| `professor1@email.com` | professor (Violão Intermediário) |
| `professor2@email.com` | professor (Ballet Iniciante) |
| `aluno1@email.com` … `aluno3@email.com` | alunos na turma de Violão |
| `aluno4@email.com` | aluno na turma de Ballet |

Cursos: **Violão** e **Ballet**, cada um com níveis Infantil (4–11), Iniciante (12–15), Básico (16–18), Intermediário (19–21) e Avançado (22–30).

## O que cada escola recebe

- **Core:** cursos, níveis, professores, alunos, turmas, horários, matrículas, aulas, presenças
- **Leads:** funil (novo / contatado / matriculado / perdido)
- **Financeiro** (pro/enterprise/trial): categorias, contas (pago/pendente/vencido), contrato, PIX
- **Instrumentos** (pro/enterprise/trial): inventário + empréstimos
- **Espetáculos** (enterprise/trial): espetáculo + apresentação + elenco
- **WhatsApp / notificações:** configs base (contas_a_receber só com financeiro)

## Planos (`PlanoAssinaturaSeeder`)

| Slug | Módulos | Limite alunos |
|------|---------|---------------|
| `basico` | leads | 80 |
| `profissional` | leads, financeiro, instrumentos | 250 |
| `enterprise` | todos | ilimitado |
