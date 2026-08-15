<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Services\ContractService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContractController extends Controller
{
    public function index()
    {
        $contracts = Contract::withCount(['properties', 'leaseContracts'])->orderBy('title')->paginate(15);

        return view('admin.contracts.index', compact('contracts'));
    }

    public function create(ContractService $service)
    {
        return view('admin.contracts.form', [
            'contract' => new Contract,
            'placeholders' => $service->availablePlaceholders(),
        ]);
    }

    public function store(Request $request, ContractService $service)
    {
        $data = $this->validated($request, $service);
        $contract = Contract::create($data);

        return redirect()->route('admin.contracts.edit', $contract)->with('success', 'Contrato-base cadastrado.');
    }

    public function show(Contract $contract, ContractService $service)
    {
        $contract->loadCount(['properties', 'leaseContracts']);

        return view('admin.contracts.show', [
            'contract' => $contract,
            'placeholders' => $service->availablePlaceholders(),
        ]);
    }

    public function edit(Contract $contract, ContractService $service)
    {
        return view('admin.contracts.form', [
            'contract' => $contract,
            'placeholders' => $service->availablePlaceholders(),
        ]);
    }

    public function update(Request $request, Contract $contract, ContractService $service)
    {
        $contract->update($this->validated($request, $service, $contract));

        return redirect()->route('admin.contracts.edit', $contract)->with('success', 'Contrato-base atualizado. Novos aluguéis usarão esta versão.');
    }

    public function destroy(Contract $contract)
    {
        abort_if(
            $contract->properties()->exists() || $contract->leaseContracts()->exists(),
            422,
            'Este contrato está vinculado a imóveis ou aluguéis. Desative-o em vez de excluir.',
        );

        $contract->delete();

        return redirect()->route('admin.contracts.index')->with('success', 'Contrato-base excluído.');
    }

    /** @return array{title: string, content: string, active: bool} */
    private function validated(Request $request, ContractService $service, ?Contract $contract = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255', Rule::unique('contracts')->ignore($contract)],
            'content' => ['required', 'string'],
            'active' => ['boolean'],
        ]);
        $data['content'] = $service->validateTemplate($data['content']);
        $data['active'] = $request->boolean('active');

        return $data;
    }
}
