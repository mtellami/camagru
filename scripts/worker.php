<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
use Camagru\Database\Database;
use Camagru\Media\Images;
use Camagru\Mail\Outbox;

$once = in_array('--once', $argv, true);
if (in_array('--retry-failed', $argv, true)) {
    Database::query('UPDATE email_outbox SET failed_at=NULL,attempts=0,next_attempt_at=NOW() WHERE failed_at IS NOT NULL');
    exit;
}
$lastCleanup = 0;
do {
    try {
        $processed = Outbox::once();
        if (time() - $lastCleanup > 60) {
            Images::cleanup();
            Database::query('DELETE FROM rate_limits WHERE expires_at<NOW()');
            Database::query("DELETE FROM account_tokens WHERE expires_at<NOW()-INTERVAL '1 day'");
            $lastCleanup = time();
        }
        if (!$processed && !$once) { sleep(1); }
    } catch (Throwable $error) {
        $transient = $error instanceof PDOException && (
            in_array(substr((string) $error->getCode(), 0, 2), ['08','57'], true)
            || str_contains($error->getMessage(), 'Connection refused')
            || str_contains($error->getMessage(), 'server closed the connection')
            || str_contains($error->getMessage(), 'terminating connection due to administrator command')
            || str_contains($error->getMessage(), 'no connection to the server')
            || str_contains($error->getMessage(), 'could not translate host name')
        );
        if ($error instanceof PDOException) { Database::disconnect(); }
        if (!$transient) { error_log('Worker failure (' . get_class($error) . ').'); }
        if ($once) { exit(1); }
        sleep(5);
    }
} while (!$once);
