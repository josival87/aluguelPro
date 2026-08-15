<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Lease;
use App\Models\LeaseContract;
use Illuminate\Validation\ValidationException;

class ContractService
{
    /** @return array<string, string> */
    public function availablePlaceholders(): array
    {
        return [
            'nome_cliente' => 'Nome do cliente',
            'nome_locatario' => 'Nome do locatário',
            'nome_locatpario' => 'Nome do locatário (alias legado)',
            'cpf_cliente' => 'CPF do cliente',
            'telefone_cliente' => 'Telefone do cliente',
            'email_cliente' => 'E-mail do cliente',
            'renda_familiar' => 'Renda familiar',
            'titulo_imovel' => 'Título do imóvel',
            'tipo_imovel' => 'Residencial ou comercial',
            'descricao_imovel' => 'Descrição do imóvel',
            'endereco_imovel' => 'Endereço completo',
            'rua_imovel' => 'Rua do imóvel',
            'numero_imovel' => 'Número do imóvel',
            'bairro_imovel' => 'Bairro do imóvel',
            'cidade_imovel' => 'Cidade do imóvel',
            'estado_imovel' => 'UF do imóvel',
            'cep_imovel' => 'CEP do imóvel',
            'valor_aluguel' => 'Valor mensal do aluguel',
            'dia_vencimento' => 'Dia de vencimento',
            'data_inicio' => 'Data inicial',
            'data_fim' => 'Data final',
            'tempo_contrato' => 'Prazo em meses',
            'nome_locador' => 'Nome do locador/responsável',
            'telefone_locador' => 'Telefone do locador/responsável',
            'nome_grupo' => 'Nome do grupo do imóvel',
            'chave_pix' => 'Chave Pix do grupo',
            'nome_empresa' => 'Nome da imobiliária',
            'cnpj_empresa' => 'CNPJ da imobiliária',
            'telefone_empresa' => 'Telefone da imobiliária',
            'email_empresa' => 'E-mail da imobiliária',
            'numero_unidade_energia' => 'Número da unidade de energia',
            'valor_kwh' => 'Valor do kWh contratado',
            'data_geracao' => 'Data de geração do contrato',
        ];
    }

    /** @return list<string> */
    public function unknownPlaceholders(string $content): array
    {
        preg_match_all('/{{\s*([a-zA-Z0-9_]+)\s*}}/u', $content, $matches);

        return collect($matches[1] ?? [])
            ->unique()
            ->reject(fn (string $key) => array_key_exists($key, $this->availablePlaceholders()))
            ->values()
            ->all();
    }

    public function sanitize(string $html): string
    {
        $allowed = '<article><section><div><p><br><strong><b><em><i><u><h1><h2><h3><h4><ol><ul><li><table><thead><tbody><tfoot><tr><th><td><blockquote><hr><span>';
        $clean = strip_tags($html, $allowed);
        $clean = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/iu', '', $clean) ?? $clean;
        $clean = preg_replace_callback(
            '/\s+style\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/iu',
            function (array $match): string {
                $style = $match[1] ?? '';
                if ($style === '') {
                    $style = $match[2] ?? '';
                }
                if ($style === '') {
                    $style = $match[3] ?? '';
                }
                $safe = [];

                foreach (explode(';', $style) as $declaration) {
                    if (! str_contains($declaration, ':')) {
                        continue;
                    }

                    [$property, $value] = array_map('trim', explode(':', $declaration, 2));
                    $property = strtolower($property);
                    $value = strtolower($value);

                    if ($property === 'text-align' && in_array($value, ['left', 'center', 'right'], true)) {
                        $safe[$property] = $value;
                    }

                    if ($property === 'margin-left'
                        && preg_match('/^(?:0|[1-9]\d{0,2})px$/', $value)
                        && (int) $value <= 320) {
                        $safe[$property] = $value;
                    }
                }

                if ($safe === []) {
                    return '';
                }

                return ' style="'.e(collect($safe)->map(
                    fn (string $value, string $property) => $property.': '.$value
                )->implode('; ')).'"';
            },
            $clean,
        ) ?? $clean;
        $clean = preg_replace('/\s+(class|id|src|href)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/iu', '', $clean) ?? $clean;
        $clean = preg_replace('/\b(javascript|data|vbscript)\s*:/iu', '', $clean) ?? $clean;

        return trim($clean);
    }

