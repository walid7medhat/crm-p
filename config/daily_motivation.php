<?php

return [
    /*
    | Business day for Daily Edge. The CRM database session is UAE (+04:00) and
    | APP_TIMEZONE is unset (so the framework clock falls back to UTC). A workday
    | message must change at midnight in Dubai, not at 04:00.
    */
    'timezone' => 'Asia/Dubai',

    // Active users receive the day's English message at this Dubai time.
    'send_time' => '09:00',

    'cycle_length' => 120,

    'workbook_name' => 'OIA_Daily_Sales_Motivation_120.xlsx',
];
