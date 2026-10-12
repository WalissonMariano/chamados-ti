<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login — {{ config('app.name', 'Chamados') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="login-page">
    <div class="login-panel">
        <aside class="login-brand">
            <img
                src="{{ asset('images/login-brand.png') }}"
                alt="Ilustração do sistema de chamados"
                class="login-brand-image"
            >
        </aside>

        <main class="login-form-panel">
            <div class="login-form-wrapper">
                <div class="login-form-header">
                    <img
                        src="{{ asset('images/logo_tickets_sem_fundo.png') }}"
                        alt="Chamados.TI"
                        class="login-form-logo"
                    >
                </div>

                @if (session('status'))
                    <div class="login-alert login-alert--success">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="login-alert login-alert--error">
                        {{ $errors->first() }}
                    </div>
                @endif

                <div class="login-demo">
                    <strong>Acesso de demonstração</strong>
                    <p>Use as credenciais abaixo para explorar o sistema:</p>
                    <dl>
                        <div>
                            <dt>E-mail</dt>
                            <dd><code>admin@admin.com</code></dd>
                        </div>
                        <div>
                            <dt>Senha</dt>
                            <dd><code>admin</code></dd>
                        </div>
                    </dl>
                </div>

                <form method="POST" action="{{ route('login') }}">
                    @csrf

                    <div class="login-field">
                        <label for="email">E-mail</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="{{ old('email', 'admin@admin.com') }}"
                            placeholder="seu@email.com"
                            autocomplete="email"
                            required
                            autofocus
                            class="@error('email') is-invalid @enderror"
                        >
                        @error('email')
                            <p class="login-field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="login-field">
                        <label for="password">Senha</label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            value="admin"
                            placeholder="••••••••"
                            autocomplete="current-password"
                            required
                            class="@error('password') is-invalid @enderror"
                        >
                        @error('password')
                            <p class="login-field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="login-options">
                        <label class="login-remember">
                            <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}>
                            Lembrar-me
                        </label>
                    </div>

                    <button type="submit" class="login-submit">Entrar</button>
                </form>
                <div class="login-footer">
                    &copy; {{ date('Y') }} {{ config('app.name', 'Chamados') }}
                </div> 
            </div>
        </main>
    </div>
</body>
</html>
