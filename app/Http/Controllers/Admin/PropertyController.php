<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Feature;
use App\Models\Property;
use App\Models\PropertyGroup;
use App\Models\PropertyMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PropertyController extends Controller
{
    public function index(Request $request)
    {
        $properties = Property::with('group')->when($request->status, fn ($q, $v) => $q->where('status', $v))->when($request->q, fn ($q, $v) => $q->where('title', 'ilike', "%{$v}%"))->latest()->paginate(15)->withQueryString();

        return view('admin.properties.index', compact('properties'));
    }

    public function create()
    {
        return view('admin.properties.form', ['property' => new Property, 'groups' => PropertyGroup::orderBy('name')->get(), 'features' => Feature::orderBy('name')->get(), 'contracts' => Contract::where('active', true)->orderBy('title')->get()]);
    }

    public function store(Request $request)
    {
        $property = Property::create($this->validated($request));
        $this->relations($request, $property);

        return redirect()->route('admin.properties.index')->with('success', 'Imóvel cadastrado.');
    }

    public function show(Property $property)
    {
        $property->load([
            'group',
            'contract',
            'features',
            'media' => fn ($query) => $query->select(PropertyMedia::DISPLAY_COLUMNS),
            'leases.client',
        ]);

        return view('admin.properties.show', compact('property'));
    }

    public function edit(Property $property)
    {
        return view('admin.properties.form', [
            'property' => $property->load([
                'features',
                'media' => fn ($query) => $query->select(PropertyMedia::DISPLAY_COLUMNS),
            ]),
            'groups' => PropertyGroup::orderBy('name')->get(),
            'features' => Feature::orderBy('name')->get(),
            'contracts' => Contract::where('active', true)->orWhere('id', $property->contract_id)->orderBy('title')->get(),
        ]);
    }

    public function update(Request $request, Property $property)
    {
        $property->update($this->validated($request, $property));
        $this->relations($request, $property);

        return redirect()->route('admin.properties.show', $property)->with('success', 'Imóvel atualizado.');
    }

    public function destroy(Property $property)
    {
        abort_if($property->leases()->exists(), 422, 'Imóvel possui aluguéis vinculados.');
        $property->delete();

        return redirect()->route('admin.properties.index')->with('success', 'Imóvel excluído.');
    }

    private function validated(Request $request, ?Property $property = null): array
    {
        $data = $request->validate([
            'group_id' => ['required', 'exists:groups,id'], 'contract_id' => ['required', 'exists:contracts,id'], 'title' => ['required', 'string', 'max:255'], 'slug' => ['nullable', 'string', 'max:255', Rule::unique('properties')->ignore($property)],
            'description' => ['required', 'string'], 'type' => ['required', Rule::in(['residential', 'commercial'])], 'usable_area' => ['nullable', 'numeric', 'min:0'],
            'bedrooms' => ['required', 'integer', 'min:0'], 'bathrooms' => ['required', 'integer', 'min:0'], 'parking_spaces' => ['required', 'integer', 'min:0'],
            'street' => ['required', 'string', 'max:255'], 'number' => ['nullable', 'string', 'max:20'], 'complement' => ['nullable', 'string', 'max:255'],
            'neighborhood' => ['required', 'string', 'max:255'], 'city' => ['required', 'string', 'max:255'], 'state' => ['required', 'string', 'size:2'], 'postal_code' => ['nullable', 'string', 'max:9'],
            'rent_amount' => ['required', 'numeric', 'min:0'], 'status' => ['required', Rule::in(['available', 'rented', 'paused'])], 'has_solar_energy' => ['boolean'],
            'features' => ['array'], 'features.*' => ['exists:features,id'], 'media' => ['array', 'max:'.PropertyMedia::MAX_ITEMS], 'media.*' => ['file', 'mimetypes:'.implode(',', PropertyMedia::ALLOWED_MIME_TYPES), 'max:'.PropertyMedia::MAX_SIZE_KB],
        ]);
        $data['slug'] = $data['slug'] ?? $property?->slug ?? Str::slug($data['title']).'-'.Str::lower(Str::random(5));
        $data['has_solar_energy'] = $request->boolean('has_solar_energy');

        $mediaCount = ($property?->media()->count() ?? 0) + count($request->file('media', []));
        if ($mediaCount > PropertyMedia::MAX_ITEMS) {
            throw ValidationException::withMessages([
                'media' => 'O imóvel pode ter no máximo '.PropertyMedia::MAX_ITEMS.' mídias.',
            ]);
        }

        return $data;
    }

    private function relations(Request $request, Property $property): void
    {
        $property->features()->sync($request->input('features', []));
        $nextSortOrder = ((int) $property->media()->max('sort_order')) + 1;
        foreach ($request->file('media', []) as $index => $file) {
            $property->media()->create(['mime_type' => PropertyMedia::normalizeMimeType($file->getMimeType()), 'media_base64' => base64_encode(file_get_contents($file->getRealPath())), 'sort_order' => $nextSortOrder + $index]);
        }
    }
}
