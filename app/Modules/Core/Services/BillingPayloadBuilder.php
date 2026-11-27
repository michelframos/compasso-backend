<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\Instituicao;
use App\Modules\Core\Models\InstituicaoUsuario;
use App\Modules\Core\Models\User;

/**
 * Monta os payloads de cliente/subconta enviados ao Centralizador a partir da instituição.
 */
class BillingPayloadBuilder
{
    public static function subscriberId(Instituicao $instituicao): string
    {
        return 'instituicao:'.$instituicao->id;
    }

    public static function nomeInstituicao(Instituicao $instituicao): string
    {
        return (string) ($instituicao->razao_social ?: $instituicao->nome_fantasia);
    }

    /**
     * E-mail do responsável: usuário informado ou primeiro admin ativo da instituição.
     */
    public function adminUser(Instituicao $instituicao, ?User $user = null): ?User
    {
        if ($user !== null) {
            return $user;
        }

        $idUsuario = InstituicaoUsuario::query()
            ->where('id_instituicao', $instituicao->id)
            ->where('role', 'admin')
            ->where('status', InstituicaoUsuario::STATUS_ATIVO)
            ->orderBy('id_usuario')
            ->value('id_usuario');

        return $idUsuario !== null ? User::query()->find($idUsuario) : null;
    }

    /**
     * E-mail da escola para o gateway: contato da empresa, senão e-mail do admin.
     */
    public function emailContato(Instituicao $instituicao, ?User $user = null): ?string
    {
        $emailEmpresa = filled($instituicao->email_contato) ? trim((string) $instituicao->email_contato) : null;

        if ($emailEmpresa !== null && $emailEmpresa !== '') {
            return $emailEmpresa;
        }

        return $this->adminUser($instituicao, $user)?->email;
    }

    /**
     * Celular da escola para o gateway: WhatsApp da empresa, senão WhatsApp/telefone do admin.
     */
    public function mobilePhone(Instituicao $instituicao, ?User $user = null): ?string
    {
        $whatsappEmpresa = $this->digits($instituicao->whatsapp);
        if ($whatsappEmpresa !== null) {
            return $whatsappEmpresa;
        }

        $admin = $this->adminUser($instituicao, $user);

        return $this->digits($admin?->whatsapp ?? $admin?->telefone);
    }

    /**
     * Cliente da assinatura SaaS (name, email, cpf_cnpj + endereço).
     *
     * @return array<string, mixed>
     */
    public function customer(Instituicao $instituicao, ?User $user = null): array
    {
        return array_filter([
            'name' => self::nomeInstituicao($instituicao),
            'email' => $this->emailContato($instituicao, $user),
            'cpf_cnpj' => $this->digits($instituicao->cnpj),
            'address' => $instituicao->rua,
            'address_number' => $instituicao->numero,
            'complement' => $instituicao->complemento,
            'province' => $instituicao->bairro,
            'postal_code' => $this->digits($instituicao->cep),
        ], fn ($valor) => $valor !== null && $valor !== '');
    }

    /**
     * Payload de POST /api/v1/subaccounts.
     *
     * @return array<string, mixed>
     */
    public function subaccount(Instituicao $instituicao, ?User $user = null): array
    {
        $customer = $this->customer($instituicao, $user);

        return array_filter([
            'subscriber_id' => self::subscriberId($instituicao),
            'name' => $customer['name'] ?? null,
            'email' => $customer['email'] ?? null,
            'cpf_cnpj' => $customer['cpf_cnpj'] ?? null,
            'company_type' => 'LIMITED',
            'income_value' => (float) config('services.billing.subconta_income_value', 25000),
            'mobile_phone' => $this->mobilePhone($instituicao, $user),
            'address' => $customer['address'] ?? null,
            'address_number' => $customer['address_number'] ?? null,
            'complement' => $customer['complement'] ?? null,
            'province' => $customer['province'] ?? null,
            'postal_code' => $customer['postal_code'] ?? null,
        ], fn ($valor) => $valor !== null && $valor !== '');
    }

    /**
     * Campos obrigatórios ausentes para criar subconta (chave => rótulo).
     *
     * @return array<string, string>
     */
    public function camposFaltantesSubconta(Instituicao $instituicao, ?User $user = null): array
    {
        $payload = $this->subaccount($instituicao, $user);

        $obrigatorios = [
            'cpf_cnpj' => 'CNPJ',
            'name' => 'razão social ou nome fantasia',
            'email' => 'e-mail de contato da empresa (ou do administrador)',
            'mobile_phone' => 'WhatsApp da empresa (ou telefone do administrador)',
            'address' => 'rua',
            'address_number' => 'número',
            'province' => 'bairro',
            'postal_code' => 'CEP',
        ];

        return array_diff_key($obrigatorios, $payload);
    }

    private function digits(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $valor);

        return $digits === '' ? null : $digits;
    }
}
