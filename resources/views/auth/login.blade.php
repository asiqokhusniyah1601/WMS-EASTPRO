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
        body { margin: 0; overflow: hidden; }
        
        .video-bg {
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            object-fit: cover;
            z-index: -1;
        }
        
        .login-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: flex-end; /* Align to the right side */
            padding: 40px 8%;
            background: transparent;
        }

        .login-card {
            width: 100%;
            max-width: 440px;
            /* 3D Glassmorphism Effect (Blue Theme) */
            background: rgba(30, 64, 175, 0.5); /* Blue background */
            backdrop-filter: blur(20px) saturate(160%);
            -webkit-backdrop-filter: blur(20px) saturate(160%);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-top: 1px solid rgba(255, 255, 255, 0.4);
            border-left: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 24px;
            padding: 48px 40px;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.5), inset 0 0 0 1px rgba(255,255,255,0.1);
            animation: fadeInUp 0.7s cubic-bezier(0.2, 0.8, 0.2, 1);
            color: #ffffff;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .login-header {
            text-align: center;
            margin-bottom: 32px;
        }

        /* Larger & Clearer Logo with White BG for Contrast */
        .login-header .logo-icon {
            width: 90px;
            height: 90px;
            border-radius: 22px;
            background: rgba(255, 255, 255, 0.95); /* Solid white for logo contrast */
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.8);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.4), inset 0 4px 10px rgba(0,0,0,0.1);
        }

        .login-header .logo-icon i { font-size: 44px; color: var(--accent-blue, #3b82f6); }
        .login-header .logo-icon img { 
            width: 70px; 
            height: 70px; 
            object-fit: contain; 
            /* No drop shadow needed if the background is solid white */
        }

        .login-header h1 {
            font-size: 28px;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 8px;
            letter-spacing: 0.5px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.4);
        }

        .login-header p {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.9);
            text-shadow: 0 1px 2px rgba(0,0,0,0.3);
        }

        .login-form .form-group { margin-bottom: 20px; }

        .login-form label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 8px;
            letter-spacing: 0.3px;
            text-shadow: 0 1px 2px rgba(0,0,0,0.3);
        }

        .login-input-wrap { position: relative; }

        .login-input-wrap i.field-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.8);
            font-size: 15px;
        }

        .toggle-password {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #000000; /* Changed to black */
            cursor: pointer;
            font-size: 15px;
            padding: 5px;
            transition: color 0.2s ease;
        }
        .toggle-password:hover { color: #333333; }

        /* Glass Input Fields */
        .login-form input[type="email"],
        .login-form input[type="password"],
        .login-form input[type="text"] {
            width: 100%;
            padding: 14px 44px;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            color: #ffffff;
            font-size: 15px;
            font-weight: 500;
            font-family: inherit;
            transition: all 0.2s ease;
        }
        .login-form input::placeholder { color: rgba(255, 255, 255, 0.6); }
        .login-form input:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.25);
            border-color: rgba(255, 255, 255, 0.6);
            box-shadow: 0 0 0 4px rgba(255, 255, 255, 0.2);
        }

        .login-remember {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 28px;
            cursor: pointer;
        }

        .login-remember input { 
            width: 16px; height: 16px; 
            accent-color: var(--accent-blue); 
            cursor: pointer;
        }

        .login-form .btn-login {
            width: 100%;
            padding: 14px;
            font-size: 16px;
            font-weight: 700;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 12px;
            color: #fff;
            background: linear-gradient(135deg, #10b981, #059669); /* Emerald Green to stand out on Blue */
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
            text-transform: uppercase;
            letter-spacing: 1px;
            text-shadow: 0 1px 2px rgba(0,0,0,0.2);
        }

        .login-form .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.6);
            background: linear-gradient(135deg, #059669, #047857);
        }

        .login-error {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #fca5a5;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
            backdrop-filter: blur(10px);
        }

        .login-footer {
            text-align: center;
            margin-top: 32px;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.7);
        }
    </style>
</head>

<body>
    <video autoplay loop muted playsinline class="video-bg">
        <source src="{{ asset('videos/Background_Login.mp4') }}" type="video/mp4">
    </video>
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
                        <i class="fa-solid fa-eye toggle-password" id="togglePassword" title="Tampilkan Password"></i>
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

    <script>
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this;
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    </script>
</body>
</html>
