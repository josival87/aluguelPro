<?php

use App\Http\Controllers\Admin\AdminAssistantController;
use App\Http\Controllers\Admin\ChargeController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\ClientDocumentController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\ContractController as AdminContractController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FeatureController;
use App\Http\Controllers\Admin\GroupController;
use App\Http\Controllers\Admin\LeaseController;
use App\Http\Controllers\Admin\LeaseDocumentController;
use App\Http\Controllers\Admin\PropertyController;
use App\Http\Controllers\Admin\PropertyMediaController as AdminPropertyMediaController;
use App\Http\Controllers\Admin\SolarReadingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WhatsAppController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientPortalController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\PropertyMediaController;
use App\Http\Controllers\PublicPropertyController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicPropertyController::class, 'index'])->name('properties.index');
Route::get('/imoveis/{property}', [PublicPropertyController::class, 'show'])->name('properties.show');
Route::get('/imoveis/{property}/alugar', [PublicPropertyController::class, 'application'])->name('properties.application');
Route::post('/imoveis/{property}/alugar', [PublicPropertyController::class, 'apply'])->middleware('throttle:5,1')->name('properties.apply');
Route::get('/midias-imoveis/{propertyMedia}', [PropertyMediaController::class, 'show'])->name('property-media.show');

Route::middleware('guest')->group(function () {
    Route::get('/entrar', [AuthController::class, 'create'])->name('login');
    Route::post('/entrar', [AuthController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
});
Route::post('/sair', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'role:admin,manager'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/assistente/{conversation?}', [AdminAssistantController::class, 'index'])->name('assistant.index');
    Route::post('/assistente/mensagens', [AdminAssistantController::class, 'store'])->middleware('throttle:20,1')->name('assistant.messages');
    Route::get('/empresa', [CompanyController::class, 'edit'])->name('company.edit');
    Route::put('/empresa', [CompanyController::class, 'update'])->name('company.update');
    Route::get('/whatsapp', [WhatsAppController::class, 'index'])->name('whatsapp.index');
    Route::put('/whatsapp', [WhatsAppController::class, 'update'])->name('whatsapp.update');
    Route::put('/whatsapp/automacoes', [WhatsAppController::class, 'updateAutomations'])->name('whatsapp.automations.update');
    Route::post('/whatsapp/conectar', [WhatsAppController::class, 'connect'])->middleware('throttle:10,1')->name('whatsapp.connect');
    Route::get('/whatsapp/status', [WhatsAppController::class, 'status'])->middleware('throttle:60,1')->name('whatsapp.status');
    Route::post('/whatsapp/teste/texto', [WhatsAppController::class, 'sendText'])->middleware('throttle:10,1')->name('whatsapp.test.text');
    Route::post('/whatsapp/teste/imagem', [WhatsAppController::class, 'sendImage'])->middleware('throttle:10,1')->name('whatsapp.test.image');
    Route::resource('usuarios', UserController::class)->parameters(['usuarios' => 'user'])->names('users')->except('show');
    Route::resource('grupos', GroupController::class)->parameters(['grupos' => 'group'])->names('groups')->except('show');
    Route::resource('clientes', ClientController::class)->parameters(['clientes' => 'client'])->names('clients');
    Route::get('/clientes/{client}/documentos/{document}', [ClientDocumentController::class, 'show'])->name('clients.documents.show');
    Route::delete('/clientes/{client}/documentos/{document}', [ClientDocumentController::class, 'destroy'])->name('clients.documents.destroy');
    Route::resource('imoveis', PropertyController::class)->parameters(['imoveis' => 'property'])->names('properties');
    Route::post('/imoveis/{property}/midias', [AdminPropertyMediaController::class, 'store'])->name('properties.media.store');
    Route::delete('/imoveis/{property}/midias/{propertyMedia}', [AdminPropertyMediaController::class, 'destroy'])->name('properties.media.destroy');
    Route::resource('contratos', AdminContractController::class)->parameters(['contratos' => 'contract'])->names('contracts');
    Route::resource('alugueis', LeaseController::class)->parameters(['alugueis' => 'lease'])->names('leases');
    Route::post('/alugueis/{lease}/documentos', [LeaseDocumentController::class, 'store'])->name('leases.documents.store');
    Route::get('/alugueis/{lease}/documentos/{document}/baixar', [LeaseDocumentController::class, 'download'])->name('leases.documents.download');
    Route::delete('/alugueis/{lease}/documentos/{document}', [LeaseDocumentController::class, 'destroy'])->name('leases.documents.destroy');
    Route::get('/caracteristicas', [FeatureController::class, 'index'])->name('features.index');
    Route::post('/caracteristicas', [FeatureController::class, 'store'])->name('features.store');
    Route::put('/caracteristicas/{feature}', [FeatureController::class, 'update'])->name('features.update');
    Route::delete('/caracteristicas/{feature}', [FeatureController::class, 'destroy'])->name('features.destroy');
    Route::get('/cobrancas', [ChargeController::class, 'index'])->name('charges.index');
    Route::post('/cobrancas/gerar', [ChargeController::class, 'generate'])->name('charges.generate');
    Route::post('/cobrancas/{charge}/pix', [ChargeController::class, 'pix'])->name('charges.pix');
    Route::patch('/cobrancas/{charge}/pagar', [ChargeController::class, 'paid'])->name('charges.paid');
    Route::patch('/cobrancas/{charge}/reabrir', [ChargeController::class, 'reopen'])->name('charges.reopen');
    Route::get('/medicao-solar', [SolarReadingController::class, 'create'])->name('solar.create');
    Route::post('/medicao-solar/analisar', [SolarReadingController::class, 'analyze'])->middleware('throttle:10,1')->name('solar.analyze');
    Route::post('/medicao-solar', [SolarReadingController::class, 'store'])->name('solar.store');
    Route::post('/alugueis/{lease}/contrato', [ContractController::class, 'generate'])->name('contracts.generate');
    Route::get('/alugueis/{lease}/contrato/editar', [ContractController::class, 'editFinal'])->name('leases.contract.edit');
    Route::put('/alugueis/{lease}/contrato', [ContractController::class, 'updateFinal'])->name('leases.contract.update');
    Route::post('/alugueis/{lease}/contrato/finalizar', [ContractController::class, 'finalize'])->name('leases.contract.finalize');
    Route::post('/alugueis/{lease}/contrato/assinaturas', [ContractController::class, 'requestSignatures'])->name('leases.contract.signatures');
});

Route::middleware(['auth', 'role:client'])->prefix('cliente')->name('client.')->group(function () {
    Route::get('/', [ClientPortalController::class, 'dashboard'])->name('dashboard');
    Route::get('/alugueis/{lease}', [ClientPortalController::class, 'lease'])->name('lease');
    Route::get('/cobrancas/{charge}', [ClientPortalController::class, 'charge'])->name('charge');
    Route::post('/cobrancas/{charge}/pix', [ClientPortalController::class, 'pix'])->name('pix');
});

Route::middleware('auth')->group(function () {
    Route::get('/contratos/{contract}', [ContractController::class, 'show'])->name('contracts.show');
    Route::post('/contratos/{contract}/codigo', [ContractController::class, 'sendOtp'])->middleware('throttle:3,1')->name('contracts.otp');
    Route::post('/contratos/{contract}/assinar', [ContractController::class, 'sign'])->middleware('throttle:10,1')->name('contracts.sign');
});
