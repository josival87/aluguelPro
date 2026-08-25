<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);
        $remember = $request->boolean('remember');
        $user = $this->findUser(trim($credentials['login']));

        if (! $user || ! Auth::attempt([
            'id' => $user->id,
            'password' => $credentials['password'],
            'active' => true,
        ], $remember)) {
            return back()->withErrors(['login' => 'Login ou senha inválidos.'])->onlyInput('login');
        }

        $request->session()->regenerate();

        return redirect()->intended($request->user()->role === 'client' ? route('client.dashboard') : route('admin.dashboard'));
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function findUser(string $identifier): ?User
    {
        $staff = User::query()
            ->whereIn('role', ['admin', 'manager'])
            ->where('active', true)
            ->where('login', $identifier)
            ->first();

        if ($staff) {
            return $staff;
        }

        $cpf = preg_replace('/\D/', '', $identifier) ?? '';

        if (strlen($cpf) === 11 && preg_match('/^\d{3}\.?\d{3}\.?\d{3}-?\d{2}$/', $identifier)) {
            $formattedCpf = substr($cpf, 0, 3).'.'.substr($cpf, 3, 3).'.'.substr($cpf, 6, 3).'-'.substr($cpf, 9, 2);

            return User::query()
                ->where('role', 'client')
                ->where('active', true)
                ->where(function ($query) use ($cpf, $formattedCpf) {
                    $query->whereIn('cpf', [$cpf, $formattedCpf])
                        ->orWhereHas('client', fn ($client) => $client->whereIn('cpf', [$cpf, $formattedCpf]));
                })
                ->first();
        }

        return null;
    }
}
