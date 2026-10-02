<?php

namespace App\Modules\Core\UseCases\Auth;

use App\Modules\Core\Domain\ValueObjects\Cnpj;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\InstituicaoUsuario;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\CadastroEscolaMailer;
use App\Modules\Core\Support\InstituicaoActivationService;
use Illuminate\Support\Facades\Hash;

class ReenviarCodigoAtivacaoUseCase
{
    public const RESULT_ENVIADO = 'enviado';

    public const RESULT_CREDENCIAIS_INVALIDAS = 'credenciais_invalidas';

    public const RESULT_JA_ATIVADA = 'ja_ativada';

    public const RESULT_FALHA_ENVIO = 'falha_envio';

    public function __construct(
        private readonly InstituicaoActivationService $activation,
        private readonly CadastroEscolaMailer $mailer,
    ) {}

    public function execute(string $email, string $password, string $tenantCnpj): string
    {
        $instituicao = Instituicao::query()->where('cnpj', Cnpj::normalize($tenantCnpj))->first();

        if ($instituicao === null) {
            return self::RESULT_CREDENCIAIS_INVALIDAS;
        }

        $user = $this->findLinkedUser($email, $password, $instituicao);

        if ($user === null) {
            return self::RESULT_CREDENCIAIS_INVALIDAS;
        }

        if (! $instituicao->aguardandoAtivacao()) {
            return self::RESULT_JA_ATIVADA;
        }

        $codigo = $this->activation->gerarCodigo($instituicao);

        return $this->mailer->enviar($instituicao, $user, $codigo)
            ? self::RESULT_ENVIADO
            : self::RESULT_FALHA_ENVIO;
    }

    private function findLinkedUser(string $email, string $password, Instituicao $instituicao): ?User
    {
        $vinculados = InstituicaoUsuario::query()
            ->where('id_instituicao', $instituicao->id)
            ->where('status', InstituicaoUsuario::STATUS_ATIVO)
            ->pluck('id_usuario');

        return User::query()
            ->where('email', $email)
            ->whereIn('id', $vinculados)
            ->get()
            ->first(fn (User $user): bool => Hash::check($password, $user->senha));
    }
}
