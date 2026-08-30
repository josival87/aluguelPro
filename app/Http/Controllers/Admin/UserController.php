<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PropertyGroup;
use App\Models\User;
use App\Support\AdminGroupContext;
use App\Support\Cpf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::query()
            ->with('group')
            ->whereIn('role', ['admin', 'manager'])
            ->when(
                AdminGroupContext::groupId($request->user()),
                fn ($query, int $groupId) => $query->where('group_id', $groupId),
            )
            ->latest()
            ->paginate(15);

        return view('admin.users.index', [
            'users' => $users,
        ]);
    }

    public function create(Request $request)
    {
        return view('admin.users.form', [
            'user' => new User,
            'groups' => PropertyGroup::orderBy('name')->get(),
            'canSelectAllGroups' => $request->user()->hasAllGroupsAccess(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['active'] = $request->boolean('active');
        $data['group_id'] = AdminGroupContext::groupId($request->user()) ?? ($data['group_id'] ?? null);
        User::create($data);

        return redirect()->route('admin.users.index')->with('success', 'Usuário cadastrado.');
    }

    public function edit(Request $request, User $user)
    {
        abort_if($user->role === 'client', 404);
        $this->ensureUserIsAccessible($request->user(), $user);

        return view('admin.users.form', [
            'user' => $user,
            'groups' => PropertyGroup::orderBy('name')->get(),
            'canSelectAllGroups' => $request->user()->hasAllGroupsAccess(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        abort_if($user->role === 'client', 404);
        $this->ensureUserIsAccessible($request->user(), $user);
        $data = $this->validated($request, $user);
        $data['active'] = $request->boolean('active');
        $data['group_id'] = AdminGroupContext::groupId($request->user()) ?? ($data['group_id'] ?? null);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $user->update($data);

        return redirect()->route('admin.users.index')->with('success', 'Usuário atualizado.');
    }

    public function destroy(Request $request, User $user)
    {
        abort_if($user->is(auth()->user()), 422, 'Você não pode excluir seu próprio usuário.');
        abort_if($user->role === 'client', 404);
        $this->ensureUserIsAccessible($request->user(), $user);
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
            'group_id' => ['nullable', 'integer', Rule::exists('groups', 'id')],
            'active' => ['boolean'],
            'password' => [$user ? 'nullable' : 'required', 'nullable', 'confirmed', 'min:8'],
        ], [
            'cpf.digits' => 'O CPF deve conter 11 números.',
        ]);
    }

    private function ensureUserIsAccessible(User $actor, User $target): void
    {
        $groupId = AdminGroupContext::groupId($actor);

        abort_if($groupId !== null && (int) $target->group_id !== $groupId, 404);
    }
}
