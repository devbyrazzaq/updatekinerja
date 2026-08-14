@php
    use App\Exports\ExportTheme;
@endphp
{{--
    Catatan kaki dirender Chromium sebagai dokumen terpisah: CSS halaman utama tidak
    berlaku di sini, jadi font dan warna disematkan ulang. Kelas `pageNumber` dan
    `totalPages` diisi sendiri oleh Chromium saat mencetak.
--}}
<style>
    {!! ExportTheme::fontFaceCss() !!}

    #report-footer {
        width: 100%;
        padding: 0 12mm;
        font-family: 'Plus Jakarta Sans', -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        font-size: 7px;
        color: {{ ExportTheme::css('MUTED') }};
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-top: 0.5px solid {{ ExportTheme::css('HAIRLINE') }};
        padding-top: 4px;
        -webkit-print-color-adjust: exact;
    }
</style>
<div id="report-footer">
    <span>{{ $note }}</span>
    <span>Halaman <span class="pageNumber"></span> dari <span class="totalPages"></span></span>
</div>
