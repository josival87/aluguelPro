<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function create() { return view('auth.login'); }

    public function store(Request $request)
    {
        $credentials = $request->validate(['login' => ['required', 'string'], 'password' => ['required', 'string']]);
        $remember = $request->boolean('remember');

        if (! Auth::attempt([...$credentials, 'active' => true], $remember)) {
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
}
