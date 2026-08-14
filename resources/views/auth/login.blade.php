<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk — Sistem Manajemen Kinerja</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800" rel="stylesheet" />
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen bg-parchment text-ink antialiased selection:bg-navy/15" style="font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif;">
    <main class="grid min-h-screen lg:grid-cols-[1fr_600px]">
        {{-- Panel brand --}}
        <section class="relative hidden overflow-hidden bg-ink text-white lg:block">
            <img src="{{ asset('images/campus.webp') }}" alt="Universitas Muhammadiyah Lamongan" class="absolute inset-0 h-full w-full object-cover opacity-40">
            <div class="absolute inset-0 bg-gradient-to-br from-ink/70 via-ink/85 to-ink"></div>
            <div class="absolute inset-0 opacity-[0.06]" style="background-image: linear-gradient(to right, white 1px, transparent 1px), linear-gradient(to bottom, white 1px, transparent 1px); background-size: 64px 64px;"></div>

            <div class="relative flex min-h-screen flex-col justify-between p-12 xl:p-16">
                <div class="flex items-center gap-4">
                    <span class="flex h-16 items-center rounded-xl bg-white p-2">
                        <img src="{{ asset('images/logo-umla.jpg') }}" alt="Logo UMLA" class="block h-full w-auto object-contain">
                    </span>
                    <span>
                        <span class="block text-sm font-bold uppercase tracking-[0.3em] text-sky">SIMKINERJA</span>
                        <span class="block text-2xl font-bold tracking-tight">Sistem Manajemen Kinerja</span>
                    </span>
                </div>

                <div class="max-w-3xl">
                    <p class="mb-7 text-base font-semibold uppercase tracking-[0.28em] text-gold">Akses aman</p>
                    <h1 class="font-bold tracking-[-2.5px]" style="font-size: clamp(52px, 5.6vw, 82px); line-height: 0.98;">
                        Kelola kinerja unit kerja dalam satu ruang.
                    </h1>
                    <p class="mt-9 max-w-2xl text-2xl leading-[1.55] text-bodymuted">
                        Satu akun untuk merencanakan program kerja, mengajukan anggaran, mencatat pemasukan, dan memantau realisasi hingga selesai.
                    </p>
                </div>

                <div class="grid grid-cols-3 overflow-hidden rounded-2xl border border-white/10">
                    @foreach ([['01', 'Terencana'], ['02', 'Terverifikasi'], ['03', 'Terpantau']] as [$number, $label])
                        <div class="border-r border-white/10 p-6 last:border-r-0">
                            <p class="text-sm font-bold text-gold">{{ $number }}</p>
                            <p class="mt-2 text-lg font-bold">{{ $label }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Panel formulir (terang) --}}
        <section class="flex min-h-screen items-center justify-center bg-parchment px-5 py-12 sm:px-8">
            <div class="w-full max-w-lg">
                <div class="mb-10 flex items-center gap-4 lg:hidden">
                    <span class="flex h-14 items-center rounded-xl bg-white p-1.5 shadow-sm">
                        <img src="{{ asset('images/logo-umla.jpg') }}" alt="Logo UMLA" class="block h-full w-auto object-contain">
                    </span>
                    <span>
                        <span class="block text-xs font-bold uppercase tracking-[0.3em] text-navy">SIMKINERJA</span>
                        <span class="block text-lg font-bold text-ink">Sistem Manajemen Kinerja</span>
                    </span>
                </div>

                <div class="rounded-[28px] border border-ink/10 bg-white p-8 shadow-xl shadow-ink/5 sm:p-10">
                    <p class="text-sm font-semibold uppercase tracking-[0.28em] text-navy">Login</p>
                    <h2 class="mt-5 font-bold tracking-[-1.5px] text-ink" style="font-size: clamp(36px, 4.4vw, 52px); line-height: 1.02;">
                        Selamat datang kembali.
                    </h2>
                    <p class="mt-5 text-xl leading-8 text-ink/55">Masukkan akun kampus Anda untuk mengakses SIMKINERJA.</p>

                    @if ($errors->any())
                        <div class="mt-7 rounded-2xl border border-crimson/25 bg-crimson/5 p-5 text-base font-medium text-crimson">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login.store') }}" class="mt-9 space-y-6">
                        @csrf

                        <div>
                            <label for="username" class="text-base font-bold text-ink">Username</label>
                            <input
                                id="username"
                                name="username"
                                type="text"
                                value="{{ old('username') }}"
                                required
                                autofocus
                                autocomplete="username"
                                class="mt-3 block w-full rounded-xl border border-ink/10 bg-parchment px-5 py-4 text-lg font-medium text-ink placeholder:text-ink/40 outline-none transition focus:border-navy focus:bg-white focus:ring-4 focus:ring-navy/10"
                                placeholder="Masukkan username"
                            >
                        </div>

                        <div>
                            <label for="password" class="text-base font-bold text-ink">Password</label>
                            <input
                                id="password"
                                name="password"
                                type="password"
                                required
                                autocomplete="current-password"
                                class="mt-3 block w-full rounded-xl border border-ink/10 bg-parchment px-5 py-4 text-lg font-medium text-ink placeholder:text-ink/40 outline-none transition focus:border-navy focus:bg-white focus:ring-4 focus:ring-navy/10"
                                placeholder="Masukkan password"
                            >
                        </div>

                        <label class="flex items-center gap-3 text-base font-medium text-ink/60">
                            <input type="checkbox" name="remember" value="1" class="h-5 w-5 rounded border-ink/20 text-crimson focus:ring-crimson">
                            Ingat saya
                        </label>

                        <button type="submit" class="flex w-full items-center justify-center rounded-full bg-crimson px-6 py-5 text-lg font-bold text-white transition hover:bg-[#a60d26] active:scale-[0.98]">
                            Masuk ke Aplikasi
                        </button>
                    </form>

                    <div class="mt-9 border-t border-ink/10 pt-7">
                        <p class="text-base font-bold text-ink">Catatan akses</p>
                        <ul class="mt-4 space-y-3 text-base leading-7 text-ink/55">
                            <li class="flex gap-3"><span class="mt-2.5 h-2 w-2 shrink-0 rounded-full bg-gold"></span><span>Gunakan username dan password yang diberikan pengelola sistem.</span></li>
                            <li class="flex gap-3"><span class="mt-2.5 h-2 w-2 shrink-0 rounded-full bg-gold"></span><span>Hubungi admin jika akun belum aktif atau lupa akses.</span></li>
                        </ul>
                    </div>
                </div>

                <p class="mt-7 text-center text-sm text-ink/50">© {{ date('Y') }} Universitas Muhammadiyah Lamongan</p>
            </div>
        </section>
    </main>
</body>
</html>
