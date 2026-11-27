<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instituicoes', function (Blueprint $table): void {
            $table->string('gateway_subaccount_uuid', 64)->nullable();
            $table->string('gateway_subaccount_status', 20)->default('none');
            $table->text('gateway_subaccount_last_error')->nullable();
            // Default (["pix","boleto"]) resolvido no model Instituicao.
            $table->json('meios_cobranca_online')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('instituicoes', function (Blueprint $table): void {
            $table->dropColumn([
                'gateway_subaccount_uuid',
                'gateway_subaccount_status',
                'gateway_subaccount_last_error',
                'meios_cobranca_online',
            ]);
        });
    }
};
