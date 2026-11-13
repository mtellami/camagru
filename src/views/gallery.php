<?php

use Camagru\Http\Http;
?>

<section class="gallery-section gallery-page" aria-labelledby="gallery-title">
    <div class="section-heading gallery-heading">
        <h1 id="gallery-title">The community <em>roll</em></h1>
        <div class="gallery-heading-actions">
            <span class="fresh-label">
                <span class="status-dot" aria-hidden="true"></span>Fresh moments, newest first
            </span>
            <a class="button button-pill gallery-create"
                href="<?= $user ? '/editor' : '/register' ?>">Make a moment <span
                    aria-hidden="true">↗</span>
            </a>
        </div>
    </div>
    <div class="photo-grid" id="gallery-grid">
        <?php foreach ($photos as $photo):
    require __DIR__ . '/partials/card.php';
endforeach;
?>
    </div>
    <?php if (!$photos): ?>
    <div class="empty-state">
        <span aria-hidden="true">◉</span>
        <h3>The first frame is yours.</h3>
        <p>There’s a whole roll of possibilities waiting.</p>
        <a class="button" href="<?= $user ? '/editor' : '/register' ?>">Create the first moment ↗
        </a>
    </div>
    <?php endif; ?>
    <nav class="pagination" aria-label="Gallery pages">
        <?php if ($page > 1): ?>
        <a href="/gallery?page=<?= $page - 1 ?>">← Previous</a>
        <?php endif; ?>
        <span>Page <?= $page ?></span>
        <?php if ($more): ?>
        <a href="/gallery?page=<?= $page + 1 ?>">Next page →</a>
        <?php endif; ?>
    </nav>
    <?php if ($more): ?>
    <button class="button button-outline load-more" type="button" data-feed="/gallery"
        data-cursor="<?= Http::escape($cursor) ?>" data-target="gallery-grid">Load more moments ↓
    </button>
    <?php endif; ?>
</section>
