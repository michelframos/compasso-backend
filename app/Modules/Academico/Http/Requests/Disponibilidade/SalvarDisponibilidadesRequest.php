<?php

namespace App\Modules\Academico\Http\Requests\Disponibilidade;

use App\Modules\Academico\Models\DisponibilidadeProfessor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'SalvarDisponibilidadesRequest',
    required: ['disponibilidades'],
    properties: [
        new OA\Property(
            property: 'disponibilidades',
            type: 'array',
            description: 'Substitui todas as janelas do professor. Lista vazia remove a disponibilidade.',
            items: new OA\Items(properties: [
                new OA\Property(property: 'dia_semana', type: 'string', enum: ['segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado', 'domingo']),
                new OA\Property(property: 'hora_inicio', type: 'string', example: '08:00'),
                new OA\Property(property: 'hora_termino', type: 'string', example: '12:00'),
            ])
        ),
    ]
)]
class SalvarDisponibilidadesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'disponibilidades' => 'present|array|max:50',
            'disponibilidades.*.dia_semana' => ['required', Rule::in(DisponibilidadeProfessor::DIAS_SEMANA)],
            'disponibilidades.*.hora_inicio' => 'required|date_format:H:i',
            'disponibilidades.*.hora_termino' => 'required|date_format:H:i|after:disponibilidades.*.hora_inicio',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $porDia = collect($this->input('disponibilidades', []))
                ->map(fn (array $janela, int $indice) => $janela + ['indice' => $indice])
                ->groupBy('dia_semana');

            foreach ($porDia as $janelas) {
                $ordenadas = $janelas->sortBy('hora_inicio')->values();

                for ($i = 1; $i < $ordenadas->count(); $i++) {
                    if ($ordenadas[$i]['hora_inicio'] < $ordenadas[$i - 1]['hora_termino']) {
                        $validator->errors()->add(
                            "disponibilidades.{$ordenadas[$i]['indice']}.hora_inicio",
                            'Há janelas sobrepostas no mesmo dia.'
                        );
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'disponibilidades.*.hora_termino.after' => 'O término deve ser depois do início.',
        ];
    }
}
