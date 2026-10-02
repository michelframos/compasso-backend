<?php

namespace App\Modules\Core\Contracts;

use App\Modules\Core\Models\Instituicao;

/**
 * Direitos efetivos (módulos liberados, limite de alunos e trial) de uma instituição.
 *
 * Módulo ativo = contratado no plano (ou liberado pelo trial) e não desligado pela escola.
 */
interface PlanoEntitlementResolverInterface
{
    /**
     * @return list<string>
     */
    public function catalogKeys(): array;

    /**
     * @return array<string, array{label: string, descricao: string, desativavel?: bool}>
     */
    public function catalog(): array;

    public function labelFor(string $key): ?string;

    /**
     * Módulos que a própria escola pode ligar ou desligar.
     *
     * @return list<string>
     */
    public function modulosDesativaveis(): array;

    /**
     * @return array{modulos: list<string>, modulos_contratados: list<string>, limite_alunos: int|null, em_trial: bool}
     */
    public function resolve(Instituicao $instituicao): array;

    /**
     * Módulos ativos (contratados e não desligados pela escola).
     *
     * @return list<string>
     */
    public function modulos(Instituicao $instituicao): array;

    /**
     * @return list<string>
     */
    public function modulosContratados(Instituicao $instituicao): array;

    public function limiteAlunos(Instituicao $instituicao): ?int;

    public function permiteModulo(Instituicao $instituicao, string $modulo): bool;

    public function moduloContratado(Instituicao $instituicao, string $modulo): bool;

    /**
     * @return list<string>
     */
    public function normalizeModulos(mixed $modulos): array;
}
