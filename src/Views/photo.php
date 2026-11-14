<?php

use Camagru\Http\Http;
use Camagru\Config;
?>

<section class="photo-detail">
    <a class="back-link" href="/gallery">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M15 6l-6 6 6 6" />
        </svg>Back to the roll
    </a>
    <div class="detail-layout">
        <div class="detail-image">
            <img src="/media/<?= (int) $photo['id'] ?>/image"
                alt="A creation by <?= Http::escape($photo['username']) ?>"
                width="<?= (int) $photo['width'] ?>" height="<?= (int) $photo['height'] ?>">
        </div>
        <div class="detail-panel">
            <div class="moment-author">
                <span class="moment-avatar" aria-hidden="true">
                    <?= Http::escape(strtoupper(substr($photo['username'], 0, 1))) ?></span>
                <div>
                    <h1>By <em><?= Http::escape($photo['username']) ?>.</em></h1>
                    <time class="muted" datetime="<?= Http::escape($photo['created_at']) ?>">
                        <?= Http::escape(date('F j, Y', strtotime($photo['created_at']))) ?></time>
                </div>
            </div>
            <div class="detail-actions">
                <?php if ($user): ?>
                <form method="post"
                    action="/photos/<?= (int) $photo['id'] ?>/<?= $photo['liked'] ? 'unlike' : 'like' ?>"
                    data-ajax="like">
                    <input type="hidden" name="csrf" value="<?= Http::escape($_SESSION['csrf']) ?>">
                    <button type="submit" class="button button-outline like-button"
                        aria-pressed="<?= $photo['liked'] ? 'true' : 'false' ?>">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path
                                d="M12 20s-8-5-8-11a4.5 4.5 0 018-2.8A4.5 4.5 0 0120 9c0 6-8 11-8 11z" />
                        </svg>
                        <span class="like-count"><?= (int) $photo['likes'] ?></span> likes
                    </button>
                </form>
                <?php else: ?>
                <span class="guest-like" tabindex="0" title="Sign in to like">
                    <button type="button" class="button button-outline like-button" disabled
                        aria-label="Sign in to like">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path
                                d="M12 20s-8-5-8-11a4.5 4.5 0 018-2.8A4.5 4.5 0 0120 9c0 6-8 11-8 11z" />
                        </svg>
                        <span class="like-count"><?= (int) $photo['likes'] ?></span> likes
                    </button>
                </span>
                <?php endif; ?>
                <button class="button button-outline share-button" type="button"
                    popovertarget="share-popover" aria-controls="share-popover"
                    aria-expanded="false"
                    data-url="<?= Http::escape(Config::url('/photos/' . $photo['id'])) ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                        stroke-linejoin="round" aria-hidden="true">
                        <path d="M10 14L20 4M14 4h6v6" />
                        <path d="M18 14v5a1 1 0 01-1 1H5a1 1 0 01-1-1V7a1 1 0 011-1h5" />
                    </svg>Share
                </button>
                <?php if ($user && (int) $user['id'] === (int) $photo['user_id']): ?>
                <details class="moment-more">
                    <summary aria-label="More actions">
                        <span aria-hidden="true">•••</span>
                    </summary>
                    <div class="moment-menu">
                        <form method="post" action="/photos/<?= (int) $photo['id'] ?>/delete"
                            data-ajax="delete">
                            <input type="hidden" name="csrf"
                                value="<?= Http::escape($_SESSION['csrf']) ?>">
                            <button type="submit" class="text-button danger">Delete this creation
                            </button>
                        </form>
                    </div>
                </details>
                <?php endif; ?>
            </div>
            <section class="comments-section" aria-labelledby="comments-title">
                <h2 id="comments-title">A little conversation.</h2>
                <div id="comments">
                    <?php foreach ($comments as $comment): ?>
                    <article class="comment">
                        <span class="comment-avatar" aria-hidden="true">
                            <?= Http::escape(strtoupper(substr($comment['username'], 0, 1))) ?>
                        </span>
                        <div><strong><?= Http::escape($comment['username']) ?></strong>
                            <p><?= Http::escape($comment['body']) ?></p>
                        </div>
                        <?php if ($user && (int) $user['id'] === (int) $comment['user_id']): ?>
                        <form method="post"
                            action="/photos/<?= (int) $photo['id'] ?>/comments/<?= (int) $comment['id'] ?>/delete"
                            data-ajax="delete-comment" class="comment-delete-form">
                            <input type="hidden" name="csrf"
                                value="<?= Http::escape($_SESSION['csrf']) ?>">
                            <button type="submit" class="comment-delete"
                                aria-label="Delete your comment" title="Delete your comment">
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none"
                                    stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                    stroke-linejoin="round" aria-hidden="true">
                                    <path
                                        d="M4 7h16M10 11v6M14 11v6M6 7l1 12a1 1 0 001 1h8a1 1 0 001-1l1-12M9 7V4h6v3" />
                                </svg>
                            </button>
                        </form>
                        <?php endif; ?>
                    </article>
                    <?php endforeach; ?>
                </div>
                <p id="no-comments" class="moment-empty" <?= $comments ? 'hidden' : '' ?>>Be the
                    first to say something kind.</p>
                <?php if ($page > 1 || count($comments) === 20): ?>
                <nav class="pagination" aria-label="Comment pages">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>">← Previous</a>
                    <?php endif; ?>
                    <?php if (count($comments) === 20): ?>
                    <a href="?page=<?= $page + 1 ?>">More comments →</a>
                    <?php endif; ?>
                </nav>
                <?php endif; ?>
                <?php if ($user): ?>
                <form method="post" action="/photos/<?= (int) $photo['id'] ?>/comments"
                    class="moment-compose" data-ajax="comment">
                    <input type="hidden" name="csrf" value="<?= Http::escape($_SESSION['csrf']) ?>">
                    <input type="hidden" name="submission" value="<?= bin2hex(random_bytes(16)) ?>">
                    <span class="comment-avatar" aria-hidden="true">
                        <?= Http::escape(strtoupper(substr($user['username'], 0, 1))) ?></span>
                    <div class="moment-compose-box">
                        <label class="visually-hidden" for="comment-body">Leave a note</label>
                        <textarea id="comment-body" name="body" maxlength="1000" rows="3" required
                            placeholder="A little kindness goes a long way…"></textarea>
                        <button class="button" type="submit">Post comment ↗</button>
                        <p class="form-status" role="status"></p>
                    </div>
                </form>
                <?php else: ?>
                <p class="muted">
                    <a href="/login">Sign in</a> to join the conversation.
                </p>
                <?php endif; ?>
            </section>
        </div>
    </div>
