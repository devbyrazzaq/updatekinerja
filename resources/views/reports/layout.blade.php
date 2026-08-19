@php
    use App\Exports\ExportTheme;

    /**
     * Tata letak laporan sengaja dibangun dari tabel dan warna heksa tertulis penuh,
     * bukan flexbox dan `var()`: view yang sama dirender dua mesin sekaligus
     * (mPDF untuk PHP murni, Chromium lewat Browsershot) dan mPDF hanya mengerti
     * sebagian CSS 2.1. Apa pun yang ditambahkan di sini harus lolos keduanya.
     */
    $engine ??= 'browsershot';
    $ringkasan = $summary ?? [];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Laporan' }}</title>
    <style>
        @if ($engine !== 'mpdf')
            {{-- mPDF tidak mengenal @font-face maupun .woff2; fontnya didaftarkan
                 lewat config/pdf.php, jadi aturan ini hanya untuk Chromium. --}}
            {!! ExportTheme::fontFaceCss() !!}
        @endif

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 400;
            color: {{ ExportTheme::css('INK') }};
            font-size: 9.5px;
            line-height: 1.5;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        h1 { margin: 0; }

        /* --- Kop instansi ------------------------------------------------ */

        .letterhead {
            width: 100%;
            border-collapse: collapse;
        }

        .letterhead td {
            vertical-align: middle;
            padding: 0 0 8px 0;
        }

        .kop-logo {
            width: 44px;
        }

        .kop-logo img {
            width: 34px;
        }

        /*
         * Isi sel ditata lewat kelas tunggal, bukan selektor turunan seperti
         * `.letterhead .name`: mPDF tidak menurunkan aturan semacam itu ke elemen
         * di dalam <td>, sehingga gayanya diam-diam hilang pada laporan PHP murni.
         */
        .kop-nama {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }

        .kop-app {
            font-size: 8.5px;
            color: {{ ExportTheme::css('MUTED') }};
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .kop-cetak {
            width: 28%;
            text-align: right;
            font-size: 8.5px;
            color: {{ ExportTheme::css('MUTED') }};
        }

        /*
         * Aturan dua warna: garis navy sepanjang halaman dengan aksen emas pendek
         * di pangkalnya. Digambar sebagai border sel, bukan blok berlatar, karena
         * mPDF meniadakan tinggi elemen kosong tetapi selalu menggambar border.
         */
        .rule {
            width: 100%;
            border-collapse: collapse;
        }

        .rule td {
            font-size: 1px;
            line-height: 1px;
            border-bottom: 2px solid {{ ExportTheme::css('NAVY') }};
        }

        .rule .accent {
            width: 56px;
            border-bottom-color: {{ ExportTheme::css('GOLD') }};
        }

        /* --- Judul laporan ----------------------------------------------- */

        .report-title {
            margin-top: 14px;
        }

        .report-title h1 {
            font-size: 17px;
            font-weight: 700;
            letter-spacing: -0.015em;
            line-height: 1.25;
        }

        .report-title .subtitle {
            margin-top: 3px;
            font-size: 9.5px;
            color: {{ ExportTheme::css('MUTED') }};
        }

        /* --- Kartu ringkasan --------------------------------------------- */

        .summary {
            width: 100%;
            margin-top: 12px;
            border-collapse: separate;
            border-spacing: 6px 0;
        }

        .summary td {
            border: 1px solid {{ ExportTheme::css('HAIRLINE') }};
            background: {{ ExportTheme::css('SURFACE') }};
            padding: 8px 10px;
            vertical-align: top;
        }

        .kartu-label {
            font-size: 7.5px;
            font-weight: 700;
            color: {{ ExportTheme::css('MUTED') }};
            text-transform: uppercase;
            letter-spacing: 0.07em;
        }

        .kartu-nilai {
            margin-top: 2px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: -0.01em;
        }

        /* --- Tabel -------------------------------------------------------- */

        table.data {
            width: 100%;
            margin-top: 14px;
            border-collapse: collapse;
            table-layout: fixed;
        }

        /* Kepala tabel diulang di tiap halaman, dan tidak ada baris yang terbelah. */
        thead { display: table-header-group; }
        table.data tr { page-break-inside: avoid; }

        table.data thead th {
            background: {{ ExportTheme::css('NAVY') }};
            color: #fff;
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            text-align: left;
            padding: 7px 8px;
            vertical-align: middle;
            /* Kolom sempit pada tabel lebar: label panjang harus patah, bukan
               melimpah ke kolom sebelahnya. */
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        /* Tabel berkolom banyak: semuanya dirapatkan agar tetap muat sehalaman. */
        table.dense thead th {
            font-size: 7px;
            letter-spacing: 0.02em;
            padding: 6px 4px;
        }

        table.dense tbody td {
            font-size: 7.5px;
            padding: 5px 4px;
        }

        table.data tbody td {
            padding: 6px 8px;
            border-bottom: 1px solid {{ ExportTheme::css('HAIRLINE') }};
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        /* Baris selang-seling ditandai kelas, bukan :nth-child — mPDF tidak
           mengenal pseudo-class itu. */
        table.data tbody tr.zebra td { background: {{ ExportTheme::css('ZEBRA') }}; }

        /* Pita pembatas antar kelompok baris, mis. bulan pencairan. */
        .group-band { page-break-after: avoid; }

        table.data tbody tr.group-band td {
            background: {{ ExportTheme::css('SURFACE') }};
            border-top: 2px solid {{ ExportTheme::css('NAVY') }};
            border-bottom: 1px solid {{ ExportTheme::css('NAVY') }};
            padding: 5px 8px;
            font-size: 8px;
            font-weight: 700;
            color: {{ ExportTheme::css('NAVY') }};
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .col-index {
            color: {{ ExportTheme::css('MUTED') }};
        }

        .align-right { text-align: right; }
        .align-center { text-align: center; }
        .align-left { text-align: left; }

        /* Angka, tanggal, dan penanda dijaga utuh dalam satu baris. */
        .nowrap { white-space: nowrap; }

        .muted { color: {{ ExportTheme::css('MUTED') }}; }

        /* Sel tautan: dibuka pembaca PDF, jadi tetap ditandai selayaknya pranala. */
        .tautan { color: {{ ExportTheme::css('LINK') }}; text-decoration: underline; font-weight: 700; }

        .badge {
            /* Lencana tidak pernah patah di tengah kata, sesempit apa pun kolomnya. */
            white-space: nowrap;
            padding: 1px 5px;
            font-size: 7.5px;
            font-weight: 700;
            letter-spacing: 0.02em;
            border: 1px solid {{ ExportTheme::css('HAIRLINE') }};
            background: #fff;
            color: {{ ExportTheme::css('MUTED') }};
        }

        .badge-on {
            border-color: #BBF7D0;
            background: #ECFDF3;
            color: #166534;
        }

        .empty-state {
            padding: 26px 8px;
            text-align: center;
            color: {{ ExportTheme::css('MUTED') }};
            font-style: italic;
        }

        .row-count {
            margin-top: 8px;
            font-size: 8px;
            color: {{ ExportTheme::css('MUTED') }};
        }

        @stack('styles')
    </style>
</head>
<body>
    <table class="letterhead">
        <tr>
            @if (filled($logo ?? null))
                <td class="kop-logo"><img src="{{ $logo }}" alt=""></td>
            @endif
            <td>
                <div class="kop-nama">{{ $instansi }}</div>
                <div class="kop-app">{{ $aplikasi }}</div>
            </td>
            <td class="kop-cetak">
                Dicetak<br>
                {{ $generatedAt->locale(ExportTheme::LOCALE)->translatedFormat('d F Y, H:i') }}
            </td>
        </tr>
    </table>

    <table class="rule">
        <tr>
            <td class="accent">&nbsp;</td>
            <td>&nbsp;</td>
        </tr>
    </table>

    <div class="report-title">
        <h1>{{ $title }}</h1>
        @if (filled($subtitle ?? null))
            <div class="subtitle">{{ $subtitle }}</div>
        @endif
    </div>

    @if (filled($ringkasan))
        <table class="summary" cellspacing="6">
            <tr>
                @foreach ($ringkasan as $label => $value)
                    <td style="width: {{ round(100 / count($ringkasan), 2) }}%;">
                        <div class="kartu-label">{{ $label }}</div>
                        <div class="kartu-nilai">{{ $value }}</div>
                    </td>
                @endforeach
            </tr>
        </table>
    @endif

    @yield('content')
</body>
</html>
