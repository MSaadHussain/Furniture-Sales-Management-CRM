<?php

/*
|--------------------------------------------------------------------------
| Google Sheets mirror
|--------------------------------------------------------------------------
| Pushes orders, customers and products into a Google Sheet through an Apps
| Script Web App. There is no default URL or secret: until an operator fills
| both in, the sync is simply off and the CRM behaves exactly as before.
*/

return [
    'enabled' => env('GOOGLE_SHEETS_ENABLED', false),

    // Apps Script Web App deployment URL (…/exec).
    'url' => env('GOOGLE_SHEETS_WEBAPP_URL'),

    // Shared secret, identical to SHARED_SECRET inside the Apps Script.
    'secret' => env('GOOGLE_SHEETS_SECRET'),

    // Rows pushed per HTTP call. Apps Script stops at 6 minutes per request,
    // so batches stay small enough to finish well inside that.
    'batch_size' => (int) env('GOOGLE_SHEETS_BATCH_SIZE', 200),

    'timeout' => (int) env('GOOGLE_SHEETS_TIMEOUT', 60),
];
