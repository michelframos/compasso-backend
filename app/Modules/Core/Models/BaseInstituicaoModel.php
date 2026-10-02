<?php

namespace App\Modules\Core\Models;

use App\Modules\Core\Models\Concerns\PertenceAInstituicao;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Base para models gerados pelo MakeModule em ambiente multi-tenant.
 */
abstract class BaseInstituicaoModel extends Model
{
    use HasFactory, PertenceAInstituicao, SoftDeletes;
}
