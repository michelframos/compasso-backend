<?php

namespace App\Modules\Core\Http\Requests\Auth;

use App\Modules\Core\Domain\ValueObjects\Cnpj;
use Illuminate\Foundation\Http\FormRequest;

use OpenApi\Attributes as OA;

#[OA\Schema(
    title: "Login Request",
    description: "Login no painel da escola. Informe tenant_cnpj (ou, alternativamente, tenant_slug).",
    required: ["email", "password", "tenant_cnpj"],
    properties: [
        new OA\Property(property: "email", type: "string", format: "email", example: "user@example.com"),
        new OA\Property(property: "password", type: "string", format: "password", example: "password"),
        new OA\Property(property: "tenant_cnpj", type: "string", example: "11.222.333/0001-81"),
        new OA\Property(property: "tenant_slug", type: "string", nullable: true, example: "default")
    ]
)]
class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('tenant_cnpj')) {
            $this->merge(['tenant_cnpj' => Cnpj::normalize($this->input('tenant_cnpj'))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'tenant_cnpj' => ['required_without:tenant_slug', 'nullable', 'string', 'max:20'],
            'tenant_slug' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tenant_cnpj.required_without' => 'Informe o CNPJ da escola.',
        ];
    }
}
