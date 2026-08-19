@extends('reports.layout')

@php
    use App\Exports\ExportTheme;

    $tanggalTandaTangan = $tanggal->locale(ExportTheme::LOCALE)->translatedFormat('d F Y');
    $barisTanggal = filled($kota) ? $kota.', '.$tanggalTandaTangan : $tanggalTandaTangan;
@endphp

@section('content')
    <style>
        /* --- Keterangan jadwal ------------------------------------------- */

        .meta {
            display: flex;
            gap: 8px;
            margin-top: 12px;
        }

        .meta .item {
            flex: 1;
            border: 1px solid var(--hairline);
            border-radius: 8px;
            background: var(--surface);
            padding: 8px 10px;
        }

        .meta .item .label {
            font-size: 7.5px;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.07em;
        }

        .meta .item .value {
            margin-top: 2px;
            font-size: 10px;
            font-weight: 700;
        }

        /* --- Rekening tujuan di dalam sel -------------------------------- */

        .rekening .bank { font-weight: 600; }

        .rekening .nomor {
            font-variant-numeric: tabular-nums;
            letter-spacing: 0.02em;
        }

        .rekening .pemilik {
            color: var(--muted);
            font-size: 8px;
        }

        tfoot td {
            border-top: 2px solid var(--navy);
            border-bottom: none;
            padding: 7px 8px;
            font-weight: 700;
            background: var(--surface) !important;
        }

        /* --- Catatan ------------------------------------------------------ */

        .catatan {
            margin-top: 12px;
            border: 1px solid var(--hairline);
            border-radius: 8px;
            padding: 8px 10px;
            font-size: 9px;
        }

        .catatan .label {
            font-size: 7.5px;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.07em;
            margin-bottom: 2px;
        }

        /* --- Blok tanda tangan -------------------------------------------- */

        /* Tanda tangan tidak boleh terbelah antar halaman: jabatan, nama, dan
           nomor karyawan harus terbaca sebagai satu kesatuan. */
        .signature {
            margin-top: 26px;
            display: flex;
            justify-content: flex-end;
            page-break-inside: avoid;
        }

        .signature .block {
            width: 42%;
            text-align: center;
            font-size: 9.5px;
        }

        .signature .date { margin-bottom: 4px; }

        .signature .role { font-weight: 600; }

        /* Ruang kosong tempat tanda tangan dibubuhkan. */
        .signature .gap { height: 62px; }

        .signature .name {
            font-weight: 700;
            text-decoration: underline;
            text-underline-offset: 3px;
        }

        .signature .number {
            margin-top: 2px;
            color: var(--muted);
            font-variant-numeric: tabular-nums;
        }
    </style>

    <div class="meta">
        <div class="item">
            <div class="label">Tanggal Pencairan</div>
            <div class="value">{{ $jadwal->tanggal_pencairan?->locale(ExportTheme::LOCALE)->translatedFormat('d F Y') ?? '—' }}</div>
        </div>
        <div class="item">
            <div class="label">Jumlah Realisasi</div>
            <div class="value">{{ number_format(count($rincian), 0, ',', '.') }} realisasi</div>
        </div>
        <div class="item">
            <div class="label">Total Pencairan</div>
            <div class="value">Rp {{ number_format($total, 0, ',', '.') }}</div>
        </div>
        <div class="item">
            <div class="label">Status</div>
            <div class="value">{{ $jadwal->status?->getLabel() ?? '—' }}</div>
        </div>
    </div>

    <table>
        <colgroup>
            <col class="col-index">
            <col style="width: 20%;">
            <col style="width: 26%;">
            <col style="width: 14%;">
            <col style="width: 20%;">
            <col style="width: 16%;">
        </colgroup>
        <thead>
            <tr>
                <th class="col-index">#</th>
                <th>Unit Kerja</th>
                <th>Kegiatan</th>
                <th>Cara Pembayaran</th>
                <th>Rekening Tujuan</th>
                <th class="align-right">Nominal</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rincian as $index => $baris)
                <tr>
                    <td class="col-index">{{ $index + 1 }}</td>
                    <td>{{ $baris['unit_kerja'] }}</td>
                    <td>{{ $baris['kegiatan'] }}</td>
                    <td>{{ $baris['metode'] }}</td>
                    <td class="rekening">
                        @if ($baris['nomor_rekening'] === '-')
                            <span class="muted">—</span>
                        @else
                            <div class="bank">{{ $baris['bank'] }}</div>
                            <div class="nomor">{{ $baris['nomor_rekening'] }}</div>
                            <div class="pemilik">a.n. {{ $baris['atas_nama'] }}</div>
                        @endif
                    </td>
                    <td class="align-right nowrap">Rp {{ number_format($baris['nominal'], 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td class="empty-state" colspan="6">Belum ada realisasi yang dijadwalkan pada pencairan ini.</td>
                </tr>
            @endforelse
        </tbody>
        @if (filled($rincian))
            <tfoot>
                <tr>
                    <td colspan="5">Total Pencairan</td>
                    <td class="align-right nowrap">Rp {{ number_format($total, 0, ',', '.') }}</td>
                </tr>
            </tfoot>
        @endif
    </table>

    @if (filled($catatan))
        <div class="catatan">
            <div class="label">Catatan</div>
            {!! $catatan !!}
        </div>
    @endif

    <div class="signature">
        <div class="block">
            <div class="date">{{ $barisTanggal }}</div>
            @if (filled($penandatangan['jabatan']))
                <div class="role">{{ $penandatangan['jabatan'] }}</div>
            @endif
            <div class="gap"></div>
            <div class="name">{{ filled($penandatangan['nama']) ? $penandatangan['nama'] : '(..............................)' }}</div>
            @if (filled($penandatangan['nomor']))
                <div class="number">{{ $penandatangan['nomor'] }}</div>
            @endif
        </div>
    </div>
@endsection
