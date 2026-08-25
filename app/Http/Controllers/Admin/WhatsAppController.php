<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\WppConnectException;
use App\Http\Controllers\Controller;
use App\Models\WhatsAppAutomation;
use App\Models\WhatsAppSetting;
use App\Services\WhatsAppService;
use App\Services\WppConnectClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class WhatsAppController extends Controller
{
    public function index(): View
    {
        return view('admin.whatsapp.index', [
            'setting' => WhatsAppSetting::current(),
            'automations' => WhatsAppAutomation::configured(),
        ]);
    }

    public function updateAutomations(Request $request): RedirectResponse
    {
        $keys = array_keys(WhatsAppAutomation::DEFINITIONS);
        $rules = ['messages' => ['required', 'array:'.implode(',', $keys)]];

        foreach ($keys as $key) {
            $rules["messages.{$key}"] = ['required', 'string', 'max:4096'];
        }

        $messages = $request->validate($rules)['messages'];

        foreach ($keys as $key) {
            WhatsAppAutomation::query()->updateOrCreate(
                ['key' => $key],
                ['message' => $messages[$key]],
            );
        }

        return back()->with('success', 'Mensagens automáticas do WhatsApp atualizadas.');
    }

    public function update(Request $request): RedirectResponse
    {
        $setting = WhatsAppSetting::current();
        $data = $request->validate([
            'api_url' => ['required', 'url:http,https', 'max:500'],
            'session_name' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/'],
            'secret_key' => [Rule::requiredIf(blank($setting->secret_key)), 'nullable', 'string', 'max:2000'],
        ], [
            'session_name.regex' => 'O nome da sessão aceita somente letras, números, hífen e sublinhado.',
            'secret_key.required' => 'Informe a secret key do servidor WPPConnect.',
        ]);

        $apiUrl = rtrim($data['api_url'], '/');
        $newSecret = filled($data['secret_key'] ?? null) ? $data['secret_key'] : $setting->secret_key;
        $connectionChanged = ! $setting->exists
            || $setting->api_url !== $apiUrl
            || $setting->session_name !== $data['session_name']
            || (filled($data['secret_key'] ?? null) && $setting->secret_key !== $data['secret_key']);

        $setting->fill([
            'api_url' => $apiUrl,
            'session_name' => $data['session_name'],
            'secret_key' => $newSecret,
        ]);

        if ($connectionChanged) {
            $setting->forceFill([
                'api_token' => null,
                'connected_phone' => null,
                'connection_status' => 'configured',
                'last_error' => null,
                'last_connected_at' => null,
            ]);
        }

        $setting->save();

        return back()->with('success', 'Configuração do WPPConnect salva. Agora inicie a conexão pelo QR Code.');
    }

    public function connect(WppConnectClient $client): JsonResponse
    {
        try {
            return response()->json($client->connect());
        } catch (WppConnectException $exception) {
            $this->recordFailure($exception);

            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function status(WppConnectClient $client): JsonResponse
    {
        try {
            return response()->json($client->status());
        } catch (WppConnectException $exception) {
            $this->recordFailure($exception);

            return response()->json([
                'connected' => false,
                'status' => 'error',
                'phone' => null,
                'qr_code' => null,
                'message' => $exception->getMessage(),
            ], 502);
        }
    }

    public function sendText(Request $request, WhatsAppService $whatsApp): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9 ()-]{10,20}$/'],
            'message' => ['required', 'string', 'max:4096'],
        ]);

        $log = $whatsApp->sendText(
            $data['phone'],
            $data['message'],
            'admin_test_text',
            'test',
        );

        return $this->deliveryResponse($log->status, $log->error, 'Mensagem de teste enviada pelo WPPConnect.');
    }

    public function sendImage(Request $request, WhatsAppService $whatsApp): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9 ()-]{10,20}$/'],
            'caption' => ['nullable', 'string', 'max:4096'],
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        $file = $request->file('image');
        $contents = file_get_contents($file->getRealPath());
        abort_if($contents === false, 422, 'Não foi possível ler a imagem enviada.');

        $log = $whatsApp->sendImage(
            $data['phone'],
            $contents,
            $file->getClientOriginalName(),
            $data['caption'] ?? '',
            'admin_test_image',
            'test',
        );

        return $this->deliveryResponse($log->status, $log->error, 'Imagem de teste enviada pelo WPPConnect.');
    }

    private function deliveryResponse(string $status, ?string $error, string $success): RedirectResponse
    {
        if ($status === 'sent') {
            return back()->with('success', $success);
        }

        $message = $status === 'simulated'
            ? 'Configure e conecte o WPPConnect antes de testar o envio.'
            : ($error ?: 'O WPPConnect não confirmou o envio.');

        return back()->withErrors(['whatsapp' => $message]);
    }

    private function recordFailure(Throwable $exception): void
    {
        $setting = WhatsAppSetting::current();
        if (! $setting->exists) {
            return;
        }

        $setting->forceFill([
            'connection_status' => 'error',
            'last_error' => $exception->getMessage(),
        ])->save();
    }
}
