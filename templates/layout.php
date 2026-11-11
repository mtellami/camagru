<?php use Camagru\Http; use Camagru\Config; ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= Http::escape($_SESSION['csrf'] ?? '') ?>">
    <meta name="description" content="A little camera, a little imagination. Make and share your everyday moments with Camagru.">
    <title><?= Http::escape($title ?? 'Camagru') ?> · Camagru</title>
    <?php if (isset($photo)): ?>
    <meta property="og:title" content="<?= Http::escape('A moment by ' . $photo['username']) ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= Http::escape(Config::url('/photos/' . $photo['id'])) ?>">
    <meta property="og:image" content="<?= Http::escape(Config::url('/media/' . $photo['id'] . '/poster')) ?>">
    <link rel="canonical" href="<?= Http::escape(Config::url('/photos/' . $photo['id'])) ?>">
    <?php endif; ?>
    <link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/style.css">
    <script src="/assets/app.js" defer></script>
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header">
    <a class="brand" href="/" aria-label="Camagru home"><span class="brand-mark" aria-hidden="true">c.</span>camagru<span class="brand-dot">®</span></a>
    <nav aria-label="Main navigation">
        <a href="/gallery" <?= in_array(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), ['/','/gallery'], true) ? 'aria-current="page"' : '' ?>>Gallery</a>
        <?php if ($user): ?>
            <a href="/settings">Settings</a>
            <form method="post" action="/logout" class="inline-form"><input type="hidden" name="csrf" value="<?= Http::escape($_SESSION['csrf']) ?>"><button class="text-button" type="submit">Sign out</button></form>
            <a class="button button-small" href="/editor">Create <span aria-hidden="true">↗</span></a>
        <?php else: ?>
            <a href="/login">Sign in</a><a class="button button-small" href="/register">Join the fun <span aria-hidden="true">↗</span></a>
        <?php endif; ?>
    </nav>
</header>
<main id="main">
    <?php if ($flash): ?><div class="flash" role="status"><?= Http::escape($flash) ?></div><?php endif; ?>
    <?= $content ?>
</main>
<footer class="site-footer"><a class="footer-brand" href="/">camagru.</a><span>Ordinary moments. A little more playful.</span><span>Made for the joy of it <span aria-hidden="true">✳</span></span></footer>
<div class="toast" id="toast" role="status" aria-live="polite" hidden></div>
</body>
</html>
