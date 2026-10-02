<?php

namespace App\Modules\Comercial\Adapters;

use App\Modules\Comercial\Models\Lead;
use App\Modules\Core\Contracts\MarcarLeadMatriculadoPort;

class MarcarLeadMatriculadoAdapter implements MarcarLeadMatriculadoPort
{
    public function execute(int $leadId): void
    {
        Lead::whereKey($leadId)->update(['status' => 'matriculado']);
    }
}
