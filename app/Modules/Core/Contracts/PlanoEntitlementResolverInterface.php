<?php

namespace App\Modules\Core\Contracts;

use App\Modules\Core\Models\Instituicao;

/**
 * Direitos efetivos (módulos liberados, limite de alunos e trial) de uma instituição.
 */
interface PlanoEntitlementResolverInterface
{
    /**
     * @return list<string>
     */
    public function catalogKeys(): array;

    /**
     * @return array<string, array{label: string, descricao: string}>
     */
    public function catalog(): array;

    public function labelFor(string $key): ?string;

    /**
     * @return array{modulos: list<string>, limite_alunos: int|null, em_trial: bool}
     */
    public function resolve(Instituicao $instituicao): array;

    /**
     * @return list<string>
     */
    public function modulos(Instituicao $instituicao): array;

    public function limiteAlunos(Instituicao $instituicao): ?int;

    public function permiteModulo(Instituicao $instituicao, string $modulo): bool;

    /**
     * @return list<string>
     */
    public function normalizeModulos(mixed $modulos): array;
}
