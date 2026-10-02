<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instituicoes', function (Blueprint $table): void {
            $table->unsignedBigInteger('id_plano_assinatura')->nullable()->after('status');
            $table->dateTime('trial_ends_at')->nullable()->after('id_plano_assinatura');
            $table->boolean('trial_usa_padrao')->default(true)->after('trial_ends_at');
            $table->date('assinatura_inicia_em')->nullable()->after('trial_usa_padrao');
            $table->string('assinatura_status', 20)->default('trialing')->after('assinatura_inicia_em');

            $table->foreign('id_plano_assinatura')->references('id')->on('planos_assinatura');
            $table->index(['assinatura_status', 'trial_ends_at']);
        });

        $defaultTrialDays = (int) config('platform.default_trial_days', 14);

        DB::table('instituicoes')->update([
            'trial_ends_at' => now()->addDays($defaultTrialDays),
            'trial_usa_padrao' => true,
            'assinatura_status' => 'trialing',
        ]);
    }

    public function down(): void
    {
        Schema::table('instituicoes', function (Blueprint $table): void {
            $table->dropForeign(['id_plano_assinatura']);
            $table->dropIndex(['assinatura_status', 'trial_ends_at']);
            $table->dropColumn([
                'id_plano_assinatura',
                'trial_ends_at',
                'trial_usa_padrao',
                'assinatura_inicia_em',
                'assinatura_status',
            ]);
        });
    }
};
