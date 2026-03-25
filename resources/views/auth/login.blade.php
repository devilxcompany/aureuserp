@extends('layouts.app')

@section('title', 'Login – AureusERP')

@section('content')
<div class="login-wrapper">
    <div class="logo">
        <h1>AureusERP</h1>
        <p>ERP Management for Devil X Company</p>
    </div>

    <div class="card">
        <h2>Welcome back</h2>
        <p class="subtitle">Sign in to your account to continue</p>

        @if ($errors->any())
            <div class="error-msg" style="display:block">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="form-group">
                <label for="email">Email address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="{{ old('email') }}"
                    placeholder="admin@example.com"
                    required
                    autocomplete="email"
                />
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="••••••••"
                    required
                    autocomplete="current-password"
                />
            </div>

            <div class="remember-row">
                <label>
                    <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }} />
                    Remember me
                </label>
                <a href="{{ route('password.request') }}" class="forgot-link">Forgot password?</a>
            </div>

            <button type="submit" class="btn-login">Sign In</button>
        </form>

        <p class="footer-note">© 2025 Devil X Company · AureusERP</p>
    </div>
</div>
@endsection
