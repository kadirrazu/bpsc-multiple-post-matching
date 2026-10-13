@extends('layouts.auth')

@section('title', 'Login')

@section('content')
    <div class="card card-md">
        <div class="card-body">
            <h2 class="h2 text-center mb-4">
                Sign in to your account
            </h2>

            @if (session('status'))
                <div class="alert alert-success" role="alert">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger" role="alert">
                    <div class="fw-semibold mb-1">
                        Login failed
                    </div>

                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form
                action="{{ route('login') }}"
                method="POST"
                autocomplete="off"
                novalidate
            >
                @csrf

                <div class="mb-3">
                    <label
                        for="email"
                        class="form-label required"
                    >
                        Email address
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email') }}"
                        class="form-control @error('email') is-invalid @enderror"
                        placeholder="your-email@example.com"
                        autocomplete="username"
                        autofocus
                        required
                    >

                    @error('email')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label
                        for="password"
                        class="form-label required"
                    >
                        Password
                    </label>

                    <div class="password-control"><input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control @error('password') is-invalid @enderror"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        required
                    ><button type="button" class="password-toggle" data-password-toggle="password" aria-controls="password" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i></button></div>

                    @error('password')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-check">
                        <input
                            type="checkbox"
                            name="remember"
                            value="1"
                            class="form-check-input"
                            @checked(old('remember'))
                        >

                        <span class="form-check-label">
                            Remember me
                        </span>
                    </label>
                </div>

                <div class="form-footer">
                    <button
                        type="submit"
                        class="btn btn-primary w-100"
                    >
                        Sign in
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="text-center text-secondary mt-3">
        Authorized users only
    </div>
@endsection