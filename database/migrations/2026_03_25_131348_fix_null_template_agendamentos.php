<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        \DB::table('configuracoes_notificacoes')
            ->where('modulo', 'agendamentos')
            ->where(function ($query) {
                $query->whereNull('template_mensagem')
                    ->orWhere('template_mensagem', '');
            })
            ->update([
                'template_mensagem' => 'Olá {nome}, confirmamos seu agendamento para a aula de {curso} no dia {data} às {horario}. Até lá!',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Não é necessário reverter, pois o dado nulo era um erro.
    }
};
