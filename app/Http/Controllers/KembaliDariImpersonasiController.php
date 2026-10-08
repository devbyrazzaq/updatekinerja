<?php

namespace App\Http\Controllers;

use App\Services\Impersonasi;
use Illuminate\Http\RedirectResponse;

/**
 * Tombol "Kembali ke akun saya" pada banner impersonasi. Rutenya rute terautentikasi
 * panel, jadi tetap bisa dipanggil walaupun akun yang sedang dimasuki tidak punya
 * permission apa pun.
 */
class KembaliDariImpersonasiController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        if (! Impersonasi::aktif()) {
            return redirect()->to(filament()->getUrl());
        }

        return redirect()->to(Impersonasi::kembali());
    }
}
