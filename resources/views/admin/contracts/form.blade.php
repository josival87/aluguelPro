@extends('layouts.base', ['area'=>'admin'])
@section('title',($contract->exists?'Editar':'Novo').' contrato — AlugaPro')
@section('content')
<div class="page-head">
    <div><a style="color:var(--blue);font-weight:700" href="{{ route('admin.contracts.index') }}">← Contratos</a><h1 style="margin-top:8px">{{ $contract->exists?'Editar contrato-base':'Novo contrato-base' }}</h1><p>Use as variáveis abaixo; elas serão substituídas ao criar cada aluguel.</p></div>
    @if($contract->exists)<a class="btn btn-ghost" href="{{ route('admin.contracts.show',$contract) }}">Visualizar</a>@endif
</div>
<form id="contract-form" class="card form-card" method="post" action="{{ $contract->exists?route('admin.contracts.update',$contract):route('admin.contracts.store') }}">
    @csrf @if($contract->exists)@method('PUT')@endif
    <div class="form-grid">
        <div class="field span-2"><label>Título</label><input name="title" value="{{ old('title',$contract->title) }}" required placeholder="Ex.: Contrato residencial"></div>
        <div class="field span-2"><label class="check-pill"><input type="checkbox" name="active" value="1" @checked(old('active',$contract->exists?$contract->active:true))> Disponível para novos imóveis</label></div>
        <div class="field span-2">
            <label>Texto do contrato</label>
            <x-contract-editor-toolbar />
            @php($defaultContent='<h1>TÍTULO DO CONTRATO</h1><p>LOCATÁRIO: '.str_repeat('{',2).'nome_cliente'.str_repeat('}',2).', CPF '.str_repeat('{',2).'cpf_cliente'.str_repeat('}',2).'.</p>')
            <div id="rich-editor" class="rich-editor" contenteditable="true">{!! old('content',$contract->content ?: $defaultContent) !!}</div>
            <textarea id="contract-content" name="content" hidden></textarea>
        </div>
        <div class="field span-2"><label>Variáveis disponíveis</label><p class="field-help">Clique para inserir no ponto atual do editor.</p><div class="variable-list">@foreach($placeholders as $key=>$label)@php($token=str_repeat('{',2).$key.str_repeat('}',2))<button type="button" class="variable-chip" data-variable="{{ $key }}"><code>{{ $token }}</code><span>{{ $label }}</span></button>@endforeach</div></div>
    </div>
    <div class="form-actions"><a class="btn btn-ghost" href="{{ route('admin.contracts.index') }}">Cancelar</a><button class="btn">Salvar contrato-base</button></div>
</form>
@if($contract->exists)<form method="post" action="{{ route('admin.contracts.destroy',$contract) }}" onsubmit="return confirm('Excluir este contrato-base?')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm"><x-icon name="trash"/> Excluir</button></form>@endif
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded',()=>{
    const editor=document.querySelector('#rich-editor'),content=document.querySelector('#contract-content'),form=document.querySelector('#contract-form');
    document.querySelectorAll('[data-command]').forEach(button=>button.addEventListener('click',()=>{editor.focus();document.execCommand(button.dataset.command,false,null)}));
    document.querySelectorAll('[data-block]').forEach(button=>button.addEventListener('click',()=>{editor.focus();document.execCommand('formatBlock',false,button.dataset.block)}));
    document.querySelectorAll('[data-variable]').forEach(button=>button.addEventListener('click',()=>{editor.focus();document.execCommand('insertText',false,'{'.concat('{',button.dataset.variable,'}','}'))}));
    form.addEventListener('submit',()=>content.value=editor.innerHTML);
});
</script>
@endpush
@endsection
