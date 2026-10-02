<?php

namespace App\Modules\Core\UseCases\Platform;

use App\Modules\Core\Contracts\InstituicaoRepositoryInterface;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\InstituicaoUsuario;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\PlatformTrialResolver;
use Illuminate\Support\Facades\DB;

class CreatePlatformInstituicaoUseCase
{
    public function __construct(
        private readonly InstituicaoRepositoryInterface $instituicoes,
        private readonly PlatformTrialResolver $trialResolver,
    ) {}

    public function execute(array $data): Instituicao
    {
        $adminData = $this->extractAdminData($data);
        $data = $this->trialResolver->resolveForCreate($data);

        return DB::transaction(function () use ($data, $adminData): Instituicao {
            $instituicao = $this->instituicoes->create($data);

            if ($adminData !== null) {
                $this->createAdminUser($instituicao, $adminData);
            }

            return $instituicao->fresh(['planoAssinatura']);
        });
    }

    /**
     * @return array{nome: string, email: string, password: string, cpf: ?string}|null
     */
    private function extractAdminData(array &$data): ?array
    {
        if (! filled($data['admin_email'] ?? null)) {
            return null;
        }

        $admin = [
            'nome' => $data['admin_nome'],
            'email' => $data['admin_email'],
            'password' => $data['admin_password'],
            'cpf' => $data['admin_cpf'] ?? null,
        ];

        unset(
            $data['admin_nome'],
            $data['admin_email'],
            $data['admin_password'],
            $data['admin_password_confirmation'],
            $data['admin_cpf'],
        );

        return $admin;
    }

    /**
     * @param  array{nome: string, email: string, password: string, cpf: ?string}  $adminData
     */
    private function createAdminUser(Instituicao $instituicao, array $adminData): User
    {
        $user = User::create([
            'nome' => $adminData['nome'],
            'email' => $adminData['email'],
            'senha' => $adminData['password'],
            'cpf' => $adminData['cpf'],
            'role' => 'admin',
        ]);

        InstituicaoUsuario::query()->create([
            'id_instituicao' => $instituicao->id,
            'id_usuario' => $user->id,
            'role' => 'admin',
            'status' => InstituicaoUsuario::STATUS_ATIVO,
        ]);

        return $user;
    }
}
