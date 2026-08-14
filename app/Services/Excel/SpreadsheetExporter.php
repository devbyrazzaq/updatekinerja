<?php

namespace App\Services\Excel;

use App\Enums\EnumFormatKolom;
use App\Exports\Export;
use App\Exports\ExportTheme;
use App\Models\Setting;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\Common\Entity\Sheet;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Menulis sebuah {@see Export} menjadi berkas .xlsx memakai openspout, lalu
 * mengembalikannya sebagai respons unduhan yang otomatis dihapus setelah dikirim.
 *
 * Berkas dirapikan mengikuti {@see ExportTheme}: blok kepala berisi judul, anak
 * judul, dan keterangan cetak; kepala tabel berpita navy yang dibekukan dan diberi
 * filter; baris data selang-seling dengan perataan serta format angka mengikuti
 * {@see EnumFormatKolom} tiap kolom.
 *
 * Kepala tabel ditulis dua tingkat: label manusiawi ("Unit Kerja") untuk dibaca,
 * lalu key mesin (`unit_kerja`) sebagai keterangan kecil di bawahnya. Tingkat kedua
 * itulah yang dicari {@see SpreadsheetImporter} sehingga berkas hasil ekspor tetap
 * bisa disunting lalu diimpor kembali.
 */
class SpreadsheetExporter
{
    /**
     * Nomor baris tempat label kolom ditulis, dihitung setelah blok kepala dokumen.
     * Baris key mesin selalu tepat di bawahnya.
     */
    protected int $labelRowNumber = 1;

