@extends('reports.layout')

@php
    // Lebar kolom dibagi menurut bobot tiap tipe: kolom teks melebar, kolom angka
    // dan penanda cukup sesempit isinya. Tanpa ini nominal panjang patah dua baris.
    //
    // Tabel memakai table-layout: fixed, jadi persentase seluruh kolom data disisakan
    // di bawah 100% — sisanya jatah kolom nomor. Tanpa jatah itu kolom nomor menyusut
    // sampai angka dua digit patah ke bawah.
    $totalBobot = array_sum(array_map(fn (array $column): float => $column['format']->bobotLebar(), $columns));
    $jatahKolomNomor = 6;
    $jatahKolomData = 100 - $jatahKolomNomor;

    // Di atas sepuluh kolom, huruf dan jarak dirapatkan supaya tabel tetap sehalaman.
    $rapat = count($columns) > 10;

    // Nomor baris tempat pita pembatas kelompok disisipkan; kosong berarti tabelnya
    // mengalir tanpa pembatas.
    $groups ??= [];
@endphp

@section('content')
    <table class="data {{ $rapat ? 'dense' : '' }}">
        <colgroup>
            {{-- Lebar ditulis dua kali: atribut `width` dibaca mPDF, `style` dibaca
                 Chromium. --}}
            <col class="col-index" width="{{ $jatahKolomNomor }}%" style="width: {{ $jatahKolomNomor }}%;">
            @foreach ($columns as $column)
                @php $lebar = round($column['format']->bobotLebar() / $totalBobot * $jatahKolomData, 2); @endphp
                <col width="{{ $lebar }}%" style="width: {{ $lebar }}%;">
            @endforeach
        </colgroup>
        <thead>
            <tr>
                <th class="col-index">#</th>
                @foreach ($columns as $column)
                    <th class="align-{{ $column['format']->perataan() }}">{{ $column['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $index => $cells)
                @if (filled($groups[$index] ?? null))
                    <tr class="group-band">
                        <td colspan="{{ count($columns) + 1 }}">{{ $groups[$index] }}</td>
                    </tr>
                @endif
                <tr class="{{ $index % 2 === 1 ? 'zebra' : '' }}">
                    <td class="col-index">{{ $index + 1 }}</td>
                    @foreach ($cells as $cell)
                        {{-- Pada tabel rapat, aturan "jangan patah" dilepas: kolomnya
                             terlalu sempit sehingga isi utuh justru melimpah ke tetangga. --}}
                        <td class="align-{{ $cell['align'] }} {{ $cell['nowrap'] && ! $rapat ? 'nowrap' : '' }}">
                            @if ($cell['badge'])
                                <span class="badge {{ $cell['aktif'] ? 'badge-on' : '' }}">{{ $cell['text'] }}</span>
                            @elseif (filled($cell['url'] ?? null))
                                <a class="tautan" href="{{ $cell['url'] }}">{{ $cell['text'] }}</a>
                            @elseif ($cell['kosong'])
                                <span class="muted">{{ $cell['text'] }}</span>
                            @else
                                {{ $cell['text'] }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td class="empty-state" colspan="{{ count($columns) + 1 }}">Belum ada data untuk dilaporkan.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if (filled($rows))
        <div class="row-count">Total {{ number_format(count($rows), 0, ',', '.') }} baris data.</div>
    @endif
@endsection
