<?php

namespace Tests\Concerns;

use App\Models\User;
use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\InstituicaoUsuario;
use App\Modules\Core\Support\InstituicaoContext;

trait InteractsWithInstituicao
{
    protected function tearDown(): void
    {
        InstituicaoContext::clear();

        parent::tearDown();
    }

    protected function instituicaoDefault(): Instituicao
    {
        return Instituicao::where('slug', 'default')->firstOrFail();
    }

    protected function criarUsuarioNaInstituicao(Instituicao $instituicao, string $role, array $atributos = []): User
    {
        $user = User::factory()->create(['role' => $role] + $atributos);

        InstituicaoUsuario::create([
            'id_instituicao' => $instituicao->id,
            'id_usuario' => $user->id,
            'role' => $role,
            'status' => InstituicaoUsuario::STATUS_ATIVO,
        ]);

        return $user;
    }

    protected function comoUsuario(User $user, Instituicao $instituicao): static
    {
        $this->app['auth']->forgetGuards();

        $token = $user->createToken('test');
        $token->accessToken->update(['id_instituicao' => $instituicao->id]);

        return $this->withToken($token->plainTextToken)
            ->withHeader('X-Tenant-Slug', $instituicao->slug);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function naInstituicao(Instituicao $instituicao, callable $callback): mixed
    {
        InstituicaoContext::setFromModel($instituicao);

        try {
            return $callback();
        } finally {
            InstituicaoContext::clear();
        }
    }
}
