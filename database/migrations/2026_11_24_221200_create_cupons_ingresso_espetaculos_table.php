<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cupons_ingresso_espetaculos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_cupom_ingresso')->constrained('cupons_ingresso')->cascadeOnDelete();
            $table->foreignId('id_espetaculo')->constrained('espetaculos')->cascadeOnDelete();

            $table->unique(['id_cupom_ingresso', 'id_espetaculo'], 'cupom_ingresso_espetaculo_unique');
        });

        if (Schema::hasColumn('cupons_ingresso', 'id_espetaculo')) {
            $vinculos = DB::table('cupons_ingresso')
                ->whereNotNull('id_espetaculo')
                ->get(['id', 'id_espetaculo']);

            foreach ($vinculos as $cupom) {
                DB::table('cupons_ingresso_espetaculos')->insert([
                    'id_cupom_ingresso' => $cupom->id,
                    'id_espetaculo' => $cupom->id_espetaculo,
                ]);
            }

            Schema::table('cupons_ingresso', function (Blueprint $table) {
                $table->dropForeign(['id_espetaculo']);
                $table->dropIndex(['id_instituicao', 'id_espetaculo']);
                $table->dropColumn('id_espetaculo');
            });
        }
    }

    public function down(): void
    {
        Schema::table('cupons_ingresso', function (Blueprint $table) {
            $table->foreignId('id_espetaculo')->nullable()->after('id_instituicao')->constrained('espetaculos')->nullOnDelete();
            $table->index(['id_instituicao', 'id_espetaculo']);
        });

        $vinculos = DB::table('cupons_ingresso_espetaculos')
            ->orderBy('id')
            ->get(['id_cupom_ingresso', 'id_espetaculo']);

        $jaRestaurado = [];
        foreach ($vinculos as $vinculo) {
            if (isset($jaRestaurado[$vinculo->id_cupom_ingresso])) {
                continue;
            }
            DB::table('cupons_ingresso')
                ->where('id', $vinculo->id_cupom_ingresso)
                ->update(['id_espetaculo' => $vinculo->id_espetaculo]);
            $jaRestaurado[$vinculo->id_cupom_ingresso] = true;
        }

        Schema::dropIfExists('cupons_ingresso_espetaculos');
    }
};