</section>
<dialog class="moment-dialog" id="delete-comment-dialog" aria-labelledby="delete-comment-title"
    aria-describedby="delete-comment-description">
    <span class="dialog-symbol dialog-symbol-danger" aria-hidden="true">
        <svg viewBox="0 0 24 24">
            <path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a1 1 0 001 1h8a1 1 0 001-1l1-12M9 7V4h6v3" />
        </svg>
    </span>
    <h2 id="delete-comment-title">Delete this comment?</h2>
    <p id="delete-comment-description">Your note will be removed from the conversation. This cannot
        be undone.</p>
    <div class="dialog-actions">
        <button class="button button-outline" type="button" data-close-dialog autofocus>Keep it
        </button>
        <button class="button button-danger" type="button" id="confirm-delete-comment">Delete
            comment</button>
    </div>
</dialog>
<?php
$momentUrl = Config::url('/photos/' . $photo['id']);
$encodedMomentUrl = rawurlencode($momentUrl);
$embedCode =
    '<a href="' .
    Http::escape($momentUrl) .
    '"><img src="' .
    Http::escape(Config::url('/media/' . $photo['id'] . '/image')) .
    '" alt="' .
    Http::escape('A moment by ' . $photo['username'] . ' on Camagru') .
    '" width="640" style="max-width:100%;height:auto"></a>';
?>
<div class="share-popover" id="share-popover" popover="auto" role="region"
    aria-labelledby="share-title" data-url="<?= Http::escape($momentUrl) ?>"
    data-embed="<?= Http::escape($embedCode) ?>">
    <h2 id="share-title">Share</h2>
    <div class="share-options">
        <button type="button" class="share-option share-embed" id="copy-moment-embed"
            title="Copy HTML embed code">
            <span class="share-option-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="m8 7-5 5 5 5m8-10 5 5-5 5M14 4l-4 16" />
                </svg>
            </span>Embed
        </button>
        <a class="share-option share-facebook"
            href="https://www.facebook.com/sharer/sharer.php?u=<?= $encodedMomentUrl ?>"
            target="_blank" rel="noopener noreferrer">
            <span class="share-option-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M14 21v-8h3l.5-4H14V7c0-1 .5-2 2-2h2V1h-3c-4 0-6 2-6 6v2H6v4h3v8" />
                </svg>
            </span>Facebook
        </a>
        <a class="share-option share-twitter"
            href="https://twitter.com/intent/tweet?url=<?= $encodedMomentUrl ?>&amp;text=<?= rawurlencode('A little moment on Camagru') ?>"
            target="_blank" rel="noopener noreferrer">
            <span class="share-option-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="m4 3 16 18M20 3 4 21" />
                </svg>
            </span>Twitter / X
        </a>
        <a class="share-option share-linkedin"
            href="https://www.linkedin.com/sharing/share-offsite/?url=<?= $encodedMomentUrl ?>"
            target="_blank" rel="noopener noreferrer">
            <span class="share-option-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <rect x="3" y="3" width="18" height="18" rx="2" />
                    <path d="M7 10v7M7 7v.1M11 17v-7m0 3c0-4 6-4 6 0v4" />
                </svg>
            </span>LinkedIn
        </a>
        <a class="share-option share-email"
            href="mailto:?subject=<?= rawurlencode('A little moment on Camagru') ?>&amp;body=<?= $encodedMomentUrl ?>">
            <span class="share-option-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <rect x="3" y="5" width="18" height="14" rx="2" />
                    <path d="m3 6 9 7 9-7" />
                </svg>
            </span>Email
        </a>
    </div>
    <button type="button" class="share-copy-link" id="copy-moment-link" autofocus>
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path
                d="m10 13 4-4m-6 6-1 1a4 4 0 005.7 5.7l3-3A4 4 0 0014 12m2-3 1-1A4 4 0 0011.3 2.3l-3 3A4 4 0 0010 12" />
        </svg>Copy Link
    </button>
    <p class="share-status" role="status" aria-live="polite"></p>
</div>
