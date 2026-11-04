<?php

namespace App\Modules\Academico\Http\Requests\Agenda;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class ListAgendaAlunoRequest extends FormRequest
{
    public const MAXIMO_DIAS = 45;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'inicio' => ['required', 'date_format:Y-m-d'],
            'fim' => ['required', 'date_format:Y-m-d', 'after_or_equal:inicio'],
            'id_aluno' => ['sometimes', 'integer'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $inicio = CarbonImmutable::parse($this->input('inicio'))->startOfDay();
            $fim = CarbonImmutable::parse($this->input('fim'))->startOfDay();

            if ($inicio->diffInDays($fim) > self::MAXIMO_DIAS) {
                $validator->errors()->add('fim', 'O período da agenda pode ter no máximo 45 dias.');
            }
        });
    }
}
