<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Charge;
use App\Models\Lease;
use App\Models\SolarReading;
use App\Services\MeterOcrService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class SolarReadingController extends Controller
{
    public function create()
    {
        $leases = Lease::with('property', 'solarConfig.readings')->whereIn('status', Lease::IN_FORCE_STATUSES)->where('has_solar_energy', true)->orderByDesc('id')->get();

        return view('admin.solar.create', compact('leases'));
    }

    public function analyze(Request $request, MeterOcrService $ocr)
    {
        $request->validate(['photo' => ['required', 'image', 'max:8192']]);
        try {
            return response()->json($ocr->read($request->file('photo')));
        } catch (Throwable $e) {
            return response()->json(['reading' => null, 'confidence' => 0, 'candidates' => [], 'requires_confirmation' => true, 'message' => $e->getMessage()], 422);
        }
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'lease_id' => ['required', 'exists:leases,id'], 'reference_month' => ['required', 'date_format:Y-m'], 'meter_reading' => ['required', 'numeric', 'min:0'],
            'photo' => ['required', 'image', 'max:8192'], 'ocr_reading' => ['nullable', 'numeric', 'min:0'], 'ocr_confidence' => ['nullable', 'numeric', 'between:0,1'],
        ]);
        $lease = Lease::with('solarConfig.readings')->findOrFail($data['lease_id']);
        abort_unless($lease->has_solar_energy && $lease->solarConfig, 422, 'Aluguel sem configuração solar.');
        $month = Carbon::createFromFormat('Y-m', $data['reference_month'])->startOfMonth();
        $previous = (float) ($lease->solarConfig->readings()->where('reference_month', '<', $month)->orderByDesc('reference_month')->value('meter_reading') ?? $lease->solarConfig->initial_reading);
        $current = (float) $data['meter_reading'];
        abort_if($current < $previous, 422, "A leitura atual não pode ser menor que {$previous} kWh.");
        $consumption = round($current - $previous, 3);
        $amount = round($consumption * (float) $lease->solarConfig->price_per_kwh, 2);
        $file = $request->file('photo');
        DB::transaction(function () use ($request, $data, $lease, $month, $previous, $current, $consumption, $amount, $file) {
            $charge = Charge::updateOrCreate(['lease_id' => $lease->id, 'type' => 'solar', 'reference_month' => $month->toDateString()], [
                'client_id' => $lease->client_id, 'due_date' => $month->copy()->day(min($lease->due_day, $month->daysInMonth))->toDateString(), 'amount' => $amount, 'status' => 'open', 'description' => "Energia solar: {$consumption} kWh",
            ]);
            SolarReading::updateOrCreate(['solar_config_id' => $lease->solarConfig->id, 'reference_month' => $month->toDateString()], [
                'charge_id' => $charge->id, 'previous_reading' => $previous, 'meter_reading' => $current, 'consumption_kwh' => $consumption, 'amount' => $amount,
                'photo_base64' => base64_encode(file_get_contents($file->getRealPath())), 'photo_mime_type' => $file->getMimeType(),
                'ocr_reading' => $data['ocr_reading'] ?? null, 'ocr_confidence' => $data['ocr_confidence'] ?? null,
                'ocr_status' => isset($data['ocr_reading']) && abs((float) $data['ocr_reading'] - $current) < 0.001 ? 'confirmed' : 'corrected', 'confirmed_by' => $request->user()->id,
            ]);
        });

        return redirect()->route('admin.leases.show', $lease)->with('success', "Medição registrada: {$consumption} kWh, total R$ ".number_format($amount,2,',','.').'.');
    }
}
