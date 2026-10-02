<?php

namespace Database\Seeders;

use App\Modules\Core\Models\PlanoAssinatura;
use Illuminate\Database\Seeder;

class PlanoAssinaturaSeeder extends Seeder
{
    public function run(): void
    {
        $todos = array_keys(config('platform.modulos_app', []));

        $planos = [
            [
                'slug' => 'basico',
                'nome' => 'Básico',
                'descricao' => 'Ideal para escolas em início de operação.',
                'preco_mensal' => 99.90,
                'limite_alunos' => 80,
                'modulos' => ['leads', 'avaliacoes', 'progressao'],
                'ativo' => true,
            ],
            [
                'slug' => 'profissional',
                'nome' => 'Profissional',
                'descricao' => 'Recursos completos para escolas em crescimento.',
                'preco_mensal' => 199.90,
                'limite_alunos' => 250,
                'modulos' => ['leads', 'financeiro', 'instrumentos', 'avaliacoes', 'progressao'],
                'ativo' => true,
            ],
            [
                'slug' => 'enterprise',
                'nome' => 'Enterprise',
                'descricao' => 'Sem limite de alunos e suporte prioritário.',
                'preco_mensal' => 399.90,
                'limite_alunos' => null,
                'modulos' => $todos,
                'ativo' => true,
            ],
        ];

        foreach ($planos as $plano) {
            PlanoAssinatura::query()->updateOrCreate(
                ['slug' => $plano['slug']],
                $plano,
            );
        }
    }
}
