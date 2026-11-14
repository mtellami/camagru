<?php

declare(strict_types=1);

namespace Camagru;

use Camagru\Database\Database;
use Camagru\Mail\Outbox;
use Camagru\Media\PhotoPublisher;

final class Maintenance
{
    /** Process a bounded mail batch and serialize cleanup during request shutdown. */
    public static function run(): void
    {
        try {
            // Keep request work bounded; failed deliveries retry on later visits.
            for ($sent = 0; $sent < 5 && Outbox::once(); $sent++) {
            }
            Database::transaction(static function (): void {
                // Two simultaneous page requests must not delete the same files.
                if (!Database::query('SELECT pg_try_advisory_xact_lock(4298043)')->fetchColumn()) {
                    return;
                }
                PhotoPublisher::cleanup();
                Database::query('DELETE FROM rate_limits WHERE expires_at<NOW()');
                Database::query(
                    "DELETE FROM account_tokens WHERE expires_at<NOW()-INTERVAL '1 day'",
                );
            });
        } catch (\Throwable $error) {
            error_log('Request maintenance failed (' . get_class($error) . ').');
        }
    }
}
