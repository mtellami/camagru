<?php

use Camagru\Http\Http;
use Camagru\Config;
?>

<!doctype html>
<html lang="en">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="<?= Http::escape($_SESSION['csrf'] ?? '') ?>">
        <meta name="description"
            content="A little camera, a little imagination. Make and share your everyday moments with Camagru.">
        <title><?= Http::escape($title ?? 'Camagru') ?> · Camagru</title>
        <?php if (isset($photo)): ?>
        <meta property="og:title"
            content="<?= Http::escape('A moment by ' . $photo['username']) ?>">
        <meta property="og:type" content="website">
        <meta property="og:url"
            content="<?= Http::escape(Config::url('/photos/' . $photo['id'])) ?>">
        <meta property="og:image"
            content="<?= Http::escape(Config::url('/media/' . $photo['id'] . '/poster')) ?>">
        <link rel="canonical" href="<?= Http::escape(Config::url('/photos/' . $photo['id'])) ?>">
        <?php endif; ?>
        <link rel="icon" href="/assets/images/favicon.svg" type="image/svg+xml">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link
            href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Inter:wght@400;500;600&display=swap"
            rel="stylesheet">
        <link rel="stylesheet" href="/assets/css/style.css?v=5">
        <link rel="stylesheet" href="/assets/css/theme.css?v=28">
        <script type="module" src="/assets/js/app.js?v=23"></script>
    </head>

    <body class="<?= $user ? 'authenticated' : 'guest' ?><?= isset($mode) ? ' auth-page' : '' ?>">
        <a class="skip-link" href="#main">Skip to content</a>
        <header class="site-header">
            <a class="brand" href="/" aria-label="Camagru home">
                <span class="brand-mark" aria-hidden="true">c.</span>camagru<span class="brand-dot">
                    ®</span>
            </a>
            <nav class="site-nav" aria-label="Main navigation">
                <a href="/"
                    <?= parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === '/' ? 'aria-current="page"' : '' ?>>
                    Home</a>
                <a href="/gallery"
                    <?= parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === '/gallery' ? 'aria-current="page"' : '' ?>>
                    Gallery</a>
                <?php if ($user): ?>
                <a href="/settings"
                    <?= parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === '/settings' ? 'aria-current="page"' : '' ?>>
                    Settings</a>
                <?php endif; ?>
            </nav>
            <div class="header-actions">
                <?php if ($user): ?>
                <form method="post" action="/logout" class="inline-form">
                    <input type="hidden" name="csrf" value="<?= Http::escape($_SESSION['csrf']) ?>">
                    <button class="button button-small" type="submit">Sign out</button>
                </form>
                <?php else: ?>
                <a class="button button-small" href="/login">Sign in</a>
                <?php endif; ?>
            </div>
        </header>
        <main id="main"> <?= $content ?> </main>
        <footer class="site-footer">
            <a class="footer-brand" href="/">camagru.</a>
            <span>Ordinary moments. A little more playful.</span>
            <span>Made for the joy of it</span>
        </footer>
        <div class="toast" id="toast" role="status" aria-live="polite"
            <?= $flash ? '' : 'hidden' ?>><?= Http::escape($flash ?? '') ?></div>
    </body>

</html>
