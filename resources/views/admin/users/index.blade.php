@extends('layouts.base', ['area' => 'admin'])

@section('title', 'Usuários — AlugaPro')

@section('content')
<div class="page-head"><div><h1>Usuários</h1><p>Equipe com acesso à área administrativa.</p></div><a class="btn" href="{{ route('admin.users.create') }}"><x-icon name="plus"/> <span>Novo usuário</span></a></div>
<div class="table-wrap"><table class="responsive"><thead><tr><th>Nome</th><th>Login</th><th>Nível</th><th>Acesso</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($users as $user)
    <tr>
        <td data-label="Nome"><strong>{{ $user->name }}</strong><small style="display:block;color:var(--muted)">{{ $user->email }}</small></td>
        <td data-label="Login">{{ $user->login }}</td>
        <td data-label="Nível">{{ $user->role === 'admin' ? 'Administrador' : 'Gerente' }}</td>
        <td data-label="Acesso">{{ $user->group?->name ?? 'Todos os grupos' }}</td>
        <td data-label="Status"><x-status :value="$user->active ? 'active' : 'inactive'"/></td>
        <td><div class="actions"><a class="icon-btn" href="{{ route('admin.users.edit', $user) }}"><x-icon name="edit" size="17"/></a>@if(!$user->is(auth()->user()))<form method="post" action="{{ route('admin.users.destroy', $user) }}" data-confirm="Excluir este usuário?">@csrf @method('DELETE')<button class="icon-btn"><x-icon name="trash" size="17"/></button></form>@endif</div></td>
    </tr>
@empty
    <tr><td colspan="6" class="empty">Nenhum usuário encontrado.</td></tr>
@endforelse
</tbody></table></div><div class="pagination">{{ $users->links() }}</div>
@endsection
