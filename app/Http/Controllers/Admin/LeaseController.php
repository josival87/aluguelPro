<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Lease;
use App\Models\PixPayment;
use App\Models\Property;
use App\Services\ContractDateExtractor;
use App\Services\ContractService;
use App\Services\PixService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class LeaseController extends Controller
{
    public function index(Request $request)
    {
        $leases = Lease::with('client', 'property.group')->when($request->status, fn ($q, $v) => $q->where('status', $v))->orderByDesc('id')->paginate(15)->withQueryString();

        return view('admin.leases.index', compact('leases'));
    }

    public function create()
    {
        return view('admin.leases.form', ['lease' => new Lease, 'contractDates' => [], 'clients' => $this->clientsForForm(), 'properties' => Property::whereIn('status', ['available', 'paused'])->orderBy('title')->get()]);
    }

    public function store(Request $request, ContractService $contracts)
    {
        $data = $this->validated($request);
        $lease = DB::transaction(function () use ($request, $data, $contracts) {
            $lease = Lease::create($data);
            $this->solar($request, $lease);
            $contracts->generate($lease);
            if ($lease->isInForce()) {
                $lease->property()->update(['status' => 'rented']);
            }

            return $lease;
        });

        return redirect()->route('admin.leases.show', $lease)->with('success', 'Aluguel cadastrado.');
    }

    public function show(Lease $lease, PixService $pix)
    {
        $lease->load([
            'client.documents',
            'property.group',
            'charges.adjustments.user',
            'solarConfig.readings.charge',
            'contract.template',
            'contract.signatures',
            'documents.uploader',
            'notificationLogs' => fn ($query) => $query
                ->where('recipient_type', 'client')
                ->with('charge')
                ->latest(),
        ]);
        try {
            $pix->normalizeKey((string) $lease->property->group?->pix_key);
            $pixReady = true;
        } catch (InvalidArgumentException) {
            $pixReady = false;
        }
        $pixPayment = session('pix_payment_id')
            ? PixPayment::with('charge')
                ->whereKey(session('pix_payment_id'))
                ->whereHas('charge', fn ($query) => $query->where('lease_id', $lease->id))
                ->first()
            : null;

        return view('admin.leases.show', compact('lease', 'pixPayment', 'pixReady'));
    }

    public function edit(Lease $lease, ContractDateExtractor $dateExtractor)
    {
        $lease->load('solarConfig', 'contract');

        return view('admin.leases.form', [
            'lease' => $lease,
            'contractDates' => $dateExtractor->extract($lease->contract?->final_content),
            'clients' => $this->clientsForForm(),
            'properties' => Property::where(fn ($q) => $q->whereIn('status', ['available', 'paused'])->orWhere('id', $lease->property_id))->orderBy('title')->get(),
        ]);
    }

    public function update(Request $request, Lease $lease, ContractService $contracts)
    {
        $data = $this->validated($request, $lease);
        DB::transaction(function () use ($request, $data, $lease, $contracts) {
            $lease->update($data);
            $this->solar($request, $lease);
            if (! $lease->contract) {
                $contracts->generate($lease);
            }
            if ($lease->isInForce()) {
                $lease->property()->update(['status' => 'rented']);
            }
            if (in_array($lease->status, Lease::CLOSED_STATUSES, true)) {
                $lease->property()->update(['status' => 'available']);
            }
        });

        return redirect()->route('admin.leases.show', $lease)->with('success', 'Aluguel atualizado.');
    }

    public function destroy(Lease $lease)
    {
        abort_if($lease->charges()->exists(), 422, 'Aluguel possui cobranças. Cancele-o em vez de excluir.');
        $lease->delete();

        return redirect()->route('admin.leases.index')->with('success', 'Aluguel excluído.');
    }

    private function validated(Request $request, ?Lease $lease = null): array
    {
        $data = $request->validate([
            'property_id' => ['required', 'exists:properties,id'], 'client_id' => ['required', 'exists:clients,id'], 'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'], 'contract_months' => ['required', 'integer', 'min:1', 'max:120'], 'due_day' => ['required', 'integer', 'min:1', 'max:28'],
            'rent_amount' => ['required', 'numeric', 'min:0'], 'status' => ['required', Rule::in(['awaiting_completion', 'awaiting_signatures', 'active', 'closed', 'cancelled'])],
            'has_solar_energy' => ['boolean'], 'utility_number' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string'],
            'initial_reading' => ['nullable', 'required_if:has_solar_energy,1', 'numeric', 'min:0'], 'price_per_kwh' => ['nullable', 'required_if:has_solar_energy,1', 'numeric', 'min:0'],
        ]);
        $data['has_solar_energy'] = $request->boolean('has_solar_energy');
        Property::query()->findOrFail($data['property_id']);
        Client::query()->findOrFail($data['client_id']);
        if ($data['status'] === 'awaiting_signatures' && $lease?->contract?->status !== 'awaiting_signatures') {
            throw ValidationException::withMessages(['status' => 'Envie o contrato finalizado para assinatura pela ficha do aluguel.']);
        }
        if (! empty($data['start_date']) && empty($data['end_date'])) {
            $data['end_date'] = Carbon::parse($data['start_date'])->addMonths((int) $data['contract_months'])->toDateString();
        }

        return $data;
    }

    private function solar(Request $request, Lease $lease): void
    {
        if ($lease->has_solar_energy) {
            $lease->solarConfig()->updateOrCreate([], ['initial_reading' => $request->input('initial_reading'), 'price_per_kwh' => $request->input('price_per_kwh')]);
        } elseif ($lease->solarConfig && ! $lease->solarConfig->readings()->exists()) {
            $lease->solarConfig()->delete();
        }
    }

    private function clientsForForm()
    {
        return Client::query()
            ->withCount([
                'leases as active_leases_count' => fn ($query) => $query->whereIn('status', Lease::IN_FORCE_STATUSES),
            ])
            ->orderBy('active_leases_count')
            ->orderBy('name')
            ->get();
    }
}
