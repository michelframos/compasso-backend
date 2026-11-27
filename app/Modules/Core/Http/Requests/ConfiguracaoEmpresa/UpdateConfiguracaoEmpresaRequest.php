<?php

namespace App\Modules\Core\Http\Requests\ConfiguracaoEmpresa;

use App\Modules\Core\Domain\ValueObjects\Cnpj;
use App\Modules\Core\Support\InstituicaoContext;
use App\Rules\CnpjRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConfiguracaoEmpresaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('cnpj')) {
            $this->merge(['cnpj' => Cnpj::normalize($this->input('cnpj'))]);
        }

        if ($this->has('whatsapp')) {
            $digits = preg_replace('/\D+/', '', (string) $this->input('whatsapp'));
            $this->merge(['whatsapp' => $digits !== '' ? $digits : null]);
        }

        if ($this->has('email_contato') && $this->input('email_contato') === '') {
            $this->merge(['email_contato' => null]);
        }

        foreach (['limite_reposicoes_periodo', 'prazo_reposicao_dias'] as $campo) {
            if ($this->has($campo) && $this->input($campo) === '') {
                $this->merge([$campo => null]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'nome_fantasia' => 'required|string|max:255',
            'razao_social' => 'nullable|string|max:255',
            'cnpj' => [
                'required',
                'string',
                new CnpjRule,
                Rule::unique('instituicoes', 'cnpj')->ignore(InstituicaoContext::id()),
            ],
            // Contato usado na subconta Asaas (com fallback no admin, se vazio).
            'email_contato' => 'required|email|max:255',
            'whatsapp' => ['required', 'string', 'regex:/^\d{10,11}$/'],
            // Endereço obrigatório para provisionar subconta (Centralizador / Asaas).
            'rua' => 'required|string|max:255',
            'numero' => 'required|string|max:10',
            'bairro' => 'required|string|max:255',
            'complemento' => 'nullable|string|max:255',
            'cep' => ['required', 'string', 'max:10', 'regex:/^\d{5}-?\d{3}$/'],
            'id_estado' => 'nullable|exists:estados,id',
            'id_cidade' => 'nullable|exists:cidades,id',
            'permitir_aluno_editar_medidas' => 'sometimes|boolean',
            'limite_reposicoes_periodo' => 'sometimes|nullable|integer|min:0|max:99',
            'prazo_reposicao_dias' => 'sometimes|nullable|integer|min:0|max:365',
            'frequencia_minima' => 'sometimes|required|numeric|min:0|max:100',
            'modo_geracao_mensalidades' => 'sometimes|required|in:automatico,formulario,duracao_turma',
            'dia_vencimento_mensalidade' => 'sometimes|required|integer|min:1|max:31',
            'quantidade_parcelas_mensalidade' => 'sometimes|required|integer|min:1|max:24',
            'inicio_parcelas_mensalidade' => 'sometimes|required|in:mes_matricula,mes_seguinte',
        ];
    }
}
