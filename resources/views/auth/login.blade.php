@php
    $themeMode = \App\Models\AppSetting::getValue('theme_mode', 'dark');
    $appLogo = \App\Models\AppSetting::getValue('app_logo');
    $appFavicon = \App\Models\AppSetting::getValue('app_favicon');
@endphp
<!DOCTYPE html>
<html lang="id" class="{{ $themeMode === 'light' ? 'light-theme' : 'dark-theme' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk | WMS DLMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @if($appFavicon)
        <link rel="icon" href="{{ asset($appFavicon) }}">
    @endif
    <style>
        .login-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-primary);
            padding: 20px;
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 40px 36px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
            animation: fadeInUp 0.5s ease-out;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(24px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .login-header .logo-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-indigo));
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            box-shadow: 0 8px 30px rgba(79, 70, 229, 0.3);
        }

        .login-header .logo-icon i { font-size: 26px; color: #fff; }
        .login-header .logo-icon img { width: 34px; height: 34px; object-fit: contain; }

        .login-header h1 {
            font-size: 22px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 6px;
        }

        .login-header p {
            font-size: 13px;
            color: var(--text-secondary);
        }

        .login-form .form-group { margin-bottom: 18px; }

        .login-form label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-secondary);
            margin-bottom: 7px;
        }

        .login-input-wrap { position: relative; }

        .login-input-wrap i.field-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 14px;
        }

        .login-form input[type="email"],
        .login-form input[type="password"] {
            width: 100%;
            padding: 12px 14px 12px 40px;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            color: var(--text-primary);
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .login-form input:focus {
            outline: none;
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }

        .login-remember {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 22px;
        }

        .login-remember input { width: 15px; height: 15px; accent-color: var(--accent-blue); }

        .login-form .btn-login {
            width: 100%;
            padding: 13px;
            font-size: 15px;
            font-weight: 600;
            border: none;
            border-radius: 10px;
            color: #fff;
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-indigo));
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.2s ease;
        }

        .login-form .btn-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(79, 70, 229, 0.35);
        }

        .login-error {
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            border-radius: 10px;
            padding: 11px 14px;
            font-size: 13px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 9px;
        }

        .login-footer {
            text-align: center;
            margin-top: 26px;
            font-size: 11px;
            color: var(--text-muted);
        }
    </style>
</head>

<body>
    <div class="login-page">
        <div class="login-card">
            <div class="login-header">
                <div class="logo-icon">
                    @if($appLogo)
                        <img src="{{ asset($appLogo) }}" alt="Logo">
                    @else
                        <i class="fa-solid fa-boxes-stacked"></i>
                    @endif
                </div>
                <h1>WMS EASTPRO</h1>
                <p>Masuk untuk mengelola lifecycle perangkat Anda</p>
            </div>

            @if ($errors->any())
                <div class="login-error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <form action="{{ route('login.post') }}" method="POST" class="login-form">
                @csrf
                <div class="form-group">
                    <label for="email">Email</label>
                    <div class="login-input-wrap">
                        <i class="fa-solid fa-envelope field-icon"></i>
                        <input type="email" name="email" id="email" value="{{ old('email') }}"
                            placeholder="nama@perusahaan.com" autofocus required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="login-input-wrap">
                        <i class="fa-solid fa-lock field-icon"></i>
                        <input type="password" name="password" id="password"
                            placeholder="Masukkan password" required>
                    </div>
                </div>

                <label class="login-remember">
                    <input type="checkbox" name="remember" value="1">
                    Ingat saya di perangkat ini
                </label>

                <button type="submit" class="btn-login">
                    <i class="fa-solid fa-arrow-right-to-bracket"></i>
                    Masuk
                </button>
            </form>

            <div class="login-footer">
                &copy; {{ date('Y') }} PT EasyGo Indonesia &middot; Device Lifecycle Management
            </div>
        </div>
    </div>
</body>
</html>
