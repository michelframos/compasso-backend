<?php

use App\Modules\Core\Support\InstituicaoDataMigrator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        (new InstituicaoDataMigrator)->migrate();

        if (Schema::hasTable('configuracoes_empresa')) {
            DB::statement("ALTER TABLE `usuarios` MODIFY `role` ENUM('admin','professor','aluno','responsavel','secretaria')
                NOT NULL COMMENT 'Deprecated: use instituicoes_usuarios.role'");

            Schema::drop('configuracoes_empresa');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('configuracoes_empresa')) {
            Schema::create('configuracoes_empresa', function (Blueprint $table): void {
                $table->id();
                $table->string('nome_fantasia');
                $table->string('razao_social')->nullable();
                $table->string('cnpj', 20)->nullable();
                $table->string('rua')->nullable();
                $table->string('numero', 10)->nullable();
                $table->string('bairro')->nullable();
                $table->string('complemento')->nullable();
                $table->string('cep', 10)->nullable();
                $table->unsignedBigInteger('id_estado')->nullable();
                $table->unsignedBigInteger('id_cidade')->nullable();
                $table->timestamps();

                $table->foreign('id_estado')->references('id')->on('estados');
                $table->foreign('id_cidade')->references('id')->on('cidades');
            });

            $instituicao = DB::table('instituicoes')->where('slug', 'default')->first();

            if ($instituicao !== null) {
                DB::table('configuracoes_empresa')->insert([
                    'nome_fantasia' => $instituicao->nome_fantasia,
                    'razao_social' => $instituicao->razao_social,
                    'cnpj' => $instituicao->cnpj,
                    'rua' => $instituicao->rua,
                    'numero' => $instituicao->numero,
                    'bairro' => $instituicao->bairro,
                    'complemento' => $instituicao->complemento,
                    'cep' => $instituicao->cep,
                    'id_estado' => $instituicao->id_estado,
                    'id_cidade' => $instituicao->id_cidade,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        DB::statement("ALTER TABLE `usuarios` MODIFY `role` ENUM('admin','professor','aluno','responsavel','secretaria') NOT NULL");
    }
};
