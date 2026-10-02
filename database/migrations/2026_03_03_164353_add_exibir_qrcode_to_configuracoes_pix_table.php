<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracoes_pix', function (Blueprint $table) {
            $table->boolean('exibir_qrcode')->default(true)->after('cidade');
        });
    }

    public function down(): void
    {
        Schema::table('configuracoes_pix', function (Blueprint $table) {
            $table->dropColumn('exibir_qrcode');
        });
    }
};
