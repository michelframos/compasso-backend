<?php

namespace App\Modules\Espetaculos\UseCases\Ensaio;

use App\Modules\Core\Models\User;
use App\Modules\Espetaculos\Models\Apresentacao;
use App\Modules\Espetaculos\Models\Ensaio;
use Illuminate\Support\Facades\DB;

class AgendarEnsaioUseCase
{
    /**
     * @param  array{id_professor?: ?int, data: string, hora_inicio: string, hora_termino: string, local?: ?string, observacoes?: ?string}  $dados
     */
    public function execute(Apresentacao $apresentacao, array $dados, User $autor): Ensaio
    {
        RegrasDoEnsaio::validarData($apresentacao, $dados['data']);

        return DB::transaction(fn () => Ensaio::create([
            ...$dados,
            'id_apresentacao' => $apresentacao->id,
            'id_professor' => $autor->role === 'professor'
                ? $autor->professor?->id
                : ($dados['id_professor'] ?? null),
        ]));
    }
}
