@extends('layouts.base', ['area'=>'admin'])
@section('title','Editar contrato do aluguel #'.$lease->id.' — AlugaPro')
@section('content')
<div class="page-head"><div><a style="color:var(--blue);font-weight:700" href="{{ route('admin.leases.show',$lease) }}">← Aluguel #{{ $lease->id }}</a><h1 style="margin-top:8px">Ajustes finais do contrato</h1><p>Modelo: {{ $contract->template?->title }} · esta versão pertence somente a este aluguel.</p></div><x-status :value="$contract->status"/></div>
<div class="alert" style="background:#eef5ff;color:#173d7a"><x-icon name="file"/> As variáveis do contrato-base já foram substituídas pelos dados atuais do cliente e do imóvel. Edite livremente antes de finalizar.</div>
<section class="card form-card">
    <x-contract-editor-toolbar />
    <div id="rich-editor" class="rich-editor rich-editor-large" contenteditable="true">{!! old('final_content',$contract->final_content) !!}</div>
    <div class="form-actions">
        <form id="save-contract" method="post" action="{{ route('admin.leases.contract.update',$lease) }}">@csrf @method('PUT')<textarea name="final_content" hidden></textarea><button class="btn btn-outline"><x-icon name="edit"/> Salvar rascunho</button></form>
        <form id="finalize-contract" method="post" action="{{ route('admin.leases.contract.finalize',$lease) }}" onsubmit="return confirm('Finalizar e bloquear este contrato para alterações?')">@csrf<textarea name="final_content" hidden></textarea><button class="btn"><x-icon name="check"/> Finalizar contrato</button></form>
    </div>
</section>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded',()=>{
    const editor=document.querySelector('#rich-editor');
    document.querySelectorAll('[data-command]').forEach(button=>button.addEventListener('click',()=>{editor.focus();document.execCommand(button.dataset.command,false,null)}));
    document.querySelectorAll('[data-block]').forEach(button=>button.addEventListener('click',()=>{editor.focus();document.execCommand('formatBlock',false,button.dataset.block)}));
    document.querySelectorAll('#save-contract,#finalize-contract').forEach(form=>form.addEventListener('submit',()=>form.querySelector('textarea').value=editor.innerHTML));
});
</script>
@endpush
@endsection
