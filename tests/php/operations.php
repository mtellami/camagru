<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/src/bootstrap.php';

use Camagru\Config;
use Camagru\Database\Database;
use Camagru\Mail\Outbox;

$snapshotPath = Config::storage() . '/tmp/persistence-check.json';
if (($argv[1] ?? '') === 'snapshot') {
    $files = [];
    foreach (Database::query("SELECT media_key,kind FROM photos WHERE state='ready'")->fetchAll() as $photo) {
        foreach (['.jpg','-thumb.jpg',...($photo['kind'] === 'gif' ? ['.gif'] : [])] as $suffix) {
            $name = $photo['media_key'] . $suffix;
            $files[$name] = hash_file('sha256',Config::storage() . '/media/' . $name);
        }
    }
    file_put_contents($snapshotPath,json_encode(['users' => (int) Database::query('SELECT COUNT(*) FROM users')->fetchColumn(), 'files' => $files],JSON_THROW_ON_ERROR));
    echo "Persistence snapshot recorded.\n";
    exit;
}
if (($argv[1] ?? '') !== 'verify') { throw new RuntimeException('Use snapshot or verify.'); }
$snapshot = json_decode(file_get_contents($snapshotPath),true,512,JSON_THROW_ON_ERROR);
if ((int) Database::query('SELECT COUNT(*) FROM users')->fetchColumn() < $snapshot['users']) { throw new RuntimeException('Users were lost during restart.'); }
foreach ($snapshot['files'] as $name => $hash) {
    if (!is_file(Config::storage() . '/media/' . $name) || hash_file('sha256',Config::storage() . '/media/' . $name) !== $hash) { throw new RuntimeException('Media changed during restart.'); }
}
$id = Database::query('SELECT id FROM users ORDER BY id LIMIT 1')->fetchColumn();
if (!$id) { throw new RuntimeException('Run browser tests before the worker recovery check.'); }
$event = 'operations:' . bin2hex(random_bytes(16));
Outbox::queue($event,(int) $id,'operations@example.test','Camagru worker recovery check','Local infrastructure check.');
$deadline = time() + 25;
do {
    if (Database::query('SELECT sent_at FROM email_outbox WHERE event_key=?',[$event])->fetchColumn()) {
        unlink($snapshotPath);
        echo "Database, media persistence, and email-worker recovery checks passed.\n";
        exit;
    }
    usleep(250000);
} while (time() < $deadline);
throw new RuntimeException('The worker did not recover after database restart.');
