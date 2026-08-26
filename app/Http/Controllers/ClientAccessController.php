<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientAccessCode;
use App\Models\User;
use App\Services\WhatsAppService;
use App\Services\WppConnectClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ClientAccessController extends Controller
{
    private const CLIENT_SESSION_KEY = 'client_access.client_id';

    private const CODE_SESSION_KEY = 'client_access.code_id';

    private const VERIFIED_CODE_SESSION_KEY = 'client_access.verified_code_id';

    public function create()
    {
        return view('auth.access', ['step' => 'phone']);
    }

    public function sendCode(Request $request, WhatsAppService $whatsApp, WppConnectClient $wppConnect): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
        ], [
            'phone.required' => 'Informe o número de telefone cadastrado no AlugaPro.',
        ]);

        try {
            $phone = $wppConnect->normalizePhone($data['phone']);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages([
                'phone' => 'Informe um telefone válido com DDD.',
            ]);
        }

        $client = $this->findClientByPhone($phone, $wppConnect);

        if (! $client) {
            throw ValidationException::withMessages([
                'phone' => 'O número informado não está vinculado a nenhum cliente.',
            ]);
        }

        ClientAccessCode::query()
            ->where('client_id', $client->id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $code = (string) random_int(100000, 999999);
        $accessCode = ClientAccessCode::create([
            'client_id' => $client->id,
            'phone' => $phone,
            'code_hash' => $this->hashCode($code),
            'expires_at' => now()->addMinutes(config('business.otp_expiration_minutes')),
        ]);

        $request->session()->forget([
            self::CLIENT_SESSION_KEY,
            self::CODE_SESSION_KEY,
            self::VERIFIED_CODE_SESSION_KEY,
        ]);
        $request->session()->put([
            self::CLIENT_SESSION_KEY => $client->id,
            self::CODE_SESSION_KEY => $accessCode->id,
        ]);

        $delivery = $whatsApp->send(
            $phone,
            "AlugaPro: seu código para solicitar acesso é {$code}. Ele expira em ".config('business.otp_expiration_minutes').' minutos. Não compartilhe este código.',
            'client_access_otp',
            'client',
        );

        if (app()->isLocal()) {
            $request->session()->flash('dev_otp', $code);
        } elseif ($delivery->status !== 'sent') {
            $accessCode->delete();
            $request->session()->forget([
                self::CLIENT_SESSION_KEY,
                self::CODE_SESSION_KEY,
            ]);

            return back()->withErrors([
                'phone' => 'Não foi possível enviar o código pelo WhatsApp. Tente novamente em alguns instantes.',
            ])->onlyInput('phone');
        }

        return redirect()->route('access.code.form')->with(
            'success',
            $delivery->status === 'sent'
                ? 'Enviamos um código de confirmação para o seu WhatsApp.'
                : 'Código simulado para desenvolvimento.',
        );
    }

    public function code(Request $request)
    {
        $accessCode = $this->pendingCode($request);

        if (! $accessCode) {
            return redirect()->route('access.request')->withErrors([
                'phone' => 'Solicite um novo código para continuar.',
            ]);
        }

        return view('auth.access', [
            'step' => 'code',
            'maskedPhone' => $this->maskPhone($accessCode->phone),
        ]);
    }

    public function verifyCode(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'digits:6'],
        ], [
            'code.required' => 'Informe o código enviado pelo WhatsApp.',
            'code.digits' => 'O código deve conter 6 números.',
        ]);

        $accessCode = $this->pendingCode($request);

        if (! $accessCode) {
            return redirect()->route('access.request')->withErrors([
                'phone' => 'O código expirou. Solicite um novo para continuar.',
            ]);
        }

        $accessCode->increment('attempts');
        $accessCode->refresh();

        if ($accessCode->attempts > 5) {
            $accessCode->update(['used_at' => now()]);
            $this->clearAccessSession($request);

            return redirect()->route('access.request')->withErrors([
                'phone' => 'Muitas tentativas incorretas. Solicite um novo código.',
            ]);
        }

        if (! hash_equals($accessCode->code_hash, $this->hashCode($data['code']))) {
            return back()->withErrors(['code' => 'Código inválido. Tente novamente.']);
        }

        $accessCode->update(['verified_at' => now()]);
        $request->session()->put(self::VERIFIED_CODE_SESSION_KEY, $accessCode->id);

        return redirect()->route('access.password.form');
    }

    public function password(Request $request)
    {
        if (! $this->verifiedCode($request)) {
            return redirect()->route('access.request')->withErrors([
                'phone' => 'Confirme um novo código para cadastrar sua senha.',
            ]);
        }

        return view('auth.access', ['step' => 'password']);
    }

    public function storePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'password.required' => 'Crie uma senha para acessar sua conta.',
            'password.min' => 'A senha deve ter pelo menos 8 caracteres.',
            'password.confirmed' => 'A confirmação da senha não confere.',
        ]);

        $verifiedCode = $this->verifiedCode($request);

        if (! $verifiedCode) {
            return redirect()->route('access.request')->withErrors([
                'phone' => 'Sua confirmação expirou. Solicite um novo código.',
            ]);
        }

        DB::transaction(function () use ($verifiedCode, $data) {
            $lockedCode = ClientAccessCode::query()->lockForUpdate()->findOrFail($verifiedCode->id);

            if ($lockedCode->used_at || ! $lockedCode->verified_at || $lockedCode->expires_at->isPast()) {
                throw ValidationException::withMessages([
                    'password' => 'Sua confirmação expirou. Solicite um novo código.',
                ]);
            }

            $client = Client::query()->lockForUpdate()->findOrFail($lockedCode->client_id);
            $user = $client->user;

            if (! $user) {
                $user = User::query()
                    ->where('role', 'client')
                    ->where('cpf', $client->cpf)
                    ->first();
            }

            if ($user) {
                $user->update([
                    'name' => $client->name,
                    'phone' => $client->phone,
                    'role' => 'client',
                    'active' => true,
                    'password' => $data['password'],
                ]);
            } else {
                $user = User::create([
                    'name' => $client->name,
                    'email' => $this->availableEmail($client->email),
                    'login' => $this->availableLogin((string) $client->cpf, $client->id),
                    'cpf' => User::query()->where('cpf', $client->cpf)->exists() ? null : $client->cpf,
                    'phone' => $client->phone,
                    'role' => 'client',
                    'active' => true,
                    'password' => $data['password'],
                ]);
            }

            if ($client->user_id !== $user->id) {
                $client->update(['user_id' => $user->id]);
            }

            $lockedCode->update(['used_at' => now()]);
        });

        $this->clearAccessSession($request);
        $request->session()->regenerate();

        return redirect()->route('login')->with(
            'success',
            'Senha cadastrada com sucesso. Entre com seu CPF e a nova senha.',
        );
    }

    private function findClientByPhone(string $phone, WppConnectClient $wppConnect): ?Client
    {
        return Client::query()
            ->whereNotNull('phone')
            ->orderBy('id')
            ->get()
            ->first(function (Client $client) use ($phone, $wppConnect): bool {
                try {
                    return hash_equals($phone, $wppConnect->normalizePhone($client->phone));
                } catch (InvalidArgumentException) {
                    return false;
                }
            });
    }

    private function pendingCode(Request $request): ?ClientAccessCode
    {
        $codeId = $request->session()->get(self::CODE_SESSION_KEY);
        $clientId = $request->session()->get(self::CLIENT_SESSION_KEY);

        if (! $codeId || ! $clientId) {
            return null;
        }

        return ClientAccessCode::query()
            ->whereKey($codeId)
            ->where('client_id', $clientId)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    private function verifiedCode(Request $request): ?ClientAccessCode
    {
        $codeId = $request->session()->get(self::VERIFIED_CODE_SESSION_KEY);
        $clientId = $request->session()->get(self::CLIENT_SESSION_KEY);

        if (! $codeId || ! $clientId) {
            return null;
        }

        return ClientAccessCode::query()
            ->whereKey($codeId)
            ->where('client_id', $clientId)
            ->whereNotNull('verified_at')
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    private function clearAccessSession(Request $request): void
    {
        $request->session()->forget([
            self::CLIENT_SESSION_KEY,
            self::CODE_SESSION_KEY,
            self::VERIFIED_CODE_SESSION_KEY,
        ]);
    }

    private function hashCode(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    private function maskPhone(string $phone): string
    {
        return '•••• •••••-'.substr($phone, -4);
    }

    private function availableEmail(?string $email): ?string
    {
        if (blank($email) || User::query()->where('email', $email)->exists()) {
            return null;
        }

        return $email;
    }

    private function availableLogin(string $cpf, int $clientId): string
    {
        if ($cpf !== '' && ! User::query()->where('login', $cpf)->exists()) {
            return $cpf;
        }

        return 'cliente-'.$clientId;
    }
}
