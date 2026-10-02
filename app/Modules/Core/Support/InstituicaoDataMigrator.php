<?php

namespace App\Modules\Core\Support;

use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\InstituicaoUsuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InstituicaoDataMigrator
{
    /**
     * @return array{instituicao_id: int, usuarios_sincronizados: int, dados_empresa_migrados: bool}
     */
    public function migrate(): array
    {
        $dadosEmpresaMigrados = false;

        $instituicao = Instituicao::withTrashed()->where('slug', 'default')->first();

        if ($instituicao === null) {
            $instituicao = Instituicao::create($this->dadosInstituicaoDefault());
            $dadosEmpresaMigrados = true;
        } elseif (Schema::hasTable('configuracoes_empresa')) {
            $config = DB::table('configuracoes_empresa')->first();

            if ($config !== null) {
                $instituicao->update($this->mapearConfiguracaoEmpresa((array) $config));
                $dadosEmpresaMigrados = true;
            }
        }

        $usuariosSincronizados = $this->sincronizarUsuarios($instituicao);

        return [
            'instituicao_id' => $instituicao->id,
            'usuarios_sincronizados' => $usuariosSincronizados,
            'dados_empresa_migrados' => $dadosEmpresaMigrados,
        ];
    }

    private function sincronizarUsuarios(Instituicao $instituicao): int
    {
        $sincronizados = 0;

        $usuarios = DB::table('usuarios')->select('id', 'role')->get();

        foreach ($usuarios as $usuario) {
            InstituicaoUsuario::query()->firstOrCreate(
                [
                    'id_instituicao' => $instituicao->id,
                    'id_usuario' => $usuario->id,
                ],
                [
                    'role' => $usuario->role,
                    'status' => InstituicaoUsuario::STATUS_ATIVO,
                ]
            );

            $sincronizados++;
        }

        return $sincronizados;
    }

    /**
     * @return array<string, mixed>
     */
    private function dadosInstituicaoDefault(): array
    {
        if (Schema::hasTable('configuracoes_empresa')) {
            $config = DB::table('configuracoes_empresa')->first();

            if ($config !== null) {
                return array_merge(
                    ['slug' => 'default', 'status' => Instituicao::STATUS_ATIVO],
                    $this->mapearConfiguracaoEmpresa((array) $config)
                );
            }
        }

        return [
            'slug' => 'default',
            'nome_fantasia' => 'Instituição Padrão',
            'status' => Instituicao::STATUS_ATIVO,
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function mapearConfiguracaoEmpresa(array $config): array
    {
        return array_filter([
            'nome_fantasia' => $config['nome_fantasia'] ?? null,
            'razao_social' => $config['razao_social'] ?? null,
            'cnpj' => $config['cnpj'] ?? null,
            'rua' => $config['rua'] ?? null,
            'numero' => $config['numero'] ?? null,
            'bairro' => $config['bairro'] ?? null,
            'complemento' => $config['complemento'] ?? null,
            'cep' => $config['cep'] ?? null,
            'id_estado' => $config['id_estado'] ?? null,
            'id_cidade' => $config['id_cidade'] ?? null,
        ], fn ($value) => $value !== null);
    }
}
