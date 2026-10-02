<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->unsignedBigInteger('impersonator_user_id')->nullable()->after('id_instituicao');
            $table->foreign('impersonator_user_id')->references('id')->on('usuarios');
            $table->index('impersonator_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->dropForeign(['impersonator_user_id']);
            $table->dropIndex(['impersonator_user_id']);
            $table->dropColumn('impersonator_user_id');
        });
    }
};
