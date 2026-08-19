<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mesin Render PDF
    |--------------------------------------------------------------------------
    |
    | "mpdf" merender laporan sepenuhnya di dalam PHP sehingga tidak menuntut
    | binary apa pun di server — inilah pilihan bawaan karena paling tahan
    | dipindah antar hosting. "browsershot" merender lewat headless Chromium:
    | hasilnya lebih setia pada CSS modern, tetapi hanya jalan di mesin yang
    | benar-benar punya Chrome/Chromium terpasang.
    |
    | Supported: "mpdf", "browsershot"
    |
    */

    'engine' => env('PDF_ENGINE', 'mpdf'),

    /*
    |--------------------------------------------------------------------------
    | Path Binary Chrome/Chromium
    |--------------------------------------------------------------------------
    |
    | Hanya dipakai mesin "browsershot". Path absolut ke binary Chrome/Chromium,
    | mis. "/usr/bin/chromium". Biarkan kosong untuk memakai Chrome unduhan
    | puppeteer — yang berarti `npx puppeteer browsers install` wajib sudah
    | dijalankan di mesin tersebut.
    |
    */

    'chrome_path' => env('BROWSERSHOT_CHROME_PATH'),

    /*
    |--------------------------------------------------------------------------
    | Setelan mPDF
    |--------------------------------------------------------------------------
    |
    | mPDF tidak mengenal @font-face maupun .woff2, jadi font laporan didaftarkan
    | dari berkas .ttf statis di `resources/fonts`. Nama keluarganya sengaja tanpa
    | spasi ("plusjakartasans") karena begitulah mPDF menormalkan `font-family`
    | pada CSS, sehingga view yang sama tetap terbaca kedua mesin.
    |
    */

    'mpdf' => [

        'temp_dir' => storage_path('app/mpdf'),

        'font_dir' => resource_path('fonts/plus-jakarta-sans'),

        'font_family' => 'plusjakartasans',

        'font_data' => [
            'R' => 'PlusJakartaSans-Regular.ttf',
            'B' => 'PlusJakartaSans-Bold.ttf',
            'useOTL' => 0,
            'useKashida' => 0,
        ],

    ],

];
