<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Path Binary Chrome/Chromium
    |--------------------------------------------------------------------------
    |
    | Path absolut ke binary Chrome/Chromium yang dipakai Browsershot untuk
    | merender PDF. Biarkan null di lokal (memakai Chrome unduhan puppeteer).
    | Di server, set BROWSERSHOT_CHROME_PATH ke Chromium sistem, mis.
    | "/usr/bin/chromium", agar tidak bergantung pada unduhan puppeteer.
    |
    */

    'chrome_path' => env('BROWSERSHOT_CHROME_PATH'),

];
