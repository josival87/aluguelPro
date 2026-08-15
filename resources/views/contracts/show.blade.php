@extends('layouts.base', ['area'=>auth()->user()->role==='client'?'client':'admin'])
@section('title','Contrato #'.$contract->id.' — AlugaPro')
@section('content')
@php
    $myType = auth()->user()->role === 'client' ? 'client' : 'responsible';
    $tenantSignature = $contract->signatures->firstWhere('signer_type', 'client');
    $adminSignature = $contract->signatures->firstWhere('signer_type', 'responsible');
    $mySignature = $myType === 'client' ? $tenantSignature : $adminSignature;
    $adminWaitingForTenant = $myType === 'responsible' && ! $tenantSignature;
@endphp

<div class="page-head">
    <div>
        <h1>Contrato #{{ $contract->id }}</h1>
        <p>{{ $contract->template?->title }} · {{ $contract->lease->property->title }} · Hash {{ substr($contract->content_hash,0,12) }}…</p>
    </div>
    <x-status :value="$contract->status"/>
</div>

<div class="contract-paper">
    {!! $contract->final_content !!}

    <div class="signature-grid">
        @foreach(['client'=>'Locatário','responsible'=>'Administrador'] as $type=>$label)
            @php($signature=$contract->signatures->firstWhere('signer_type',$type))
            <div class="signature-box">
                <strong>{{ $label }}</strong>
                @if($signature)
                    <p style="color:var(--green)"><x-icon name="check"/> Assinado por {{ $signature->signer_name }}</p>
                    <small>
                        {{ $signature->signed_at->format('d/m/Y H:i:s') }} · OTP via WhatsApp<br>
                        Evidência {{ substr($signature->evidence_hash,0,14) }}…
                    </small>
                    @if($type === 'client' && $signature->photo_base64)
                        <figure class="signature-photo-evidence">
                            <img src="{{ $signature->photo_base64 }}" alt="Foto do locatário registrada na assinatura">
                            <figcaption>Foto autenticada · SHA-256 {{ substr($signature->photo_sha256,0,14) }}…</figcaption>
                        </figure>
                    @endif
                @else
                    <p style="color:var(--muted)">Aguardando assinatura</p>
                @endif
            </div>
        @endforeach
    </div>
</div>

@if($contract->status === 'awaiting_signatures' && ! $mySignature)
    <section class="card signing-card">
        @if($adminWaitingForTenant)
            <h2>Etapa 2 · Assinatura do administrador</h2>
            <div class="alert signing-wait">
                <x-icon name="camera"/>
                <span>O locatário precisa confirmar o código do WhatsApp e registrar a foto antes da assinatura do administrador ser liberada.</span>
            </div>
        @else
            <h2>{{ $myType === 'client' ? 'Etapa 1 · Assinatura do locatário' : 'Etapa 2 · Assinatura do administrador' }}</h2>
            <p>
                Enviaremos um código de 6 dígitos para o WhatsApp cadastrado.
                @if($myType === 'client')
                    Para confirmar, também será obrigatória uma foto atual do locatário.
                @else
                    A foto e a assinatura do locatário já foram registradas; sua assinatura concluirá o contrato.
                @endif
            </p>

            @if(session('dev_otp'))
                <div class="alert" style="background:#fff6db;color:#805800">
                    <strong>Ambiente local:</strong> código {{ session('dev_otp') }}
                </div>
            @endif

            <div class="grid-2 signing-actions">
                <form method="post" action="{{ route('contracts.otp',$contract) }}">
                    @csrf
                    <button class="btn btn-outline btn-block">Enviar código por WhatsApp</button>
                </form>

                <form method="post" action="{{ route('contracts.sign',$contract) }}" class="stack" enctype="multipart/form-data">
                    @csrf
                    <div class="field">
                        <label for="signature-code">Código recebido</label>
                        <input id="signature-code" name="code" value="{{ old('code') }}" inputmode="numeric" maxlength="6" required>
                    </div>

                    @if($myType === 'client')
                        <div class="field">
                            <label for="tenant-photo">Foto atual do locatário</label>
                            <input id="tenant-photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp" capture="user" required>
                            <small>Use a câmera frontal ou selecione uma foto nítida. JPG, PNG ou WebP, até 5 MB.</small>
                            <img id="tenant-photo-preview" class="signature-photo-preview" alt="Prévia da foto do locatário" hidden>
                        </div>
                    @endif

                    <label class="check-pill">
                        <input type="checkbox" name="accepted" value="1" required>
                        Li e aceito o contrato
                    </label>
                    <button class="btn btn-block">
                        {{ $myType === 'client' ? 'Confirmar assinatura e foto' : 'Assinar e concluir contrato' }}
                    </button>
                </form>
            </div>
        @endif
    </section>
@elseif($contract->status === 'awaiting_signatures' && $myType === 'client' && $tenantSignature)
    <section class="card signing-card">
        <div class="alert signing-success">
            <x-icon name="check"/>
            <span>Sua assinatura e foto foram registradas. O contrato aguarda a assinatura final do administrador.</span>
        </div>
    </section>
@endif

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded',()=>{
    const input=document.querySelector('#tenant-photo'),preview=document.querySelector('#tenant-photo-preview');
    if(!input||!preview)return;
    let previewUrl;
    input.addEventListener('change',()=>{
        if(previewUrl)URL.revokeObjectURL(previewUrl);
        const file=input.files?.[0];
        if(!file){preview.hidden=true;preview.removeAttribute('src');return}
        previewUrl=URL.createObjectURL(file);
        preview.src=previewUrl;
        preview.hidden=false;
    });
});
</script>
@endpush
@endsection
