<?php

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\LeaseContract;
use App\Models\Property;
use Illuminate\Database\Seeder;

class ContractSeeder extends Seeder
{
    public function run(): void
    {
        $residential = Contract::updateOrCreate(
            ['title' => 'Contrato residencial'],
            ['content' => $this->residential(), 'active' => true],
        );
        $commercial = Contract::updateOrCreate(
            ['title' => 'Contrato comercial'],
            ['content' => $this->commercial(), 'active' => true],
        );

        Property::where('type', 'residential')->whereNull('contract_id')->update(['contract_id' => $residential->id]);
        Property::where('type', 'commercial')->whereNull('contract_id')->update(['contract_id' => $commercial->id]);
        LeaseContract::with('lease.property')->whereNull('template_id')->eachById(function (LeaseContract $leaseContract) {
            $leaseContract->update(['template_id' => $leaseContract->lease?->property?->contract_id]);
        });
    }

    private function residential(): string
    {
        return <<<'HTML'
<article>
<h1>CONTRATO DE LOCAÇÃO RESIDENCIAL</h1>
<p><strong>LOCADOR:</strong> {{nome_locador}}, responsável pelo grupo {{nome_grupo}}, telefone {{telefone_locador}}, com administração de {{nome_empresa}}, CNPJ {{cnpj_empresa}}.</p>
<p><strong>LOCATÁRIO:</strong> {{nome_locatario}}, CPF {{cpf_cliente}}, RG {{rg_cliente}}, profissão {{profissao_cliente}}, telefone {{telefone_cliente}} e e-mail {{email_cliente}}.</p>
<p><strong>IMÓVEL:</strong> {{titulo_imovel}}, situado em {{endereco_imovel}}.</p>
<h2>1. Finalidade, prazo e valor</h2>
<p>O imóvel destina-se exclusivamente à moradia. A locação vigorará de {{data_inicio}} a {{data_fim}}, pelo prazo de {{tempo_contrato}}, com aluguel mensal de {{valor_aluguel}} e vencimento no dia {{dia_vencimento}}.</p>
<h2>2. Pagamento e conservação</h2>
<p>O pagamento será realizado pela chave Pix {{chave_pix}}. O locatário obriga-se a conservar o imóvel, respeitar as regras do condomínio e devolver a unidade no estado em que a recebeu, ressalvado o desgaste natural.</p>
<h2>3. Assinatura eletrônica</h2>
<p>As partes aceitam a assinatura eletrônica autenticada por código de uso único enviado ao WhatsApp cadastrado, com registro de data, hora, endereço IP e hash de integridade.</p>
<p>Documento gerado em {{data_geracao}}.</p>
</article>
HTML;
    }

    private function commercial(): string
    {
        return <<<'HTML'
<article>
<h1>CONTRATO DE LOCAÇÃO COMERCIAL</h1>
<p><strong>LOCADOR:</strong> {{nome_locador}}, responsável pelo grupo {{nome_grupo}}, telefone {{telefone_locador}}, com administração de {{nome_empresa}}, CNPJ {{cnpj_empresa}}.</p>
<p><strong>LOCATÁRIO:</strong> {{nome_locatario}}, CPF {{cpf_cliente}}, RG {{rg_cliente}}, profissão {{profissao_cliente}}, telefone {{telefone_cliente}} e e-mail {{email_cliente}}.</p>
<p><strong>IMÓVEL COMERCIAL:</strong> {{titulo_imovel}}, situado em {{endereco_imovel}}.</p>
<h2>1. Destinação e licenças</h2>
<p>O imóvel será utilizado exclusivamente para atividade comercial lícita. O locatário é responsável pelas licenças, alvarás e autorizações exigidos para sua atividade.</p>
<h2>2. Prazo e aluguel</h2>
<p>A locação vigorará de {{data_inicio}} a {{data_fim}}, pelo prazo de {{tempo_contrato}}, com aluguel mensal de {{valor_aluguel}} e vencimento no dia {{dia_vencimento}}.</p>
<h2>3. Pagamento e conservação</h2>
<p>O pagamento será realizado pela chave Pix {{chave_pix}}. Alterações físicas, letreiros e adaptações dependerão de autorização prévia do locador e das autoridades competentes.</p>
<h2>4. Assinatura eletrônica</h2>
<p>As partes aceitam a assinatura eletrônica autenticada por código de uso único enviado ao WhatsApp cadastrado, com registro de data, hora, endereço IP e hash de integridade.</p>
<p>Documento gerado em {{data_geracao}}.</p>
</article>
HTML;
    }
}
