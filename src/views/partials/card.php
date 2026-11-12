<?php use Camagru\Http\Http; ?>
<article class="photo-card">
    <a class="photo-link" href="/photos/<?= (int) $photo['id'] ?>"><img src="/media/<?= (int) $photo['id'] ?>/thumb" alt="A creation by <?= Http::escape($photo['username']) ?>" width="320" height="240"><?php if ($photo['kind'] === 'gif'): ?><span class="gif-badge">GIF</span><?php endif; ?></a>
    <div class="card-meta"><span class="author"><span class="avatar" aria-hidden="true"><?= Http::escape(strtoupper(substr($photo['username'],0,1))) ?></span><?= Http::escape($photo['username']) ?></span><a href="/photos/<?= (int) $photo['id'] ?>" class="card-counts" aria-label="<?= (int) $photo['likes'] ?> likes, <?= (int) $photo['comments'] ?> comments">♡ <?= (int) $photo['likes'] ?> <span>◌ <?= (int) $photo['comments'] ?></span></a></div>
</article>
