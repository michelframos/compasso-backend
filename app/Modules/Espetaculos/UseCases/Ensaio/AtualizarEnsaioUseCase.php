<?php

namespace App\Modules\Espetaculos\UseCases\Ensaio;

use App\Modules\Core\Models\User;
use App\Modules\Espetaculos\Models\Ensaio;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AtualizarEnsaioUseCase
{
    /**
     * @param  array{id_professor?: ?int, data: string, hora_inicio: string, hora_termino: string, local?: ?string, observacoes?: ?string}  $dados
     */
    public function execute(Ensaio $ensaio, array $dados, User $autor): Ensaio
    {
        if ($dados['data'] !== $ensaio->data->format('Y-m-d') && $dados['data'] < now()->toDateString()) {
            throw ValidationException::withMessages(['data' => 'O ensaio não pode ser remarcado para o passado.']);
        }

        RegrasDoEnsaio::validarData($ensaio->apresentacao, $dados['data']);

        if ($autor->role === 'professor') {
            unset($dados['id_professor']);
        }

        return DB::transaction(function () use ($ensaio, $dados): Ensaio {
            $ensaio->update($dados);

            return $ensaio;
        });
    }
}
