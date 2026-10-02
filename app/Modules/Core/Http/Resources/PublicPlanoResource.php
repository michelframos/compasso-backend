<?php

namespace App\Modules\Core\Http\Resources;

use App\Modules\Core\Contracts\PlanoEntitlementResolverInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicPlanoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'nome' => $this->nome,
            'descricao' => $this->descricao,
            'preco' => (float) $this->preco_mensal,
            'limite_alunos' => $this->limite_alunos,
            'modulos' => array_values($this->modulos ?? []),
            'features' => $this->featuresFromCadastro(),
            'destaque' => (bool) $this->destaque,
        ];
    }

    /**
     * @return list<string>
     */
    private function featuresFromCadastro(): array
    {
        $limite = $this->limite_alunos;
        $features = [
            $limite === null
                ? 'Alunos ilimitados'
                : "Até {$limite} alunos",
        ];

        $resolver = app(PlanoEntitlementResolverInterface::class);
        foreach ($resolver->normalizeModulos($this->modulos) as $key) {
            $label = $resolver->labelFor($key);
            if ($label !== null && ! in_array($label, $features, true)) {
                $features[] = $label;
            }
        }

        $descricao = trim((string) $this->descricao);
        if ($descricao === '') {
            return $features;
        }

        $parts = preg_split('/\r\n|\n|\r|•|;/', $descricao) ?: [];
        foreach ($parts as $part) {
            $part = trim($part, " \t-");
            if ($part !== '' && ! in_array($part, $features, true)) {
                $features[] = $part;
            }
        }

        return $features;
    }
}
