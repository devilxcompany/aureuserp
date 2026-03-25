<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <style>
        body { font-family: sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; background: #f3f4f6; }
        .card { background: #fff; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,.1); width: 100%; max-width: 400px; }
        h1 { margin: 0 0 1.5rem; font-size: 1.5rem; text-align: center; }
        label { display: block; font-size: .875rem; margin-bottom: .25rem; }
        input { width: 100%; padding: .5rem .75rem; border: 1px solid #d1d5db; border-radius: 4px; font-size: 1rem; box-sizing: border-box; }
        .error { color: #dc2626; font-size: .875rem; margin-top: .25rem; }
        button { width: 100%; padding: .6rem; background: #4f46e5; color: #fff; border: none; border-radius: 4px; font-size: 1rem; cursor: pointer; margin-top: 1.25rem; }
        button:hover { background: #4338ca; }
        .field { margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Sign in</h1>
        <form method="POST" action="{{ route('login.submit') }}">
            @csrf
            <div class="field">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
                @error('email')
                    <p class="error">{{ $message }}</p>
                @enderror
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" required>
            </div>
            <button type="submit">Log in</button>
        </form>
    </div>
</body>
</html>
