<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PropertyGroup;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    public function index() { return view('admin.groups.index', ['groups' => PropertyGroup::withCount('properties')->latest()->paginate(15)]); }
    public function create() { return view('admin.groups.form', ['group' => new PropertyGroup]); }
    public function store(Request $request) { PropertyGroup::create($this->validated($request)); return redirect()->route('admin.groups.index')->with('success', 'Grupo cadastrado.'); }
    public function edit(PropertyGroup $group) { return view('admin.groups.form', compact('group')); }
    public function update(Request $request, PropertyGroup $group) { $group->update($this->validated($request)); return redirect()->route('admin.groups.index')->with('success', 'Grupo atualizado.'); }
    public function destroy(PropertyGroup $group) { abort_if($group->properties()->exists(), 422, 'Não é possível excluir um grupo com imóveis.'); $group->delete(); return back()->with('success', 'Grupo excluído.'); }
    private function validated(Request $request): array { return $request->validate(['name' => ['required','string','max:255'], 'responsible_name' => ['required','string','max:255'], 'phone' => ['required','string','max:20'], 'pix_key' => ['required','string','max:255']]); }
}
