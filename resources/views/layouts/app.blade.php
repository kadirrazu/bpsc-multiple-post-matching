<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#2e7165">
    <title>@yield('title', 'Post Matching') | {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body>
<div class="page">
    <header class="matching-header d-print-none">
        <div class="container-xl matching-masthead">
            <a href="{{ route('matching.index') }}" class="matching-product text-decoration-none">
                <span class="brand-emblem"><i class="bi bi-intersect" aria-hidden="true"></i></span>
                <span class="product-name">BPSC Multiple Post Matching System</span>
            </a>
            <div class="matching-commission" lang="en"><strong>Bangladesh Public Service Commission</strong> (BPSC)</div>
        </div>
        <nav class="navbar navbar-expand-md matching-navigation" aria-label="Main navigation">
            <div class="container-xl">
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbar-menu" aria-controls="navbar-menu" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
                <div class="navbar-nav flex-row order-md-last align-items-center gap-2 matching-account">
                    <div class="nav-item dropdown">
                        <button type="button" class="nav-link dropdown-toggle border-0 bg-transparent" data-bs-toggle="dropdown" aria-expanded="false" aria-label="User menu"><span class="avatar avatar-sm">{{ mb_substr(auth()->user()->name, 0, 1) }}</span><span class="d-none d-lg-block ps-2">{{ auth()->user()->name }}</span></button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <div class="px-3 py-2"><strong>{{ auth()->user()->name }}</strong><div class="small text-secondary">{{ auth()->user()->designation?->name ?? 'Not assigned' }}</div></div>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="{{ route('staff-profile.edit') }}"><i class="bi bi-person-circle" aria-hidden="true"></i>My Profile</a>
                            <a class="dropdown-item" href="{{ route('staff-profile.password') }}"><i class="bi bi-key" aria-hidden="true"></i>Change Password</a>
                            <form action="{{ route('logout') }}" method="post">@csrf<button type="submit" class="dropdown-item"><i class="bi bi-box-arrow-right" aria-hidden="true"></i>Sign out</button></form>
                        </div>
                    </div>
                </div>
                <div class="collapse navbar-collapse" id="navbar-menu">
                    <ul class="navbar-nav gap-md-2">
                        <li class="nav-item {{ request()->routeIs('matching.*') ? 'active' : '' }}"><a class="nav-link" href="{{ route('matching.index') }}" @if(request()->routeIs('matching.*')) aria-current="page" @endif><i class="bi bi-files" aria-hidden="true"></i>Files &amp; Matching</a></li>
                        @if(auth()->user()->role === \App\Enums\UserRole::Admin)
                        <li class="nav-item dropdown {{ request()->routeIs('users.*') ? 'active' : '' }}">
                            <button class="nav-link dropdown-toggle border-0 bg-transparent" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-people" aria-hidden="true"></i>User Management</button>
                            <div class="dropdown-menu"><a class="dropdown-item" href="{{ route('users.index') }}"><i class="bi bi-people" aria-hidden="true"></i>Users</a><a class="dropdown-item" href="{{ route('users.create') }}"><i class="bi bi-person-plus" aria-hidden="true"></i>Add User</a></div>
                        </li>
                        @endif
                    </ul>
                </div>
            </div>
        </nav>
    </header>
    <div class="page-wrapper">
        <main class="page-body"><div class="container-xl">
            @hasSection('page-header')<div class="mb-3">@yield('page-header')</div>@endif
            @if(session('success'))<div class="alert alert-success" role="alert">{{ session('success') }}</div>@endif
            @if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @yield('content')
        </div></main>
        @include('shared.footer')
    </div>
</div>
@stack('scripts')
</body>
</html>
