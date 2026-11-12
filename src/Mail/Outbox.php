<?php
declare(strict_types=1);
namespace Camagru\Mail;

use Camagru\Config;
use Camagru\Database\Database;

final class Outbox
{
    private static function key(): string
    {
        $hex = Config::get('APP_KEY');
        if (!preg_match('/\A[a-f0-9]{64}\z/', $hex)) { throw new \RuntimeException('APP_KEY must contain 32 hex-encoded random bytes.'); }
        return hex2bin($hex);
    }

    public static function encrypt(array $data): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return base64_encode($nonce . sodium_crypto_secretbox(json_encode($data, JSON_THROW_ON_ERROR), $nonce, self::key()));
    }

    public static function decrypt(string $payload): array
    {
        $binary = base64_decode($payload, true);
        if ($binary === false || strlen($binary) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) { throw new \RuntimeException('Invalid outbox payload.'); }
        $plain = sodium_crypto_secretbox_open(substr($binary, 24), substr($binary, 0, 24), self::key());
        if ($plain === false) { throw new \RuntimeException('Cannot decrypt outbox payload.'); }
        return json_decode($plain, true, 512, JSON_THROW_ON_ERROR);
    }

    public static function queue(string $event, int $userId, string $to, string $subject, string $body, ?int $commentId = null, ?int $expires = null): void
    {
        Database::query('INSERT INTO email_outbox(event_key,user_id,comment_id,payload) VALUES (?,?,?,?) ON CONFLICT(event_key) DO NOTHING', [$event, $userId, $commentId, self::encrypt(compact('to', 'subject', 'body', 'expires'))]);
    }

    public static function once(): bool
    {
        $job = Database::transaction(static function () {
            $row = Database::query('SELECT * FROM email_outbox WHERE sent_at IS NULL AND failed_at IS NULL AND next_attempt_at<=NOW() AND (lease_until IS NULL OR lease_until<NOW()) ORDER BY id FOR UPDATE SKIP LOCKED LIMIT 1')->fetch();
            if ($row) { Database::query("UPDATE email_outbox SET lease_until=NOW()+INTERVAL '2 minutes' WHERE id=?", [$row['id']]); }
            return $row;
        });
        if (!$job) { return false; }
        try {
            $data = self::decrypt($job['payload']);
            $send = !$data['expires'] || $data['expires'] > time();
            if ($job['comment_id']) {
                $send = $send && (bool) Database::query("SELECT 1 FROM users u JOIN photos p ON p.user_id=u.id JOIN comments c ON c.photo_id=p.id WHERE c.id=? AND u.notify_comments=TRUE AND p.state='ready'", [$job['comment_id']])->fetchColumn();
            }
            if ($send) {
                $from = Config::get('MAIL_FROM', 'Camagru Accounts <no-reply@camagru.app>');
                if (preg_match('/[\r\n]/', $from . $data['to'] . $data['subject'])) { throw new \RuntimeException('Invalid email headers.'); }
                $headers = ['From' => $from, 'MIME-Version' => '1.0', 'Content-Type' => 'text/plain; charset=UTF-8', 'Content-Transfer-Encoding' => 'base64'];
                if (!mail($data['to'], '=?UTF-8?B?' . base64_encode($data['subject']) . '?=', chunk_split(base64_encode($data['body'])), $headers)) { throw new \RuntimeException('SMTP delivery failed.'); }
            }
            Database::query("UPDATE email_outbox SET sent_at=NOW(),payload='',lease_until=NULL WHERE id=?", [$job['id']]);
        } catch (\Throwable $error) {
            $attempts = (int) $job['attempts'] + 1;
            Database::query("UPDATE email_outbox SET attempts=?,lease_until=NULL,next_attempt_at=NOW()+CAST(? AS INTEGER)*INTERVAL '1 second', failed_at=CASE WHEN CAST(? AS INTEGER)>=5 THEN NOW() ELSE NULL END WHERE id=?", [$attempts, min(3600, 30 * 2 ** ($attempts - 1)), $attempts, $job['id']]);
            error_log('Email delivery attempt failed for job ' . $job['id'] . ' (' . get_class($error) . ').');
        }
        return true;
    }
}
