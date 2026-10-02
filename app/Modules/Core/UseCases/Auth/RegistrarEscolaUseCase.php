<?php

namespace App\Modules\Core\UseCases\Auth;

use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\CadastroEscolaMailer;
use App\Modules\Core\Support\InstituicaoActivationService;
use App\Modules\Core\UseCases\Platform\CreatePlatformInstituicaoUseCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cadastro de escola pela tela de login do app: inicia na gratuidade padrão e
 * fica pendente de ativação até o primeiro login com o código enviado por e-mail.
 */
class RegistrarEscolaUseCase
{
    public function __construct(
        private readonly CreatePlatformInstituicaoUseCase $createInstituicao,
        private readonly InstituicaoActivationService $activation,
        private readonly CadastroEscolaMailer $mailer,
    ) {}

    /**
     * @param  array{nome_fantasia: string, cnpj: string, responsavel_nome: string, email: string, password: string}  $data
     * @return array{instituicao: Instituicao, user: User, email_enviado: bool}
     */
    public function execute(array $data): array
    {
        [$instituicao, $user, $codigo] = DB::transaction(function () use ($data): array {
            $instituicao = $this->createInstituicao->execute([
                'slug' => $this->gerarSlugUnico($data['nome_fantasia']),
                'nome_fantasia' => $data['nome_fantasia'],
                'cnpj' => $data['cnpj'],
                'usar_trial_padrao' => true,
                'admin_nome' => $data['responsavel_nome'],
                'admin_email' => $data['email'],
                'admin_password' => $data['password'],
            ]);

            $user = $instituicao->usuarios()->where('email', $data['email'])->firstOrFail();
            $codigo = $this->activation->gerarCodigo($instituicao);

            return [$instituicao, $user, $codigo];
        });

        $emailEnviado = $this->mailer->enviar($instituicao, $user, $codigo);

        return [
            'instituicao' => $instituicao,
            'user' => $user,
            'email_enviado' => $emailEnviado,
        ];
    }

    private function gerarSlugUnico(string $nomeFantasia): string
    {
        $base = Str::slug($nomeFantasia);
        $base = $base !== '' ? Str::limit($base, 56, '') : 'escola';

        $slug = $base;
        $sufixo = 2;

        while (Instituicao::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$sufixo++;
        }

        return $slug;
    }
}
