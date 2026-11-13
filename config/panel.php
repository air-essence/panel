<?php

return [
    // Ile dni wstecz (od daty zamówienia) sprawdzać rozbieżności. Zamówienia ze sprawami sprawdzamy zawsze.
    'discrepancy_days' => (int) env('PANEL_DISCREPANCY_DAYS', 90),
];
