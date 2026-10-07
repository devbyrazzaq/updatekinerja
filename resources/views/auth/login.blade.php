@php
    $nama = \App\Models\Setting::brandNama();
    $instansi = \App\Models\Setting::brandInstansi();
    $logo = \App\Models\Setting::brandLogoUrl() ?? asset('images/logo-umla.jpg');
    $judul = \App\Models\Setting::masukJudul();
    $deskripsi = \App\Models\Setting::masukDeskripsi();
    $catatanJudul = \App\Models\Setting::masukCatatanJudul();
    $catatan = \App\Models\Setting::masukCatatan();
    $unitKerjaAktif = \App\Models\UnitKerja::query()->where('is_active', true);
    $jumlahUnitKerja = (clone $unitKerjaAktif)->count();
    $jumlahFakultas = (clone $unitKerjaAktif)->where('name', 'like', 'Fakultas%')->count();
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk — {{ $nama }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800" rel="stylesheet" />
    <script>
        document.documentElement.classList.add('is-splashing', 'js-sheet');

        /** Jaring pengaman: buka tampilan jika bundel animasi gagal dimuat. */
        window.setTimeout(function () {
            if (!document.documentElement.classList.contains('motion-ready')) {
                document.documentElement.classList.remove('is-splashing', 'js-sheet');
                document.querySelector('[data-splash]')?.remove();
            }
        }, 4000);
    </script>
    <style>
        html.is-splashing { overflow: hidden; }
        html.is-splashing body { overflow: hidden; }
        html.is-splashing [data-anim] { opacity: 0; }
        html:not(.is-splashing) [data-splash] { display: none; }

        @media (prefers-reduced-motion: reduce) {
            html.is-splashing [data-anim] { opacity: 1; }
        }

        /* Panel formulir tampil sebagai bottom sheet selama layar belum lg. */
        @media (max-width: 1023.98px) {
            html.js-sheet [data-sheet] { transform: translateY(100%); }

            html.js-sheet [data-sheet],
            html.js-sheet [data-sheet-backdrop] { visibility: hidden; pointer-events: none; }

            html.js-sheet.is-sheet-open [data-sheet],
            html.js-sheet.is-sheet-open [data-sheet-backdrop] { visibility: visible; pointer-events: auto; }

            html.js-sheet.is-sheet-open,
            html.js-sheet.is-sheet-open body { overflow: hidden; }

            /* Tanpa JS: formulir kembali menjadi bagian halaman biasa. */
            html:not(.js-sheet) [data-sheet] {
                position: static;
                height: auto;
                transform: none;
                box-shadow: none;
                border-radius: 0;
            }

            html:not(.js-sheet) [data-sheet-backdrop],
            html:not(.js-sheet) [data-sheet-cta],
            html:not(.js-sheet) [data-sheet-close],
            html:not(.js-sheet) [data-sheet-handle] { display: none; }
        }
    </style>
    <noscript>
        <style>[data-splash] { display: none; }</style>
    </noscript>
    @vite(['resources/css/app.css', 'resources/js/login.js'])
</head>
<body class="bg-parchment text-ink antialiased selection:bg-navy/15" style="font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif;">
    {{-- Splash screen --}}
    <div
        data-splash
        @if ($errors->any()) data-splash-skip @endif
        class="fixed inset-0 z-[100] flex flex-col items-center justify-center gap-8 bg-ink px-6 text-center text-white"
    >
        <div class="absolute inset-0 opacity-[0.07]" style="background-image: linear-gradient(to right, white 1px, transparent 1px), linear-gradient(to bottom, white 1px, transparent 1px); background-size: 64px 64px;"></div>
        <div class="absolute inset-0" style="background: radial-gradient(60% 55% at 50% 45%, rgba(30,58,138,0.45) 0%, transparent 70%);"></div>

        <div class="relative flex flex-col items-center gap-8">
            <span data-splash-logo class="flex h-20 items-center rounded-2xl bg-white p-3 shadow-2xl shadow-black/30 sm:h-24" style="opacity: 0;">
                <img src="{{ $logo }}" alt="Logo {{ $nama }}" class="block h-full w-auto object-contain">
            </span>

            <div class="space-y-3">
                <p data-splash-text class="text-sm font-bold uppercase tracking-[0.34em] text-gold" style="opacity: 0;">{{ $nama }}</p>
                <p data-splash-text class="text-2xl font-bold tracking-[-1px] sm:text-4xl" style="opacity: 0;">Sistem Manajemen Kinerja</p>
                <p data-splash-text class="text-sm text-bodymuted sm:text-base" style="opacity: 0;">{{ $instansi }}</p>
            </div>

            <span data-splash-text class="block text-gold" style="opacity: 0;">
                <svg class="h-14 w-14 -rotate-90" viewBox="0 0 56 56" fill="none" aria-hidden="true">
                    <circle cx="28" cy="28" r="24" stroke="white" stroke-width="3" opacity="0.12"></circle>
                    <circle
                        data-splash-ring
                        cx="28" cy="28" r="24"
                        stroke="currentColor" stroke-width="3" stroke-linecap="round"
                        stroke-dasharray="150.8" stroke-dashoffset="150.8"
                    ></circle>
                </svg>
            </span>
        </div>
    </div>

    {{-- Latar gelap saat bottom sheet terbuka --}}
    <div data-sheet-backdrop class="fixed inset-0 z-30 bg-ink/75 backdrop-blur-[2px] lg:hidden" style="opacity: 0;"></div>

    <main class="flex min-h-dvh flex-col overflow-hidden bg-ink lg:grid lg:min-h-screen lg:grid-cols-[1fr_600px] lg:overflow-visible lg:bg-transparent">
        {{-- Panel brand: 4/5 layar di mobile, kolom kiri penuh di desktop --}}
        <section class="relative flex flex-1 flex-col overflow-hidden bg-ink text-white lg:h-auto lg:min-h-screen">
            @include('auth.partials.latar')
            <div class="absolute inset-0 bg-gradient-to-br from-ink/70 via-ink/85 to-ink"></div>
            <div class="absolute inset-0 opacity-[0.06]" style="background-image: linear-gradient(to right, white 1px, transparent 1px), linear-gradient(to bottom, white 1px, transparent 1px); background-size: 64px 64px;"></div>

            <div class="relative flex flex-1 flex-col justify-between gap-8 p-6 pb-16 sm:p-10 sm:pb-20 lg:gap-0 lg:p-12 lg:pb-12 xl:p-16">
                <div data-anim="brand" class="flex items-center gap-3 lg:gap-4">
                    <span class="flex h-12 items-center rounded-xl bg-white p-1.5 lg:h-16 lg:p-2">
                        <img src="{{ $logo }}" alt="Logo {{ $nama }}" class="block h-full w-auto object-contain">
                    </span>
                    <span>
                        <span class="block text-xs font-bold uppercase tracking-[0.3em] text-sky lg:text-sm">{{ $nama }}</span>
                        <span class="block text-lg font-bold tracking-tight lg:text-2xl">Sistem Manajemen Kinerja</span>
                    </span>
                </div>

                <div data-anim="brand" class="max-w-3xl">
                    <h1 class="font-bold tracking-[-1px] text-[clamp(40px,10.5vw,56px)] leading-[1.03] lg:tracking-[-2.5px] lg:text-[clamp(52px,5.6vw,82px)] lg:leading-[0.98]">
                        {{ $judul }}
                    </h1>
                    <p class="mt-5 max-w-2xl text-xl leading-relaxed text-bodymuted lg:mt-9 lg:text-2xl lg:leading-[1.55]">
                        {{ $deskripsi }}
                    </p>
                </div>

                {{-- Cakupan sistem: unit kerja aktif beserta fakultas di dalamnya. --}}
                <div class="hidden gap-3 sm:grid-cols-2 lg:grid lg:grid-cols-2 lg:gap-4">
                    <div data-anim="brand" class="flex gap-3 rounded-2xl border border-white/10 bg-white/5 p-4 lg:gap-4 lg:p-6">
                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-gold lg:h-6 lg:w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M3 21h18M5 21V5a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v16M15 21V9h2a2 2 0 0 1 2 2v10" />
                            <path d="M9 7h2M9 11h2M9 15h2" />
                        </svg>
                        <span class="min-w-0">
                            <span class="block text-base font-bold lg:text-lg">{{ $jumlahUnitKerja }} Unit Kerja</span>
                            <span class="mt-0.5 block text-sm leading-snug text-bodymuted lg:mt-1 lg:text-base">Unit kerja aktif yang merencanakan dan melaporkan program kerjanya.</span>
                        </span>
                    </div>
                    <div data-anim="brand" class="flex gap-3 rounded-2xl border border-white/10 bg-white/5 p-4 lg:gap-4 lg:p-6">
                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-gold lg:h-6 lg:w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="m12 4 9 4.5-9 4.5-9-4.5L12 4Z" />
                            <path d="M7 10.5V16c0 1.1 2.2 2 5 2s5-.9 5-2v-5.5" />
                        </svg>
                        <span class="min-w-0">
                            <span class="block text-base font-bold lg:text-lg">{{ $jumlahFakultas }} Fakultas</span>
                            <span class="mt-0.5 block text-sm leading-snug text-bodymuted lg:mt-1 lg:text-base">Fakultas yang tercakup di dalam unit kerja tersebut.</span>
                        </span>
                    </div>
                </div>
            </div>
        </section>

        {{-- Pemicu bottom sheet: 1/5 layar bawah, hanya di mobile --}}
        <div data-sheet-cta data-anim="cta" class="relative z-10 -mt-8 flex flex-col items-center gap-4 rounded-t-[32px] bg-parchment px-6 pb-9 pt-7 lg:hidden">
            <p class="text-center text-base font-medium text-ink/55">Gunakan akun kampus Anda untuk melanjutkan.</p>
            <button
                type="button"
                data-sheet-open
                class="flex w-full max-w-md items-center justify-center rounded-full bg-crimson px-6 py-4 text-lg font-bold text-white transition hover:bg-[#a60d26] active:scale-[0.98]"
            >
                Masuk ke Aplikasi
            </button>
        </div>

        {{-- Panel formulir: bottom sheet di mobile, kolom kanan di desktop --}}
        <section
            data-sheet
            @if ($errors->any()) data-sheet-autoopen @endif
            role="dialog"
            aria-modal="true"
            aria-label="Formulir masuk"
            class="fixed inset-x-0 bottom-0 z-40 flex h-[90dvh] flex-col overflow-y-auto rounded-t-[32px] bg-parchment px-5 pb-10 pt-4 shadow-[0_-24px_60px_rgba(11,31,58,0.45)] sm:px-8 lg:static lg:z-auto lg:h-auto lg:min-h-screen lg:items-center lg:justify-center lg:overflow-visible lg:rounded-none lg:px-8 lg:py-12 lg:shadow-none"
        >
            <span data-sheet-handle class="mx-auto mb-5 block h-1.5 w-12 shrink-0 rounded-full bg-ink/15 lg:hidden"></span>

            <button
                type="button"
                data-sheet-close
                aria-label="Tutup formulir masuk"
                class="absolute right-5 top-5 flex h-9 w-9 items-center justify-center rounded-full bg-ink/5 text-ink/50 transition hover:bg-ink/10 hover:text-ink lg:hidden"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M18 6 6 18M6 6l12 12" />
                </svg>
            </button>

            <div class="mx-auto w-full max-w-lg">
                <div data-anim="form" class="rounded-none border-0 bg-transparent p-0 shadow-none lg:rounded-[28px] lg:border lg:border-ink/10 lg:bg-white lg:p-10 lg:shadow-xl lg:shadow-ink/5">
                    <p class="text-sm font-semibold uppercase tracking-[0.28em] text-navy">Login</p>
                    <h2 class="mt-3 font-bold tracking-[-1px] text-ink text-[clamp(28px,7vw,38px)] leading-[1.05] lg:mt-5 lg:tracking-[-1.5px] lg:text-[clamp(36px,4.4vw,52px)] lg:leading-[1.02]">
                        Selamat datang kembali.
                    </h2>
                    <p class="mt-3 text-base leading-relaxed text-ink/55 lg:mt-5 lg:text-xl lg:leading-8">Masukkan akun kampus Anda untuk mengakses {{ $nama }}.</p>

                    @if ($errors->any())
                        <div class="mt-5 rounded-2xl border border-crimson/25 bg-crimson/5 p-4 text-sm font-medium text-crimson lg:mt-7 lg:p-5 lg:text-base">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-5 lg:mt-9 lg:space-y-6">
                        @csrf

                        <div>
                            <label for="username" class="text-base font-bold text-ink">Username</label>
                            <input
                                id="username"
                                name="username"
                                type="text"
                                value="{{ old('username') }}"
                                required
                                autocomplete="username"
                                class="mt-2 block w-full rounded-xl border border-ink/10 bg-white px-5 py-4 text-lg font-medium text-ink placeholder:text-ink/40 outline-none transition focus:border-navy focus:bg-white focus:ring-4 focus:ring-navy/10 lg:mt-3 lg:bg-parchment"
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
                                class="mt-2 block w-full rounded-xl border border-ink/10 bg-white px-5 py-4 text-lg font-medium text-ink placeholder:text-ink/40 outline-none transition focus:border-navy focus:bg-white focus:ring-4 focus:ring-navy/10 lg:mt-3 lg:bg-parchment"
                                placeholder="Masukkan password"
                            >
                        </div>

                        <label class="flex items-center gap-3 text-base font-medium text-ink/60">
                            <input type="checkbox" name="remember" value="1" class="h-5 w-5 rounded border-ink/20 text-crimson focus:ring-crimson">
                            Ingat saya
                        </label>

                        <button type="submit" class="flex w-full items-center justify-center rounded-full bg-crimson px-6 py-4 text-lg font-bold text-white transition hover:bg-[#a60d26] active:scale-[0.98] lg:py-5">
                            Masuk ke Aplikasi
                        </button>
                    </form>

                    @if ($catatan !== [])
                        <div class="mt-7 border-t border-ink/10 pt-6 lg:mt-9 lg:pt-7">
                            <p class="text-base font-bold text-ink">{{ $catatanJudul }}</p>
                            <ul class="mt-3 space-y-3 text-sm leading-6 text-ink/55 lg:mt-4 lg:text-base lg:leading-7">
                                @foreach ($catatan as $butir)
                                    <li class="flex gap-3"><span class="mt-2 h-2 w-2 shrink-0 rounded-full bg-gold"></span><span>{{ $butir }}</span></li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>

                <p data-anim="form" class="mt-6 text-center text-sm text-ink/50 lg:mt-7">© {{ date('Y') }} {{ $instansi }}</p>
            </div>
        </section>
    </main>
</body>
</html>
