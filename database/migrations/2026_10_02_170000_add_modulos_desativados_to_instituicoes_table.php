<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MODULOS_NOVOS = ['avaliacoes', 'progressao'];

    public function up(): void
    {
        Schema::table('instituicoes', function (Blueprint $table) {
            $table->json('modulos_desativados')->nullable()->after('assinatura_status');
        });

        // Avaliações e progressão já existiam sem cobrança: os planos atuais passam a incluí-los.
        DB::table('planos_assinatura')->orderBy('id')->each(function (object $plano): void {
            $modulos = json_decode((string) $plano->modulos, true);
            $modulos = is_array($modulos) ? $modulos : [];

            DB::table('planos_assinatura')->where('id', $plano->id)->update([
                'modulos' => json_encode(array_values(array_unique([...$modulos, ...self::MODULOS_NOVOS]))),
            ]);
        });
    }

    public function down(): void
    {
        DB::table('planos_assinatura')->orderBy('id')->each(function (object $plano): void {
            $modulos = json_decode((string) $plano->modulos, true);

            if (! is_array($modulos)) {
                return;
            }

            DB::table('planos_assinatura')->where('id', $plano->id)->update([
                'modulos' => json_encode(array_values(array_diff($modulos, self::MODULOS_NOVOS))),
            ]);
        });

        Schema::table('instituicoes', function (Blueprint $table) {
            $table->dropColumn('modulos_desativados');
        });
    }
};
