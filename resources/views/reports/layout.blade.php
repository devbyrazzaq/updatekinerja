@php
    use App\Exports\ExportTheme;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Laporan' }}</title>
    <style>
        {!! ExportTheme::fontFaceCss() !!}

        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --ink: {{ ExportTheme::css('INK') }};
            --navy: {{ ExportTheme::css('NAVY') }};
            --gold: {{ ExportTheme::css('GOLD') }};
            --zebra: {{ ExportTheme::css('ZEBRA') }};
            --surface: {{ ExportTheme::css('SURFACE') }};
            --hairline: {{ ExportTheme::css('HAIRLINE') }};
            --muted: {{ ExportTheme::css('MUTED') }};
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            font-weight: 400;
            color: var(--ink);
            font-size: 9.5px;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* --- Kop instansi ------------------------------------------------ */

        .letterhead {
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 8px;
        }

        .letterhead img {
            width: 34px;
            height: 34px;
            object-fit: contain;
        }

        .letterhead .institution {
            flex: 1;
        }

        .letterhead .institution .name {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }

        .letterhead .institution .app {
            font-size: 8.5px;
            color: var(--muted);
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .letterhead .printed {
            text-align: right;
            font-size: 8.5px;
            color: var(--muted);
        }

        /* Aturan dua warna: garis navy tebal ditimpa aksen emas pendek. */
        .rule {
            height: 2px;
            background: var(--navy);
            position: relative;
        }

        .rule::after {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 56px;
            height: 2px;
            background: var(--gold);
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
            color: var(--muted);
            max-width: 78%;
        }

        /* --- Kartu ringkasan --------------------------------------------- */

        .summary {
            display: flex;
            gap: 8px;
            margin-top: 12px;
        }

        .summary .card {
            flex: 1;
            border: 1px solid var(--hairline);
            border-radius: 8px;
            background: var(--surface);
            padding: 8px 10px;
        }

        .summary .card .label {
            font-size: 7.5px;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.07em;
        }

        .summary .card .value {
            margin-top: 2px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: -0.01em;
        }

        /* --- Tabel -------------------------------------------------------- */

        table {
            width: 100%;
            margin-top: 14px;
            border-collapse: collapse;
            table-layout: fixed;
        }

        /* Kepala tabel diulang di tiap halaman, dan tidak ada baris yang terbelah. */
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }

        thead th {
            background: var(--navy);
            color: #fff;
            font-size: 8px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            text-align: left;
            padding: 7px 8px;
            vertical-align: middle;
            /* Kolom sempit pada tabel lebar: label panjang harus patah, bukan
               melimpah ke kolom sebelahnya. */
            overflow-wrap: anywhere;
            word-break: break-word;
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

        thead th:first-child { border-top-left-radius: 5px; }
        thead th:last-child { border-top-right-radius: 5px; }

        tbody td {
            padding: 6px 8px;
            border-bottom: 1px solid var(--hairline);
            vertical-align: top;
            word-break: break-word;
        }

        tbody tr:nth-child(even) td { background: var(--zebra); }

        /* Pita pembatas antar kelompok baris, mis. bulan pencairan. Latarnya
           ditegaskan agar tidak tertimpa selang-seling, dan pita tidak boleh
           tertinggal sendirian di kaki halaman tanpa baris yang dipayunginya. */
        .group-band { page-break-after: avoid; }

        .group-band td {
            background: var(--surface) !important;
            border-top: 2px solid var(--navy);
            border-bottom: 1px solid var(--navy);
            padding: 5px 8px;
            font-size: 8px;
            font-weight: 700;
            color: var(--navy);
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .col-index {
            width: 26px;
            color: var(--muted);
            font-variant-numeric: tabular-nums;
        }

        .align-right { text-align: right; font-variant-numeric: tabular-nums; }
        .align-center { text-align: center; }
        .align-left { text-align: left; }

        /* Angka, tanggal, dan penanda dijaga utuh dalam satu baris. */
        .nowrap { white-space: nowrap; word-break: normal; }

        .muted { color: var(--muted); }

        /* Sel tautan: dibuka pembaca PDF, jadi tetap ditandai selayaknya pranala. */
        .tautan { color: #1d4ed8; text-decoration: underline; font-weight: 600; }

        .badge {
            display: inline-block;
            /* Lencana tidak pernah patah di tengah kata, sesempit apa pun kolomnya. */
            white-space: nowrap;
            padding: 1px 7px;
            border-radius: 999px;
            font-size: 7.5px;
            font-weight: 600;
            letter-spacing: 0.02em;
            border: 1px solid var(--hairline);
            background: #fff;
            color: var(--muted);
        }

        .badge-on {
            border-color: #bbf7d0;
            background: #ecfdf3;
            color: #166534;
        }

        .empty-state {
            padding: 26px 8px;
            text-align: center;
            color: var(--muted);
            font-style: italic;
        }

        .row-count {
            margin-top: 8px;
            font-size: 8px;
            color: var(--muted);
        }
    </style>
</head>
<body>
    <div class="letterhead">
        @if (filled($logo ?? null))
            <img src="data:image/png;base64,{{ base64_encode(file_get_contents($logo)) }}" alt="">
        @endif
        <div class="institution">
            <div class="name">{{ $instansi }}</div>
            <div class="app">{{ $aplikasi }}</div>
        </div>
        <div class="printed">
            Dicetak<br>
            {{ $generatedAt->locale(ExportTheme::LOCALE)->translatedFormat('d F Y, H:i') }}
        </div>
    </div>
    <div class="rule"></div>

    <div class="report-title">
        <h1>{{ $title }}</h1>
        @if (filled($subtitle ?? null))
            <div class="subtitle">{{ $subtitle }}</div>
        @endif
    </div>

    @if (filled($summary ?? []))
        <div class="summary">
            @foreach ($summary as $label => $value)
                <div class="card">
                    <div class="label">{{ $label }}</div>
                    <div class="value">{{ $value }}</div>
                </div>
            @endforeach
        </div>
    @endif

    @yield('content')
</body>
</html>
