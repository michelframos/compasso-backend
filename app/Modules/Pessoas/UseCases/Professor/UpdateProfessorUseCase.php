<?php

namespace App\Modules\Pessoas\UseCases\Professor;

use App\Modules\Core\Contracts\UserRepositoryInterface;
use App\Modules\Pessoas\Models\Professor;
use Illuminate\Support\Facades\DB;

class UpdateProfessorUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    public function execute(Professor $professor, array $data): Professor
    {
        return DB::transaction(function () use ($professor, $data) {
            $userData = array_filter([
                'nome' => $data['nome'] ?? null,
                'email' => $data['email'] ?? null,
                'cpf' => $data['cpf'] ?? null,
                'telefone' => $data['telefone'] ?? null,
                'whatsapp' => $data['whatsapp'] ?? null,
            ], fn ($v) => $v !== null);

            if (! empty($data['password'])) {
                $userData['password'] = $data['password'];
            }

            $this->users->update($professor->usuario, $userData);

            $professor->update(array_filter([
                'comissao' => $data['comissao'] ?? null,
                'salario_fixo' => $data['salario_fixo'] ?? null,
                'valor_hora_aula' => $data['valor_hora_aula'] ?? null,
                'observacoes' => $data['observacoes'] ?? null,
            ], fn ($v) => $v !== null || array_key_exists('observacoes', $data)));

            return $professor->fresh()->load('usuario');
        });
    }
}