    public function download(Export $export): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'export_').'.xlsx';

        $this->write($export, $path);

        return response()
            ->download($path, $export->filename().'.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend();
    }

    /**
     * Tulis berkas .xlsx ke path tertentu. Dipisah dari {@see download()} agar bisa
     * dipakai langsung (mis. dalam pengujian) tanpa merakit respons HTTP.
     */
    public function write(Export $export, string $path): void
    {
        $columns = $export->columns();
        $columnCount = max(count($columns), 1);

        $options = new Options;
        $options->DEFAULT_ROW_STYLE = (new Style)
            ->setFontName(ExportTheme::FONT)
            ->setFontSize(10)
            ->setFontColor(ExportTheme::INK);

        $writer = new Writer($options);
        $writer->setCreator(Setting::brandNama());
        $writer->openToFile($path);

        $sheet = $writer->getCurrentSheet();
        $sheet->setName($this->sheetName($export));

        $this->writeDocumentHeader($writer, $options, $export, $columnCount);
        $this->writeTableHeader($writer, $columns);

        $widths = $this->initialWidths($columns);
        $dataRowCount = $this->writeDataRows($writer, $columns, $export, $widths);

        $this->applyColumnWidths($sheet, $widths);
        $this->applySheetView($sheet, $columnCount, $dataRowCount);

        $writer->close();
    }

    /**
     * Blok kepala dokumen: judul, anak judul, keterangan cetak, dan garis aksen emas.
     *
     * @param  positive-int  $columnCount
     */
    protected function writeDocumentHeader(Writer $writer, Options $options, Export $export, int $columnCount): void
    {
        $lastColumnIndex = $columnCount - 1;
        $rowNumber = 1;

        $lines = [
            [$export->title(), $this->titleStyle()],
        ];

        if (filled($subtitle = $export->subtitle())) {
            $lines[] = [$subtitle, $this->subtitleStyle()];
        }

        $lines[] = [$this->metaLine($export), $this->metaStyle()];

        foreach ($lines as [$text, $style]) {
            $writer->addRow($this->mergedRow($text, $style, $columnCount));
            $options->mergeCells(0, $rowNumber, $lastColumnIndex, $rowNumber);
            $rowNumber++;
        }

        // Garis aksen: satu baris pendek berlatar emas sebagai penutup blok kepala.
        $writer->addRow($this->mergedRow('', $this->accentStyle(), $columnCount)->setHeight(6));
        $options->mergeCells(0, $rowNumber, $lastColumnIndex, $rowNumber);
        $rowNumber++;

        $writer->addRow(Row::fromValues([''])->setHeight(6));
        $rowNumber++;

        $this->labelRowNumber = $rowNumber;
    }

    /**
     * Kepala tabel dua tingkat: label manusiawi di atas, key mesin di bawahnya.
     * Tingkat kedua membuat berkas ini bisa disunting lalu diimpor kembali tanpa
     * mengorbankan keterbacaan label.
     *
     * @param  list<array{key: string, label: string, format: EnumFormatKolom}>  $columns
     */
    protected function writeTableHeader(Writer $writer, array $columns): void
    {
        $writer->addRow(
            Row::fromValues(array_column($columns, 'label'), $this->labelRowStyle())->setHeight(26)
        );

        $writer->addRow(
            Row::fromValues(array_column($columns, 'key'), $this->keyRowStyle())->setHeight(14)
        );
    }

    /**
     * @param  list<array{key: string, label: string, format: EnumFormatKolom}>  $columns
     * @param  array<int, float>  $widths
     * @return int Jumlah baris data yang tertulis.
     */
    protected function writeDataRows(Writer $writer, array $columns, Export $export, array &$widths): int
    {
        $written = 0;

        foreach ($export->rows() as $row) {
            $values = array_values(is_array($row) ? $row : iterator_to_array($row));
            $striped = $written % 2 === 1;

            $cellValues = [];
            $cellStyles = [];

            foreach ($columns as $index => $column) {
                $value = $values[$index] ?? null;
                $format = $column['format'];

                $cellValues[] = $this->cellValue($value, $format);
                $cellStyles[$index] = $this->dataCellStyle($format, $striped);

                $widths[$index] = max($widths[$index], $this->measure($value));
            }

            $writer->addRow(Row::fromValuesWithStyles($cellValues, null, $cellStyles));
            $written++;
        }

        if ($written === 0) {
            $writer->addRow(
                $this->mergedRow('Belum ada data untuk diekspor.', $this->emptyStateStyle(), count($columns))
            );
        }

        return $written;
    }

    /**
     * Nilai numerik ditulis sebagai angka supaya format sel Excel berlaku dan
     * kolomnya bisa dijumlah; sisanya dibiarkan apa adanya.
     */
    protected function cellValue(mixed $value, EnumFormatKolom $format): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        $isNumericColumn = in_array($format, [
            EnumFormatKolom::Angka,
            EnumFormatKolom::Uang,
            EnumFormatKolom::Persen,
        ], true);

        if ($isNumericColumn && is_numeric($value)) {
            return (float) $value;
        }

        return $value;
    }

    /**
     * @param  list<array{key: string, label: string, format: EnumFormatKolom}>  $columns
     * @return array<int, float>
     */
    protected function initialWidths(array $columns): array
    {
        return array_map(
            fn (array $column): float => max(
                $column['format']->lebarMinimum(),
                (float) mb_strlen($column['label']) + 4,
                (float) mb_strlen($column['key']) + 4,
            ),
            $columns,
        );
    }

    /**
     * @param  array<int, float>  $widths
     */
    protected function applyColumnWidths(Sheet $sheet, array $widths): void
    {
        foreach ($widths as $index => $width) {
            $sheet->setColumnWidth(
                min(max($width, ExportTheme::COLUMN_WIDTH_MIN), ExportTheme::COLUMN_WIDTH_MAX),
                $index + 1,
            );
        }
    }

    /**
     * Bekukan blok kepala + kepala tabel, pasang filter kolom pada baris key, dan
     * ulangi kedua baris kepala tabel di tiap halaman cetak.
     */
    protected function applySheetView(Sheet $sheet, int $columnCount, int $dataRowCount): void
    {
        $keyRowNumber = $this->labelRowNumber + 1;

        $sheet->setSheetView(
            (new SheetView)->setFreezeRow($keyRowNumber + 1)
        );

        $sheet->setPrintTitleRows("{$this->labelRowNumber}:{$keyRowNumber}");

        if ($dataRowCount > 0) {
            $sheet->setAutoFilter(new AutoFilter(
                0,
                $keyRowNumber,
                $columnCount - 1,
                $keyRowNumber + $dataRowCount,
            ));
        }
    }

    /**
     * Baris berisi satu teks pada kolom pertama; sisa kolomnya ikut bergaya sama
     * supaya latar merentang penuh setelah sel digabung.
     */
    protected function mergedRow(string $text, Style $style, int $columnCount): Row
    {
        $values = array_fill(0, max($columnCount, 1), '');
        $values[0] = $text;

        return Row::fromValues($values, $style);
    }

    protected function metaLine(Export $export): string
    {
        return Setting::brandInstansi()
            .' · Dicetak '.now()->locale(ExportTheme::LOCALE)->translatedFormat('d F Y, H:i')
            .' · '.count($export->headings()).' kolom';
    }

    protected function sheetName(Export $export): string
    {
        // Excel membatasi nama sheet 31 karakter dan melarang beberapa tanda baca.
        return mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', '', $export->title()) ?? 'Data', 0, 31);
    }

    /**
     * Panjang tampilan sebuah nilai, dipakai untuk menghitung lebar kolom.
     */
    protected function measure(mixed $value): float
    {
        if ($value === null) {
            return 0.0;
        }

        if ($value instanceof \DateTimeInterface) {
            return 18.0;
        }

        return (float) mb_strlen((string) $value) + 3;
    }

    protected function titleStyle(): Style
    {
        return (new Style)
            ->setFontName(ExportTheme::FONT)
            ->setFontSize(16)
            ->setFontBold()
            ->setFontColor(ExportTheme::INK)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER);
    }

    protected function subtitleStyle(): Style
    {
        return (new Style)
            ->setFontName(ExportTheme::FONT)
            ->setFontSize(10)
            ->setFontColor(ExportTheme::MUTED)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER);
    }

    protected function metaStyle(): Style
    {
        return (new Style)
            ->setFontName(ExportTheme::FONT)
            ->setFontSize(9)
            ->setFontColor(ExportTheme::MUTED)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER);
    }

    protected function accentStyle(): Style
    {
        return (new Style)->setBackgroundColor(ExportTheme::GOLD);
    }

    protected function labelRowStyle(): Style
    {
        return (new Style)
            ->setFontName(ExportTheme::FONT)
            ->setFontSize(10)
            ->setFontBold()
            ->setFontColor(ExportTheme::WHITE)
            ->setBackgroundColor(ExportTheme::NAVY)
            ->setCellAlignment(CellAlignment::LEFT)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
            ->setShouldWrapText();
    }

    /**
     * Tingkat kedua kepala tabel — key mesin, sengaja kecil dan redup supaya hadir
     * sebagai keterangan teknis, bukan bersaing dengan label.
     */
    protected function keyRowStyle(): Style
    {
        return (new Style)
            ->setFontName(ExportTheme::FONT)
            ->setFontSize(8)
            ->setFontItalic()
            ->setFontColor(ExportTheme::MUTED)
            ->setBackgroundColor(ExportTheme::SURFACE)
            ->setCellAlignment(CellAlignment::LEFT)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
            ->setBorder($this->hairline());
    }

    protected function emptyStateStyle(): Style
    {
        return (new Style)
            ->setFontName(ExportTheme::FONT)
            ->setFontSize(10)
            ->setFontItalic()
            ->setFontColor(ExportTheme::MUTED)
            ->setCellAlignment(CellAlignment::CENTER);
    }

    protected function dataCellStyle(EnumFormatKolom $format, bool $striped): Style
    {
        $style = (new Style)
            ->setFontName(ExportTheme::FONT)
            ->setFontSize(10)
            ->setFontColor(ExportTheme::INK)
            ->setCellAlignment($this->alignment($format))
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
            ->setBorder($this->hairline());

        if ($striped) {
            $style->setBackgroundColor(ExportTheme::ZEBRA);
        }

        if (($excelFormat = $format->formatExcel()) !== null) {
            $style->setFormat($excelFormat);
        }

        return $style;
    }

    protected function alignment(EnumFormatKolom $format): string
    {
        return match ($format->perataan()) {
            'right' => CellAlignment::RIGHT,
            'center' => CellAlignment::CENTER,
            default => CellAlignment::LEFT,
        };
    }

    protected function hairline(): Border
    {
        return new Border(
            new BorderPart(Border::BOTTOM, ExportTheme::HAIRLINE, Border::WIDTH_THIN, Border::STYLE_SOLID),
        );
    }
}
