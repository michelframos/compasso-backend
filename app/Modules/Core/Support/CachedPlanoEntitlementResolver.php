<?php

namespace App\Modules\Core\Support;

use App\Modules\Core\Contracts\PlanoEntitlementResolverInterface;
use App\Modules\Core\Models\Instituicao;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Carbon;

/**
 * Proxy de cache para o resolver de direitos do plano, consultado em toda requisição
 * pelo middleware EnsurePlanoModulo.
 *
 * A chave inclui o updated_at da instituição, então qualquer alteração nela gera uma
 * chave nova; alterações no plano são invalidadas pelo PlanoAssinaturaObserver.
 */
class CachedPlanoEntitlementResolver implements PlanoEntitlementResolverInterface
{
    public const TTL_SEGUNDOS = 300;

    public function __construct(
        private readonly PlanoEntitlementResolverInterface $resolver,
        private readonly Cache $cache,
    ) {}

    public static function chave(int $instituicaoId, mixed $updatedAt): string
    {
        $versao = $updatedAt ? Carbon::parse($updatedAt)->getTimestamp() : 0;

        return "entitlements:v2:{$instituicaoId}:{$versao}";
    }

    public function resolve(Instituicao $instituicao): array
    {
        if (! $instituicao->exists) {
            return $this->resolver->resolve($instituicao);
        }

        return $this->cache->remember(
            self::chave($instituicao->getKey(), $instituicao->updated_at),
            $this->ttl($instituicao),
            fn () => $this->resolver->resolve($instituicao),
        );
    }

    public function esquecer(Instituicao $instituicao): void
    {
        $this->cache->forget(self::chave($instituicao->getKey(), $instituicao->updated_at));
        $this->cache->forget(self::chave($instituicao->getKey(), $instituicao->getOriginal('updated_at')));
    }

    public function modulos(Instituicao $instituicao): array
    {
        return $this->resolve($instituicao)['modulos'];
    }

    public function modulosContratados(Instituicao $instituicao): array
    {
        return $this->resolve($instituicao)['modulos_contratados'];
    }

    public function limiteAlunos(Instituicao $instituicao): ?int
    {
        return $this->resolve($instituicao)['limite_alunos'];
    }

    public function permiteModulo(Instituicao $instituicao, string $modulo): bool
    {
        return in_array($modulo, $this->modulos($instituicao), true);
    }

    public function moduloContratado(Instituicao $instituicao, string $modulo): bool
    {
        return in_array($modulo, $this->modulosContratados($instituicao), true);
    }

    public function modulosDesativaveis(): array
    {
        return $this->resolver->modulosDesativaveis();
    }

    public function catalogKeys(): array
    {
        return $this->resolver->catalogKeys();
    }

    public function catalog(): array
    {
        return $this->resolver->catalog();
    }

    public function labelFor(string $key): ?string
    {
        return $this->resolver->labelFor($key);
    }

    public function normalizeModulos(mixed $modulos): array
    {
        return $this->resolver->normalizeModulos($modulos);
    }

    /**
     * O trial expira por tempo, sem alterar o registro: o cache não pode sobreviver ao fim dele.
     */
    private function ttl(Instituicao $instituicao): int
    {
        $fimTrial = $instituicao->trial_ends_at;

        if ($instituicao->assinatura_status === Instituicao::ASSINATURA_TRIALING && $fimTrial?->isFuture()) {
            return (int) max(1, min(self::TTL_SEGUNDOS, ceil(now()->diffInSeconds($fimTrial, true))));
        }

        return self::TTL_SEGUNDOS;
    }
}
