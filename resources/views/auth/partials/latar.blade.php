{{--
    Latar panel brand halaman masuk. Jenisnya diatur di Pengaturan Sistem → Halaman Masuk;
    yang dipakai di sini adalah jenis TERPASANG, yang sudah turun sendiri ke gambar bila
    berkas video hilang atau tautan YouTube-nya tidak dikenali.

    Video dan YouTube sengaja dibisukan: peramban menolak memutar otomatis video bersuara,
    dan tanpa `muted` latarnya akan diam di bingkai pertama.
--}}
@php
    use App\Enums\EnumJenisLatarMasuk;
    use App\Models\Setting;

    $jenisLatar = Setting::masukLatarJenisTerpasang();
@endphp

@switch($jenisLatar)
    @case(EnumJenisLatarMasuk::Video)
        <video
            class="absolute inset-0 h-full w-full object-cover opacity-40"
            src="{{ Setting::masukLatarVideoUrl() }}"
            poster="{{ Setting::masukLatarGambarUrl() }}"
            autoplay
            muted
            loop
            playsinline
            preload="auto"
            aria-hidden="true"
        ></video>
        @break

    @case(EnumJenisLatarMasuk::Youtube)
        @php
            $idVideo = Setting::masukLatarYoutubeId();
            $parameter = http_build_query([
                'autoplay' => 1,
                'mute' => 1,
                'loop' => 1,
                'playlist' => $idVideo,
                'controls' => 0,
                'playsinline' => 1,
                'modestbranding' => 1,
                'rel' => 0,
                'disablekb' => 1,
                'iv_load_policy' => 3,
            ]);
        @endphp

        {{-- Ukuran 16:9 dipaksa melampaui bingkainya lalu dipusatkan, supaya video menutup
             seluruh panel tanpa bilah hitam. --}}
        <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
            <iframe
                class="absolute left-1/2 top-1/2 h-[56.25vw] min-h-full w-[177.78vh] min-w-full -translate-x-1/2 -translate-y-1/2 opacity-40"
                src="https://www.youtube-nocookie.com/embed/{{ $idVideo }}?{{ $parameter }}"
                title="Latar {{ $instansi }}"
                frameborder="0"
                allow="autoplay; encrypted-media"
                referrerpolicy="strict-origin-when-cross-origin"
                tabindex="-1"
            ></iframe>
        </div>
        @break

    @default
        <img
            src="{{ Setting::masukLatarGambarUrl() }}"
            alt="{{ $instansi }}"
            class="absolute inset-0 h-full w-full object-cover opacity-40"
        >
@endswitch
