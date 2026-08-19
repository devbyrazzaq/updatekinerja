@extends('reports.layout')

@php
    use App\Exports\ExportTheme;

    $tanggalTandaTangan = $tanggal->locale(ExportTheme::LOCALE)->translatedFormat('d F Y');
    $barisTanggal = filled($kota) ? $kota.', '.$tanggalTandaTangan : $tanggalTandaTangan;
@endphp

@push('styles')
    /* --- Keterangan jadwal ------------------------------------------- */

    .meta {
        width: 100%;
        margin-top: 12px;
        border-collapse: separate;
        border-spacing: 6px 0;
    }

    .meta td {
        border: 1px solid {{ ExportTheme::css('HAIRLINE') }};
        background: {{ ExportTheme::css('SURFACE') }};
        padding: 8px 10px;
        vertical-align: top;
        width: 25%;
    }

    /* Nilai pada kartu keterangan dibuat lebih kecil daripada kartu ringkasan:
       isinya tanggal dan status, bukan angka tunggal yang perlu menonjol. */
    .meta-nilai {
        margin-top: 2px;
        font-size: 10px;
        font-weight: 700;
    }

    /* --- Rekening tujuan di dalam sel -------------------------------- */

    .rek-bank { font-weight: 700; }

    .rek-nomor { letter-spacing: 0.02em; }

    .rek-pemilik {
        color: {{ ExportTheme::css('MUTED') }};
        font-size: 8px;
    }

    table.data tfoot td {
        border-top: 2px solid {{ ExportTheme::css('NAVY') }};
        border-bottom: none;
        padding: 7px 8px;
        font-weight: 700;
        background: {{ ExportTheme::css('SURFACE') }};
    }

    /* --- Catatan ------------------------------------------------------ */

    .catatan {
        margin-top: 12px;
        border: 1px solid {{ ExportTheme::css('HAIRLINE') }};
        padding: 8px 10px;
        font-size: 9px;
    }

    .catatan-label {
        font-size: 7.5px;
        font-weight: 700;
        color: {{ ExportTheme::css('MUTED') }};
        text-transform: uppercase;
        letter-spacing: 0.07em;
    }

    /* --- Blok tanda tangan -------------------------------------------- */

    /* Tanda tangan tidak boleh terbelah antar halaman: jabatan, nama, dan
       nomor karyawan harus terbaca sebagai satu kesatuan. */
    .signature {
        width: 100%;
        margin-top: 26px;
        border-collapse: collapse;
        page-break-inside: avoid;
    }

    .signature td {
        vertical-align: top;
        text-align: center;
        font-size: 9.5px;
    }

    .ttd-kosong { width: 58%; }

    .ttd-jabatan { font-weight: 700; }

    /* Ruang kosong tempat tanda tangan dibubuhkan. */
    .ttd-ruang {
        font-size: 62px;
        line-height: 62px;
    }

    .ttd-nama {
        font-weight: 700;
        text-decoration: underline;
    }

    .ttd-nomor {
        color: {{ ExportTheme::css('MUTED') }};
    }
@endpush

@section('content')
    <table class="meta" cellspacing="6">
        <tr>
            <td>
                <div class="kartu-label">Tanggal Pencairan</div>
                <div class="meta-nilai">{{ $jadwal->tanggal_pencairan?->locale(ExportTheme::LOCALE)->translatedFormat('d F Y') ?? '—' }}</div>
            </td>
            <td>
                <div class="kartu-label">Jumlah Realisasi</div>
                <div class="meta-nilai">{{ number_format(count($rincian), 0, ',', '.') }} realisasi</div>
            </td>
            <td>
                <div class="kartu-label">Total Pencairan</div>
                <div class="meta-nilai">Rp {{ number_format($total, 0, ',', '.') }}</div>
            </td>
            <td>
                <div class="kartu-label">Status</div>
                <div class="meta-nilai">{{ $jadwal->status?->getLabel() ?? '—' }}</div>
            </td>
        </tr>
    </table>

    <table class="data">
        <colgroup>
            {{-- Lebar ditulis dua kali: atribut `width` dibaca mPDF, `style` dibaca
                 Chromium. --}}
            <col class="col-index" width="5%" style="width: 5%;">
            <col width="19%" style="width: 19%;">
            <col width="25%" style="width: 25%;">
            <col width="13%" style="width: 13%;">
            <col width="21%" style="width: 21%;">
            <col width="17%" style="width: 17%;">
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
                <tr class="{{ $index % 2 === 1 ? 'zebra' : '' }}">
                    <td class="col-index">{{ $index + 1 }}</td>
                    <td>{{ $baris['unit_kerja'] }}</td>
                    <td>{{ $baris['kegiatan'] }}</td>
                    <td>{{ $baris['metode'] }}</td>
                    <td class="rekening">
                        @if ($baris['nomor_rekening'] === '-')
                            <span class="muted">—</span>
                        @else
                            <div class="rek-bank">{{ $baris['bank'] }}</div>
                            <div class="rek-nomor">{{ $baris['nomor_rekening'] }}</div>
                            <div class="rek-pemilik">a.n. {{ $baris['atas_nama'] }}</div>
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
            <div class="catatan-label">Catatan</div>
            {!! $catatan !!}
        </div>
    @endif

    <table class="signature">
        <tr>
            <td class="ttd-kosong">&nbsp;</td>
            <td>
                <div class="ttd-tanggal">{{ $barisTanggal }}</div>
                @if (filled($penandatangan['jabatan']))
                    <div class="ttd-jabatan">{{ $penandatangan['jabatan'] }}</div>
                @endif
                <div class="ttd-ruang">&nbsp;</div>
                <div class="ttd-nama">{{ filled($penandatangan['nama']) ? $penandatangan['nama'] : '(..............................)' }}</div>
                @if (filled($penandatangan['nomor']))
                    <div class="ttd-nomor">{{ $penandatangan['nomor'] }}</div>
                @endif
            </td>
        </tr>
    </table>
@endsection
