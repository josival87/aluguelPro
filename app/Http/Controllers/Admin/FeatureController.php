<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feature;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FeatureController extends Controller
{
    public function index() { return view('admin.features.index', ['features' => Feature::withCount('properties')->orderBy('name')->paginate(20)]); }
    public function store(Request $request) { Feature::create($request->validate(['name' => ['required','string','max:100','unique:features,name']])); return back()->with('success', 'Característica cadastrada.'); }
    public function update(Request $request, Feature $feature) { $feature->update($request->validate(['name' => ['required','string','max:100', Rule::unique('features')->ignore($feature)]])); return back()->with('success', 'Característica atualizada.'); }
    public function destroy(Feature $feature) { $feature->delete(); return back()->with('success', 'Característica excluída.'); }
}
