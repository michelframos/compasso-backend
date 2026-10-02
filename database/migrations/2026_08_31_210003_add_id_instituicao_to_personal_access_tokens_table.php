<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->unsignedBigInteger('id_instituicao')->nullable()->after('tokenable_id');
            $table->foreign('id_instituicao')->references('id')->on('instituicoes');
            $table->index('id_instituicao');
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->dropForeign(['id_instituicao']);
            $table->dropColumn('id_instituicao');
        });
    }
};
