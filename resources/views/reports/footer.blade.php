@php
    use App\Exports\ExportTheme;

    /**
     * Catatan kaki dirender di luar dokumen utama — Chromium mencetaknya sebagai
     * dokumen terpisah, mPDF sebagai blok tersendiri — jadi CSS halaman utama tidak
     * berlaku dan gayanya ditulis ulang di sini. Penanda nomor halamannya berbeda
     * per mesin, karena itu dioper sebagai HTML jadi dari kelas mesinnya.
     */
    $engine ??= 'browsershot';
@endphp
@if ($engine !== 'mpdf')
    <style>
        {!! ExportTheme::fontFaceCss() !!}
    </style>
    {{-- Chromium mencetak catatan kaki selebar kertas penuh, jadi jarak tepinya
         disamakan sendiri dengan margin halaman. mPDF sudah menaruhnya di dalam
         margin, jadi tidak perlu. --}}
    <div style="padding: 0 12mm;">
@endif
<table style="width: 100%; border-collapse: collapse; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 7px; color: {{ ExportTheme::css('MUTED') }};">
    <tr>
        <td style="border-top: 0.5px solid {{ ExportTheme::css('HAIRLINE') }}; padding: 4px 0 0 0; text-align: left;">{{ $note }}</td>
        <td style="border-top: 0.5px solid {{ ExportTheme::css('HAIRLINE') }}; padding: 4px 0 0 0; text-align: right;">Halaman {!! $pageNumber !!} dari {!! $totalPages !!}</td>
    </tr>
</table>
@if ($engine !== 'mpdf')
    </div>
@endif
