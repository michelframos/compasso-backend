<?php

namespace App\Modules\Core\DTOs;

final class EnderecoCepDTO
{
    public readonly ?string $logradouro;

    public readonly ?string $complemento;

    public readonly ?string $bairro;

    public readonly ?string $cidade;

    public readonly ?string $uf;

    public readonly ?int $codigoIbge;

    /**
     * @param  string  $cep  Somente os 8 dígitos.
     */
    public function __construct(
        public readonly string $cep,
        ?string $logradouro = null,
        ?string $complemento = null,
        ?string $bairro = null,
        ?string $cidade = null,
        ?string $uf = null,
        int|string|null $codigoIbge = null,
    ) {
        $this->logradouro = self::limpar($logradouro);
        $this->complemento = self::limpar($complemento);
        $this->bairro = self::limpar($bairro);
        $this->cidade = self::limpar($cidade);
        $this->uf = ($uf = self::limpar($uf)) !== null ? mb_strtoupper($uf) : null;
        $this->codigoIbge = is_numeric($codigoIbge) ? (int) $codigoIbge : null;
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    public static function fromArray(array $dados): self
    {
        return new self(
            cep: (string) $dados['cep'],
            logradouro: $dados['logradouro'] ?? null,
            complemento: $dados['complemento'] ?? null,
            bairro: $dados['bairro'] ?? null,
            cidade: $dados['cidade'] ?? null,
            uf: $dados['uf'] ?? null,
            codigoIbge: $dados['codigo_ibge'] ?? null,
        );
    }

    /**
     * @return array{cep: string, logradouro: ?string, complemento: ?string, bairro: ?string, cidade: ?string, uf: ?string, codigo_ibge: ?int}
     */
    public function toArray(): array
    {
        return [
            'cep' => $this->cep,
            'logradouro' => $this->logradouro,
            'complemento' => $this->complemento,
            'bairro' => $this->bairro,
            'cidade' => $this->cidade,
            'uf' => $this->uf,
            'codigo_ibge' => $this->codigoIbge,
        ];
    }

    public function cepFormatado(): string
    {
        return substr($this->cep, 0, 5).'-'.substr($this->cep, 5);
    }

    private static function limpar(?string $valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }
}
