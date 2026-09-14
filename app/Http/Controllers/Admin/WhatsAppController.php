<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\MetaWhatsAppException;
use App\Http\Controllers\Controller;
use App\Models\WhatsAppAutomation;
use App\Models\WhatsAppSetting;
use App\Services\MetaWhatsAppClient;
use App\Services\WhatsAppService;
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
            'templateEvents' => WhatsAppSetting::TEMPLATE_EVENTS,
        ]);
    }

    public function updateAutomations(Request $request): RedirectResponse
    {
        $automationKeys = array_keys(WhatsAppAutomation::DEFINITIONS);
        $templateKeys = array_merge($automationKeys, array_keys(WhatsAppSetting::TEMPLATE_EVENTS));
        $rules = [
            'messages' => ['required', 'array:'.implode(',', $automationKeys)],
            'templates' => ['nullable', 'array'],
        ];

        foreach ($automationKeys as $key) {
            $rules["messages.{$key}"] = ['required', 'string', 'max:4096'];
        }
        foreach ($templateKeys as $key) {
            $rules["templates.{$key}.name"] = ['nullable', 'string', 'max:512', 'regex:/^[a-z0-9_]+$/'];
            $rules["templates.{$key}.language"] = ['nullable', 'string', 'max:20', 'regex:/^[a-z]{2,3}(?:_[A-Z]{2})?$/'];
        }

        $data = $request->validate($rules, [
            'templates.*.name.regex' => 'O nome do modelo deve usar somente letras minúsculas, números e sublinhado.',
            'templates.*.language.regex' => 'Informe um idioma no formato pt_BR ou en_US.',
        ]);

        foreach ($automationKeys as $key) {
            WhatsAppAutomation::query()->updateOrCreate(
                ['key' => $key],
                ['message' => $data['messages'][$key]],
            );
        }

        $templates = [];
        foreach ($templateKeys as $key) {
            $name = data_get($data, "templates.{$key}.name");
            if (filled($name)) {
                $templates[$key] = [
                    'name' => $name,
                    'language' => data_get($data, "templates.{$key}.language") ?: 'pt_BR',
                ];
            }
        }

        $setting = WhatsAppSetting::current();
        $setting->message_templates = $templates;
        $setting->save();

        return back()->with('success', 'Mensagens e modelos da Meta atualizados.');
    }

    public function update(Request $request): RedirectResponse
    {
        $setting = WhatsAppSetting::current();
        $data = $request->validate([
            'graph_api_version' => ['required', 'string', 'max:20', 'regex:/^v[0-9]+\.[0-9]+$/'],
            'phone_number_id' => ['required', 'digits_between:5,30'],
            'business_account_id' => ['required', 'digits_between:5,30'],
            'access_token' => [Rule::requiredIf(blank($setting->access_token)), 'nullable', 'string', 'max:10000'],
            'app_secret' => ['nullable', 'string', 'max:500'],
            'webhook_verify_token' => ['nullable', 'string', 'min:16', 'max:500'],
        ], [
            'graph_api_version.regex' => 'Informe a versão no formato v26.0.',
            'access_token.required' => 'Informe o token de acesso da Meta.',
            'webhook_verify_token.min' => 'Use pelo menos 16 caracteres no token de verificação do webhook.',
        ]);

        $newAccessToken = filled($data['access_token'] ?? null) ? $data['access_token'] : $setting->access_token;
        $newAppSecret = filled($data['app_secret'] ?? null) ? $data['app_secret'] : $setting->app_secret;
        $newVerifyToken = filled($data['webhook_verify_token'] ?? null)
            ? $data['webhook_verify_token']
            : $setting->webhook_verify_token;
        $connectionChanged = ! $setting->exists
            || $setting->graph_api_version !== $data['graph_api_version']
            || $setting->phone_number_id !== $data['phone_number_id']
            || $setting->business_account_id !== $data['business_account_id']
            || (filled($data['access_token'] ?? null) && $setting->access_token !== $data['access_token'])
            || (filled($data['app_secret'] ?? null) && $setting->app_secret !== $data['app_secret']);

        $setting->fill([
            'graph_api_version' => $data['graph_api_version'],
            'phone_number_id' => $data['phone_number_id'],
            'business_account_id' => $data['business_account_id'],
            'access_token' => $newAccessToken,
            'app_secret' => $newAppSecret,
            'webhook_verify_token' => $newVerifyToken,
        ]);

        if ($connectionChanged) {
            $setting->forceFill([
                'connected_phone' => null,
                'connection_status' => 'configured',
                'last_error' => null,
                'last_connected_at' => null,
                'webhook_subscribed_at' => null,
            ]);
        }

        $setting->save();

        return back()->with('success', 'Credenciais da WhatsApp Cloud API salvas. Agora valide a integração.');
    }

    public function verify(MetaWhatsAppClient $client): JsonResponse
    {
        try {
            return response()->json($client->verifyConfiguration());
        } catch (MetaWhatsAppException $exception) {
            $this->recordFailure($exception);

            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function sendTemplate(Request $request, WhatsAppService $whatsApp): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9 ()-]{8,20}$/'],
            'template_name' => ['required', 'string', 'max:512', 'regex:/^[a-z0-9_]+$/'],
            'language_code' => ['required', 'string', 'max:20', 'regex:/^[a-z]{2,3}(?:_[A-Z]{2})?$/'],
        ]);

        $log = $whatsApp->sendTemplate(
            $data['phone'],
            $data['template_name'],
            $data['language_code'],
            [],
            'Modelo Meta: '.$data['template_name'],
            'admin_test_template',
            'test',
        );

        return $this->deliveryResponse($log->status, $log->error, 'Modelo de teste aceito pela Meta.');
    }

    public function sendText(Request $request, WhatsAppService $whatsApp): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9 ()-]{8,20}$/'],
            'message' => ['required', 'string', 'max:4096'],
        ]);

        $log = $whatsApp->sendText(
            $data['phone'],
            $data['message'],
            'admin_test_text',
            'test',
        );

        return $this->deliveryResponse($log->status, $log->error, 'Mensagem de teste aceita pela Meta.');
    }

    public function sendImage(Request $request, WhatsAppService $whatsApp): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9 ()-]{8,20}$/'],
            'caption' => ['nullable', 'string', 'max:1024'],
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
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
            null,
            $file->getMimeType(),
        );

        return $this->deliveryResponse($log->status, $log->error, 'Imagem de teste aceita pela Meta.');
    }

    private function deliveryResponse(string $status, ?string $error, string $success): RedirectResponse
    {
        if ($status === 'sent') {
            return back()->with('success', $success);
        }

        $message = $status === 'simulated'
            ? 'Configure e valide a integração com a Meta antes de testar o envio.'
            : ($error ?: 'A Meta não confirmou o envio.');

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
