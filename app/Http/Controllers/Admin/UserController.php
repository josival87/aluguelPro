<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Cpf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        return view('admin.users.index', [
            'users' => User::whereIn('role', ['admin', 'manager'])->latest()->paginate(15),
        ]);
    }

    public function create()
    {
        return view('admin.users.form', ['user' => new User]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['active'] = $request->boolean('active');
        User::create($data);

        return redirect()->route('admin.users.index')->with('success', 'Usuário cadastrado.');
    }

    public function edit(User $user)
    {
        abort_if($user->role === 'client', 404);

        return view('admin.users.form', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        abort_if($user->role === 'client', 404);
        $data = $this->validated($request, $user);
        $data['active'] = $request->boolean('active');
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $user->update($data);

        return redirect()->route('admin.users.index')->with('success', 'Usuário atualizado.');
    }

    public function destroy(User $user)
    {
        abort_if($user->is(auth()->user()), 422, 'Você não pode excluir seu próprio usuário.');
        abort_if($user->role === 'client', 404);
        $user->delete();

        return back()->with('success', 'Usuário excluído.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $request->merge(['cpf' => Cpf::digits($request->input('cpf'))]);

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'cpf' => ['nullable', 'digits:11', Rule::unique('users')->ignore($user)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'login' => ['required', 'string', 'max:255', Rule::unique('users')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', Rule::in(['admin', 'manager'])],
            'active' => ['boolean'],
            'password' => [$user ? 'nullable' : 'required', 'nullable', 'confirmed', 'min:8'],
        ], [
            'cpf.digits' => 'O CPF deve conter 11 números.',
        ]);
    }
}
