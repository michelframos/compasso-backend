<?php

namespace App\Modules\Espetaculos\UseCases\Ensaio;

use App\Modules\Espetaculos\Models\Ensaio;
use Illuminate\Support\Facades\DB;

class ExcluirEnsaioUseCase
{
    public function execute(Ensaio $ensaio): void
    {
        DB::transaction(fn () => $ensaio->delete());
    }
}
