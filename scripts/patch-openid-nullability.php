<?php

declare(strict_types=1);

// Upstream 1.0.2 declares nullable defaults without nullable parameter types.
// Apply the PHP 8.4 compatibility change until an upstream release includes it.
$path = __DIR__ . '/../vendor/jumbojett/openid-connect-php/src/OpenIDConnectClient.php';
$source = file_get_contents($path);
if ($source === false) { throw new RuntimeException('OIDC source is missing'); }
$original = '77e9fd82a5977de3557e209082a51d7ea51feb43fdc94e3b231bf1bde83fbaeb';
$patched = '99c0c8358d7deebdef4f60a5db03d02cb66b1397a78d1d1a0cec511ac4381db0';
$hash = hash('sha256', $source);
if ($hash === $patched) { exit(0); }
if ($hash !== $original) {
    throw new RuntimeException('OIDC source changed; review and remove or update the nullability compatibility patch');
}
$source = preg_replace('/(?<![?\\w])(string|array|int|bool) (\\$\\w+) = null/', '?$1 $2 = null', $source, -1, $count);
if ($source === null || $count !== 12 || hash('sha256', $source) !== $patched) {
    throw new RuntimeException('Unexpected OIDC nullability patch result');
}
if (file_put_contents($path, $source) === false) { throw new RuntimeException('Cannot patch OIDC source'); }
echo "OIDC PHP 8.4 nullability compatibility patch applied.\n";
