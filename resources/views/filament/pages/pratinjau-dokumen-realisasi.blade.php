@php
    $jenisAktif = $this->jenisDokumen();
    $jumlah = $this->jumlahBerkas();
    $berkasTersedia = $this->berkasTersedia();
    $pratinjau = $this->pratinjau();
@endphp

<x-filament-panels::page>
    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Daftar berkas: satu realisasi boleh menyimpan lebih dari satu proposal
             maupun laporan, jadi semuanya didaftar dan bisa dipilih satu per satu. --}}
        <div class="space-y-4 lg:col-span-1">
            <div class="fi-section rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex gap-1 rounded-lg bg-gray-100 p-1 dark:bg-white/5">
                    @foreach (\App\Enums\EnumJenisDokumenRealisasi::cases() as $jenis)
                        <button
                            type="button"
                            wire:click="pilihJenis('{{ $jenis->value }}')"
                            @disabled(($jumlah[$jenis->value] ?? 0) === 0)
                            @class([
                                'flex-1 rounded-md px-3 py-1.5 text-sm font-medium transition',
                                'bg-white text-gray-950 shadow-sm dark:bg-gray-800 dark:text-white' => $jenis === $jenisAktif,
                                'text-gray-500 hover:text-gray-950 dark:text-gray-400 dark:hover:text-white' => $jenis !== $jenisAktif,
                                'cursor-not-allowed opacity-50 hover:text-gray-500' => ($jumlah[$jenis->value] ?? 0) === 0,
                            ])
                        >
                            {{ $jenis->getLabel() }}
                            <span class="text-xs font-normal">({{ $jumlah[$jenis->value] ?? 0 }})</span>
                        </button>
                    @endforeach
                </div>

                <div class="mt-4 space-y-2">
                    @forelse ($berkasTersedia as $berkas)
                        <button
                            type="button"
                            wire:click="pilihBerkas(@js($berkas['path']))"
                            @class([
                                'flex w-full items-start gap-3 rounded-xl px-3 py-2.5 text-left transition',
                                'bg-primary-50 ring-1 ring-primary-500 dark:bg-primary-500/10 dark:ring-primary-400' => $berkas['path'] === $this->berkas,
                                'bg-gray-50 hover:bg-gray-100 dark:bg-white/5 dark:hover:bg-white/10' => $berkas['path'] !== $this->berkas,
                            ])
                        >
                            <span class="mt-0.5 shrink-0 text-gray-400 dark:text-gray-500">
                                <x-filament::icon :icon="$jenisAktif->getIcon()" class="h-5 w-5" />
                            </span>

                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-medium leading-snug break-words text-gray-950 dark:text-white">
                                    {{ $berkas['nama'] }}

                                    @if ($berkas['ukuran'])
                                        <span class="font-normal text-gray-500 dark:text-gray-400">({{ $berkas['ukuran'] }})</span>
                                    @endif
                                </span>

                                <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">
                                    @if ($berkas['diunggah'])
                                        Diunggah {{ $berkas['diunggah']->locale('id')->translatedFormat('d F Y, H:i') }}
                                    @else
                                        Tanggal unggah tidak tercatat
                                    @endif
                                </span>
                            </span>
                        </button>
                    @empty
                        <p class="text-sm text-gray-400 dark:text-gray-500">
                            Belum ada {{ strtolower($jenisAktif->getLabel()) }} pada realisasi ini.
                        </p>
                    @endforelse
                </div>
            </div>

            <p class="px-1 text-xs text-gray-500 dark:text-gray-400">
                Berkas disimpan pada penyimpanan tertutup. Alamat pratinjaunya dibuat baru
                setiap halaman ini dibuka dan hanya berlaku {{ \App\Filament\Pages\PratinjauDokumenRealisasi::MENIT_URL }} menit.
            </p>
        </div>

        {{-- Pratinjau berkas terpilih, memakai URL sementara yang baru saja dibuat. --}}
        <div class="lg:col-span-2">
            <div class="fi-section rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                @if ($pratinjau === null)
                    <p class="py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                        Pilih berkas di sebelah kiri untuk melihat isinya.
                    </p>
                @else
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <p class="min-w-0 truncate text-sm font-medium text-gray-950 dark:text-white">
                            {{ $pratinjau['name'] }}
                        </p>

                        @if (filled($pratinjau['url']))
                            <a
                                href="{{ $pratinjau['url'] }}"
                                target="_blank"
                                rel="noopener"
                                class="fi-link inline-flex items-center gap-1 text-sm font-medium text-primary-600 dark:text-primary-400"
                            >
                                Buka di tab baru
                            </a>
                        @endif
                    </div>

                    @include('filament.components.file-viewer', [
                        'files' => [$pratinjau],
                        'label' => $this->getTitle(),
                    ])
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
