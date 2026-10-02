<?php

namespace App\Modules\Core\Contracts;

/**
 * Integração Academico → Comercial (status do lead).
 * Fase 7: implementação no módulo Comercial.
 */
interface MarcarLeadMatriculadoPort
{
    public function execute(int $leadId): void;
}