    public function validateTemplate(string $content): string
    {
        $content = $this->sanitize($content);
        $unknown = $this->unknownPlaceholders($content);

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'content' => 'Variáveis não reconhecidas: '.implode(', ', array_map(fn ($key) => '{{'.$key.'}}', $unknown)),
            ]);
        }

        if (trim(strip_tags($content)) === '') {
            throw ValidationException::withMessages(['content' => 'Informe o texto do contrato.']);
        }

        return $content;
    }

    public function generate(Lease $lease): LeaseContract
    {
        $lease->loadMissing('client', 'property.contract', 'property.group', 'solarConfig', 'contract');
        $template = $lease->property->contract;

        if (! $template || ! $template->active) {
            throw ValidationException::withMessages([
                'contract' => 'O imóvel precisa ter um contrato-base ativo selecionado.',
            ]);
        }

        if ($lease->contract) {
            return $lease->contract;
        }

        $content = $this->render($template, $lease);

        return LeaseContract::create([
            'lease_id' => $lease->id,
            'template_id' => $template->id,
            'final_content' => $content,
            'content_hash' => hash('sha256', $content),
            'status' => 'in_production',
            'generated_at' => now(),
        ]);
    }

    public function render(Contract $template, Lease $lease): string
    {
        $values = $this->values($lease);

        return preg_replace_callback(
            '/{{\s*([a-zA-Z0-9_]+)\s*}}/u',
            fn (array $match) => e($values[$match[1]] ?? $match[0]),
            $template->content,
        ) ?? $template->content;
    }

    public function saveDraft(LeaseContract $contract, string $content): LeaseContract
    {
        abort_unless($contract->status === 'in_production', 422, 'Somente contratos em produção podem ser alterados.');
        $content = $this->sanitize($content);

        if (trim(strip_tags($content)) === '') {
            throw ValidationException::withMessages(['final_content' => 'O contrato final não pode ficar vazio.']);
        }

        $contract->update([
            'final_content' => $content,
            'content_hash' => hash('sha256', $content),
        ]);

        return $contract;
    }

    /** @return array<string, string> */
    private function values(Lease $lease): array
    {
        $company = Company::query()->where('singleton', true)->first();
        $property = $lease->property;
        $client = $lease->client;
        $group = $property->group;
        $address = collect([
            trim($property->street.' '.($property->number ?? '')),
            $property->complement,
            $property->neighborhood,
            $property->city.'/'.$property->state,
            $property->postal_code,
        ])->filter()->implode(', ');
        $clientName = $client->name ?: 'não informado';

        return [
            'nome_cliente' => $clientName,
            'nome_locatario' => $clientName,
            'nome_locatpario' => $clientName,
            'cpf_cliente' => $client->cpf ?: 'não informado',
            'telefone_cliente' => $client->phone ?: 'não informado',
            'email_cliente' => $client->email ?: 'não informado',
            'renda_familiar' => $client->family_income ? 'R$ '.number_format((float) $client->family_income, 2, ',', '.') : 'não informada',
            'titulo_imovel' => $property->title,
            'tipo_imovel' => $property->type === 'commercial' ? 'comercial' : 'residencial',
            'descricao_imovel' => $property->description,
            'endereco_imovel' => $address,
            'rua_imovel' => $property->street,
            'numero_imovel' => $property->number ?: 's/n',
            'bairro_imovel' => $property->neighborhood,
            'cidade_imovel' => $property->city,
            'estado_imovel' => $property->state,
            'cep_imovel' => $property->postal_code ?: 'não informado',
            'valor_aluguel' => 'R$ '.number_format((float) $lease->rent_amount, 2, ',', '.'),
            'dia_vencimento' => (string) $lease->due_day,
            'data_inicio' => $lease->start_date?->format('d/m/Y') ?? 'a definir',
            'data_fim' => $lease->end_date?->format('d/m/Y') ?? 'a definir',
            'tempo_contrato' => $lease->contract_months.' meses',
            'nome_locador' => $group->responsible_name,
            'telefone_locador' => $group->phone,
            'nome_grupo' => $group->name,
            'chave_pix' => $group->pix_key,
            'nome_empresa' => $company?->name ?? 'AlugaPro',
            'cnpj_empresa' => $company?->cnpj ?? 'não informado',
            'telefone_empresa' => $company?->phone ?? 'não informado',
            'email_empresa' => $company?->email ?? 'não informado',
            'numero_unidade_energia' => $lease->utility_number ?: 'não informado',
            'valor_kwh' => $lease->solarConfig ? 'R$ '.number_format((float) $lease->solarConfig->price_per_kwh, 4, ',', '.') : 'não aplicável',
            'data_geracao' => now()->format('d/m/Y'),
        ];
    }
}
