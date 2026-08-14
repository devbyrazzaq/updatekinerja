<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * Cara pembentukan Penawaran Program Kerja dari Acuan pada satu tahun kerja.
 */
enum EnumModeGenerate: string implements HasColor, HasDescription, HasLabel
{
    /**
     * Pembentukan pertama. Ditolak bila penawaran tahun kerja tersebut sudah ada.
     */
    case Baru = 'baru';

    /**
     * Hanya berpindah konteks; penawaran yang sudah ada dibiarkan apa adanya.
     */
    case Lewati = 'lewati';

    /**
     * Menyelaraskan penawaran yang sudah ada dengan acuan terkini dan menambah
     * penawaran untuk acuan baru. Penawaran lama tidak dihapus.
     */
    case Sinkron = 'sinkron';

    /**
     * Menghapus seluruh penawaran tahun kerja tersebut lalu membentuknya kembali.
     * Ditolak bila ada penawaran yang sudah memiliki pengajuan.
     */
    case Ulang = 'ulang';

    public function getLabel(): string
    {
        return match ($this) {
            self::Baru => 'Generate Baru',
            self::Lewati => 'Biarkan Apa Adanya',
            self::Sinkron => 'Perbarui Data Lama',
            self::Ulang => 'Generate Ulang',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Baru => 'Membentuk penawaran untuk seluruh acuan aktif pada kelompok terpilih.',
            self::Lewati => 'Hanya berpindah konteks. Penawaran, pengajuan, dan realisasi tahun kerja ini tidak disentuh sama sekali.',
            self::Sinkron => 'Menyelaraskan penawaran yang sudah ada dengan acuan terkini dan menambahkan penawaran untuk acuan baru. Perubahan manual pada penawaran akan tertimpa, tetapi pengajuan yang sudah berjalan tetap aman.',
            self::Ulang => 'Menghapus seluruh penawaran tahun kerja ini lalu membentuknya kembali dari nol. Gagal bila ada penawaran yang sudah memiliki pengajuan.',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Baru => 'success',
            self::Lewati => 'gray',
            self::Sinkron => 'info',
            self::Ulang => 'danger',
        };
    }
}
