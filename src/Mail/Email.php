<?php

declare(strict_types=1);

namespace Camagru\Mail;

use Camagru\Config;

final class Email
{
    public static function encode(string $body, ?string $html = null): array
    {
        $headers = ['MIME-Version' => '1.0'];
        if ($html === null) {
            $headers['Content-Type'] = 'text/plain; charset=UTF-8';
            $headers['Content-Transfer-Encoding'] = 'base64';
            return ['headers' => $headers, 'body' => chunk_split(base64_encode($body))];
        }
        $boundary = 'camagru_' . bin2hex(random_bytes(18));
        $headers['Content-Type'] = 'multipart/alternative; boundary="' . $boundary . '"';
        $parts = '';
        foreach (['text/plain' => $body, 'text/html' => $html] as $type => $content) {
            $parts .=
                '--' .
                $boundary .
                "\r\nContent-Type: " .
                $type .
                "; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" .
                chunk_split(base64_encode($content));
        }
        return ['headers' => $headers, 'body' => $parts . '--' . $boundary . "--\r\n"];
    }

    public static function account(string $purpose, string $url): array
    {
        [$eyebrow, $heading, $description, $action, $note] = match ($purpose) {
            'reset' => [
                'A FRESH START',
                'Back to making moments.',
                'Forgot your password? It happens. Choose a new one and get back to your creative corner.',
                'Reset password',
                'This link expires in 30 minutes and can only be used once. If you did not request a password reset, you can ignore this email. Your password will stay the same.',
            ],
            'email' => [
                'YOUR ACCOUNT, UPDATED',
                'A new inbox. Still you.',
                'Confirm this email address to finish updating your Camagru account.',
                'Confirm new email',
                'This link expires in 24 hours and can only be used once. If you did not request this change, you can ignore this email. Your current email address will stay the same.',
            ],
            default => [
                'YOUR LITTLE CREATIVE CORNER',
                'A little more you.',
                'Welcome to Camagru. One small step before you capture, remix, and share your everyday moments: confirm your email address.',
                'Confirm email',
                'This link expires in 24 hours and can only be used once. If you did not create a Camagru account, you can ignore this email.',
            ],
        };
        return self::build($eyebrow, $heading, $description, $action, $url, $note);
    }

    public static function comment(string $username, string $comment, string $url): array
    {
        return self::build(
            'A LITTLE CONVERSATION',
            'Your moment made an impression.',
            $username . ' left a note on your creation.',
            'View comment',
            $url,
            'You are receiving this because comment notifications are enabled. You can change this in your account settings.',
            $comment,
        );
    }

    private static function build(
        string $eyebrow,
        string $heading,
        string $description,
        string $action,
        string $url,
        string $note,
        ?string $quote = null,
    ): array {
        $body = $heading . "\n\n" . $description . "\n\n";
        if ($quote !== null) {
            $body .= $quote . "\n\n";
        }
        $body .=
            $action .
            ":\n" .
            $url .
            "\n\n" .
            $note .
            "\n\nCamagru — Ordinary moments. A little more playful.";
        ob_start();
        require Config::root() . '/src/views/emails/message.php';
        $html = ob_get_clean();
        return compact('body', 'html');
    }
}
