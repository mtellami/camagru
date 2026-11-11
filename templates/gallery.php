<?php use Camagru\Http; ?>
<section class="hero">
    <div class="eyebrow"><span class="status-dot"></span> A SMALL SPACE FOR BIG IMAGINATION</div>
    <div class="hero-row"><div><h1>Life looks better<br>with a little <em>play.</em></h1><p>Catch a moment. Add your own twist.<br>A collection of everyday things, seen a little differently.</p></div>
    <div class="hero-aside"><span class="hero-doodle" aria-hidden="true">✳</span><p>No perfect shots needed.<br>Just you, being you.</p><a class="button" href="<?= $user ? '/editor' : '/register' ?>">Make a moment <span aria-hidden="true">↗</span></a></div></div>
</section>
<section class="gallery-section" aria-labelledby="gallery-title">
    <div class="section-heading"><h2 id="gallery-title">The community roll <span aria-hidden="true">↙</span></h2><span class="muted">Fresh moments, newest first</span></div>
    <div class="photo-grid" id="gallery-grid">
        <?php foreach ($photos as $photo): require __DIR__ . '/partials/card.php'; endforeach; ?>
    </div>
    <?php if (!$photos): ?><div class="empty-state"><span aria-hidden="true">◉</span><h3>The first frame is yours.</h3><p>There’s a whole roll of possibilities waiting.</p><a class="button" href="<?= $user ? '/editor' : '/register' ?>">Create the first moment ↗</a></div><?php endif; ?>
    <nav class="pagination" aria-label="Gallery pages">
        <?php if ($page > 1): ?><a href="/gallery?page=<?= $page-1 ?>">← Previous</a><?php endif; ?>
        <span>Page <?= $page ?></span>
        <?php if ($more): ?><a href="/gallery?page=<?= $page+1 ?>">Next page →</a><?php endif; ?>
    </nav>
    <?php if ($more): ?><button class="button button-outline load-more" type="button" data-feed="/gallery" data-cursor="<?= Http::escape($cursor) ?>" data-target="gallery-grid">Load more moments ↓</button><?php endif; ?>
</section>
