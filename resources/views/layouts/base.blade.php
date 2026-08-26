<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0757e8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'AlugaPro')</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/pagination.css') }}">
    <link rel="stylesheet" href="{{ asset('css/client-area.css') }}">
    <link rel="stylesheet" href="{{ asset('css/contract-editor.css') }}">
    <link rel="stylesheet" href="{{ asset('css/contract-signature.css') }}">
    @stack('head')
</head>
@php($area = $area ?? 'public')
<body class="area-{{ $area }}">
@if($area === 'admin')
    <aside class="sidebar">
        <a class="brand brand-light" href="{{ route('admin.dashboard') }}"><span class="brand-mark">A</span><span>Aluga<strong>Pro</strong></span></a>
        <nav class="side-nav" aria-label="Menu administrativo">
            <a class="{{ request()->routeIs('admin.dashboard')?'active':'' }}" href="{{ route('admin.dashboard') }}"><x-icon name="dashboard"/> Dashboard</a>
            <details class="nav-details" {{ request()->routeIs('admin.charges.*')?'open':'' }}>
                <summary><span><x-icon name="calendar"/> Cobranças</span><x-icon name="chevron" size="16"/></summary>
                <a href="{{ route('admin.charges.index') }}">Todos os grupos</a>
                @foreach($navGroups as $navGroup)<a href="{{ route('admin.charges.index',['group'=>$navGroup->id]) }}">{{ $navGroup->name }}</a>@endforeach
            </details>
            <a class="{{ request()->routeIs('admin.assistant.*')?'active':'' }}" href="{{ route('admin.assistant.index') }}"><x-icon name="sparkles"/> Assistente IA</a>
            <a class="{{ request()->routeIs('admin.company.*')?'active':'' }}" href="{{ route('admin.company.edit') }}"><x-icon name="settings"/> Empresa</a>
            <a class="{{ request()->routeIs('admin.whatsapp.*')?'active':'' }}" href="{{ route('admin.whatsapp.index') }}"><x-icon name="whatsapp"/> WhatsApp</a>
            <a class="{{ request()->routeIs('admin.users.*')?'active':'' }}" href="{{ route('admin.users.index') }}"><x-icon name="user"/> Usuários</a>
            <a class="{{ request()->routeIs('admin.groups.*')?'active':'' }}" href="{{ route('admin.groups.index') }}"><x-icon name="building"/> Grupos</a>
            <a class="{{ request()->routeIs('admin.clients.*')?'active':'' }}" href="{{ route('admin.clients.index') }}"><x-icon name="users"/> Clientes</a>
            <a class="{{ request()->routeIs('admin.properties.*')?'active':'' }}" href="{{ route('admin.properties.index') }}"><x-icon name="home"/> Imóveis</a>
            <a class="{{ request()->routeIs('admin.contracts.*')?'active':'' }}" href="{{ route('admin.contracts.index') }}"><x-icon name="file"/> Contratos</a>
            <a class="{{ request()->routeIs('admin.leases.*')?'active':'' }}" href="{{ route('admin.leases.index') }}"><x-icon name="key"/> Aluguéis</a>
            <a class="{{ request()->routeIs('admin.solar.*')?'active':'' }}" href="{{ route('admin.solar.create') }}"><x-icon name="sun"/> Medição solar</a>
            <a class="{{ request()->routeIs('admin.features.*')?'active':'' }}" href="{{ route('admin.features.index') }}"><x-icon name="tag"/> Características</a>
        </nav>
        <form method="post" action="{{ route('logout') }}" class="sidebar-user">@csrf<button><span class="avatar">{{ mb_substr(auth()->user()->name,0,1) }}</span><span>{{ auth()->user()->name }}<small>Sair da conta</small></span><x-icon name="logout"/></button></form>
    </aside>
    <header class="mobile-top">
        <a class="brand" href="{{ route('admin.dashboard') }}"><span class="brand-mark">A</span>Aluga<strong>Pro</strong></a>
        <details class="mobile-account-menu">
            <summary aria-label="Abrir menu da conta">
                <span class="avatar" aria-hidden="true">{{ mb_substr(auth()->user()->name,0,1) }}</span>
            </summary>
            <div class="mobile-account-popover">
                <div class="mobile-account-user">
                    <strong>{{ auth()->user()->name }}</strong>
                    <small>Área administrativa</small>
                </div>
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"><x-icon name="logout"/> Sair</button>
                </form>
            </div>
        </details>
    </header>
