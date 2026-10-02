<?php

use App\Modules\Core\Domain\ValueObjects\Cnpj;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('instituicoes')
            ->whereNotNull('cnpj')
            ->select('id', 'cnpj')
            ->orderBy('id')
            ->each(function (object $instituicao): void {
                DB::table('instituicoes')
                    ->where('id', $instituicao->id)
                    ->update(['cnpj' => Cnpj::normalize($instituicao->cnpj)]);
            });

        $duplicados = DB::table('instituicoes')
            ->whereNotNull('cnpj')
            ->groupBy('cnpj')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('cnpj');

        if ($duplicados->isNotEmpty()) {
            throw new RuntimeException(
                'Existem instituições com o mesmo CNPJ. Corrija antes de migrar: '.$duplicados->implode(', ')
            );
        }

        Schema::table('instituicoes', function (Blueprint $table): void {
            $table->unique('cnpj');
        });
    }

    public function down(): void
    {
        Schema::table('instituicoes', function (Blueprint $table): void {
            $table->dropUnique(['cnpj']);
        });
    }
};
