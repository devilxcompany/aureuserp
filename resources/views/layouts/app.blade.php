<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>@yield('title', 'AureusERP')</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .login-wrapper {
      width: 100%;
      max-width: 420px;
      padding: 1rem;
    }

    .logo {
      text-align: center;
      margin-bottom: 2rem;
    }

    .logo h1 {
      font-size: 2rem;
      font-weight: 700;
      color: #e94560;
      letter-spacing: 2px;
    }

    .logo p {
      color: #a0aec0;
      font-size: 0.875rem;
      margin-top: 0.25rem;
    }

    .card {
      background: rgba(255, 255, 255, 0.05);
      backdrop-filter: blur(12px);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 16px;
      padding: 2.5rem 2rem;
      box-shadow: 0 25px 50px rgba(0, 0, 0, 0.4);
    }

    .card h2 {
      color: #fff;
      font-size: 1.4rem;
      font-weight: 600;
      margin-bottom: 0.25rem;
    }

    .card .subtitle {
      color: #a0aec0;
      font-size: 0.875rem;
      margin-bottom: 2rem;
    }

    .form-group {
      margin-bottom: 1.25rem;
    }

    label {
      display: block;
      color: #cbd5e0;
      font-size: 0.875rem;
      font-weight: 500;
      margin-bottom: 0.5rem;
    }

    input[type="email"],
    input[type="password"] {
      width: 100%;
      padding: 0.75rem 1rem;
      background: rgba(255, 255, 255, 0.08);
      border: 1px solid rgba(255, 255, 255, 0.15);
      border-radius: 8px;
      color: #fff;
      font-size: 0.95rem;
      outline: none;
      transition: border-color 0.2s, background 0.2s;
    }

    input[type="email"]::placeholder,
    input[type="password"]::placeholder {
      color: #718096;
    }

    input[type="email"]:focus,
    input[type="password"]:focus {
      border-color: #e94560;
      background: rgba(255, 255, 255, 0.12);
    }

    .remember-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 1.5rem;
    }

    .remember-row label {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      margin: 0;
      cursor: pointer;
      color: #a0aec0;
      font-size: 0.875rem;
    }

    .remember-row input[type="checkbox"] {
      accent-color: #e94560;
      width: 16px;
      height: 16px;
    }

    .forgot-link {
      color: #e94560;
      text-decoration: none;
      font-size: 0.875rem;
      transition: opacity 0.2s;
    }

    .forgot-link:hover { opacity: 0.8; }

    .btn-login {
      width: 100%;
      padding: 0.875rem;
      background: linear-gradient(90deg, #e94560, #c0392b);
      color: #fff;
      font-size: 1rem;
      font-weight: 600;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      letter-spacing: 0.5px;
      transition: opacity 0.2s, transform 0.1s;
    }

    .btn-login:hover { opacity: 0.92; }
    .btn-login:active { transform: scale(0.98); }

    .error-msg {
      background: rgba(233, 69, 96, 0.15);
      border: 1px solid rgba(233, 69, 96, 0.4);
      color: #fc8181;
      font-size: 0.85rem;
      border-radius: 6px;
      padding: 0.6rem 0.9rem;
      margin-bottom: 1rem;
      display: none;
    }

    .footer-note {
      text-align: center;
      margin-top: 1.5rem;
      color: #718096;
      font-size: 0.8rem;
    }
  </style>
</head>
<body>
  @yield('content')
</body>
</html>
