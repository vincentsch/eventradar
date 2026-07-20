<?php

$proxies = env('TRUSTED_PROXIES');

return [
    // Keep this empty locally. Production should list only its trusted proxy IPs or CIDRs.
    'proxies' => $proxies === '' ? null : $proxies,
];
