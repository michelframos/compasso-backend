<?php

namespace App\Modules\Relatorios\UseCases;

use App\Modules\Core\Contracts\GerarPagamentoProfessorPort;
use App\Modules\Relatorios\Models\FechamentoProfessor;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Desfaz o fechamento (e a despesa ainda não paga), voltando ao cálculo ao vivo. */
class ReabrirMesProfessorUseCase
{
    public function __construct(private readonly GerarPagamentoProfessorPort $pagamentos)
    {
    }

    public function execute(FechamentoProfessor $fechamento): void
    {
        DB::transaction(function () use ($fechamento): void {
            if ($fechamento->id_conta && ! $this->pagamentos->cancelar($fechamento->id_conta)) {
                throw ValidationException::withMessages([
                    'fechamento' => 'A despesa deste fechamento já tem pagamentos registrados. Estorne-os no Financeiro antes de reabrir o mês.',
                ]);
            }

            $fechamento->delete();
        });
    }
}
