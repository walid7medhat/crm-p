<?php

return [
    // off = متوقف | log = يسجل بس | enforce = يمنع بـ 403
    'referer_guard' => env('REFERER_GUARD', 'enforce'),
];