<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/src/bootstrap.php';
use Camagru\Config;
foreach (['.env','.git','storage'] as $private) {
    if (file_exists(Config::root() . '/public/' . $private)) { throw new RuntimeException('Private files in document root'); }
}
if (file_exists(Config::root() . '/.env')) { throw new RuntimeException('Local environment file baked into image'); }
foreach (glob(Config::storage() . '/logs/*') as $log) {
    if (filesize($log) !== 0) { throw new RuntimeException('Unexpected application/server error log: ' . basename($log)); }
}
echo "Container privacy and error-log checks passed.\n";