@elseif($area === 'client')
    <header class="client-top">
        <a class="brand" href="{{ route('client.dashboard') }}"><span class="brand-mark">A</span>Aluga<strong>Pro</strong></a>
        <div class="client-top-actions">
            <div class="client-greeting"><small>Olá,</small> {{ auth()->user()->name }}</div>
            <a class="btn btn-outline btn-sm client-profile-link" href="{{ route('client.profile.edit') }}"><x-icon name="user"/><span>Dados pessoais</span></a>
            <form class="client-desktop-logout" method="post" action="{{ route('logout') }}">
                @csrf
                <button class="btn btn-outline btn-sm" type="submit"><x-icon name="logout"/><span>Sair</span></button>
            </form>
        </div>
    </header>
@else
    @php($accountRoute = auth()->check() ? (auth()->user()->role === 'client' ? route('client.dashboard') : route('admin.dashboard')) : route('login'))
    <header class="public-header"><a class="brand" href="{{ route('properties.index') }}"><span class="brand-mark">A</span>Aluga<strong>Pro</strong></a><nav><a href="{{ route('properties.index') }}">Encontrar imóvel</a><a class="btn btn-outline btn-sm" href="{{ $accountRoute }}">{{ auth()->check() ? 'Minha área' : 'Entrar' }}</a></nav></header>
@endif

<main class="main {{ $area === 'admin' ? 'admin-main' : '' }}">
    @if(session('success'))<div class="alert alert-success"><x-icon name="check"/> {{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><strong>Revise os dados:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @yield('content')
</main>

@if($area === 'admin')
<nav class="bottom-nav" aria-label="Navegação principal">
    <a class="{{ request()->routeIs('admin.dashboard')?'active':'' }}" href="{{ route('admin.dashboard') }}"><x-icon name="dashboard"/><span>Início</span></a>
    <a class="{{ request()->routeIs('admin.properties.*')?'active':'' }}" href="{{ route('admin.properties.index') }}"><x-icon name="home"/><span>Imóveis</span></a>
    <a class="{{ request()->routeIs('admin.leases.*')?'active':'' }}" href="{{ route('admin.leases.index') }}"><x-icon name="key"/><span>Aluguéis</span></a>
    <a class="{{ request()->routeIs('admin.charges.*')?'active':'' }}" href="{{ route('admin.charges.index') }}"><x-icon name="calendar"/><span>Cobranças</span></a>
    <a class="{{ request()->routeIs('admin.assistant.*')?'active':'' }}" href="{{ route('admin.assistant.index') }}"><x-icon name="sparkles"/><span>Assistente</span></a>
</nav>
@elseif($area === 'client' && auth()->check())
<nav class="bottom-nav client-nav"><a class="{{ request()->routeIs('client.dashboard')?'active':'' }}" href="{{ route('client.dashboard') }}"><x-icon name="home"/><span>Início</span></a><a href="{{ route('client.dashboard') }}#cobrancas"><x-icon name="money"/><span>Pagamentos</span></a><a href="{{ route('client.dashboard') }}#contratos"><x-icon name="file"/><span>Contratos</span></a><a class="{{ request()->routeIs('client.profile.*', 'client.documents.*')?'active':'' }}" href="{{ route('client.profile.edit') }}"><x-icon name="user"/><span>Dados</span></a><form method="post" action="{{ route('logout') }}">@csrf<button><x-icon name="logout"/><span>Sair</span></button></form></nav>
@endif
<script src="{{ asset('js/app.js') }}" defer></script>
@stack('scripts')
</body>
</html>
