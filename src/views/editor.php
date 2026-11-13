<?php

use Camagru\Http\Http;
use Camagru\Media\Images;
?>

<section class="page-intro editor-intro">
    <div>
        <h1>Make a <em>moment.</em></h1>
        <p class="editor-description">Pick a mode. Find your light. Be a little playful.</p>
    </div>
</section>
<div class="editor-layout" id="editor">
    <section class="studio" aria-label="Creative studio">
        <div class="studio-modes" role="tablist" aria-label="Creation mode">
            <button type="button" role="tab" id="photo-mode-tab" aria-selected="true"
                aria-controls="photo-actions" data-studio-mode="photo">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <rect x="3" y="6" width="13" height="12" rx="3" />
                    <path d="m16 10 5-3v10l-5-3" />
                </svg>Photo
            </button>
            <button type="button" role="tab" id="gif-mode-tab" aria-selected="false"
                aria-controls="gif-controls" tabindex="-1" data-studio-mode="gif">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <rect x="3" y="3" width="18" height="18" rx="2" />
                    <path d="M8 3v18M16 3v18M3 8h5m-5 8h5M16 8h5m-5 8h5" />
                </svg>GIF
            </button>
        </div>
        <div class="studio-stage">
            <div class="viewfinder" id="viewfinder">
                <video id="camera" autoplay muted playsinline hidden></video>
                <img id="upload-preview" alt="Your uploaded source" hidden>
                <img id="gif-frame-preview" alt="Selected GIF frame" hidden>
                <img id="overlay-preview" alt="" hidden>
                <div class="stage-chips">
                    <span id="source-badge">
                        <span class="status-dot" aria-hidden="true"></span>Your creative space
                    </span>
                    <button id="switch-source" type="button" hidden></button>
                    <span id="frame-chip">Frame: None</span>
                </div>
                <div id="camera-placeholder">
                    <span class="camera-off-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <rect x="3" y="6" width="18" height="14" rx="3" />
                            <path d="M8 6l2-3h4l2 3" />
                            <circle cx="12" cy="13" r="4" />
                        </svg>
                    </span>
                    <h2>Your camera is off.</h2>
                    <p>It stays that way until you turn it on.</p>
                    <div class="camera-off-actions">
                        <button type="button" id="start-camera" class="button">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <rect x="3" y="6" width="13" height="12" rx="3" />
                                <path d="m16 10 5-3v10l-5-3" />
                            </svg>Turn on camera
                        </button>
                        <label class="button button-outline stage-upload">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M12 16V4m-5 5 5-5 5 5M4 16v4h16v-4" />
                            </svg>Upload a photo
                            <input id="image-input" type="file" name="image" form="capture-form"
                                accept="image/jpeg,image/png">
                        </label>
                    </div>
                    <p class="camera-file-hint">JPEG or PNG · up to 5 MiB</p>
                </div>
            </div>
        </div>
        <div id="gif-controls" role="tabpanel" aria-labelledby="gif-mode-tab" hidden>
            <div class="gif-source-actions">
                <button class="button" id="gif-burst" type="button" disabled>
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="12" cy="12" r="9" />
                        <circle cx="12" cy="12" r="5" />
                    </svg>Record burst
                </button>
                <label class="button button-outline stage-upload">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M12 16V4m-5 5 5-5 5 5M4 16v4h16v-4" />
                    </svg>Upload frames
                    <input type="file" id="frames-input" name="frames[]" form="gif-form"
                        accept="image/jpeg,image/png" multiple>
                </label>
            </div>
            <p class="gif-file-hint">2–8 frames · JPEG or PNG · 5 MiB each · 20 MiB total</p>
            <div class="gif-sequence" id="gif-sequence" hidden>
                <div class="gif-sequence-heading">
                    <h3 id="gif-sequence-title">Your frames</h3>
                    <div>
                        <button class="sequence-control" id="gif-preview-play" type="button"
                            aria-label="Play frame preview" aria-pressed="false">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="m8 5 11 7-11 7Z" />
                            </svg>
                        </button>
                        <button class="sequence-control danger" id="gif-clear" type="button"
                            aria-label="Clear frames">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3" />
                            </svg>
                        </button>
                    </div>
                </div>
                <div id="gif-frame-strip" class="gif-frame-strip"></div>
            </div>
        </div>
        <p id="camera-status" class="camera-status" role="status">Your camera stays off until you
            turn it on.</p>
        <form method="post" action="/photos" enctype="multipart/form-data" id="capture-form">
            <input type="hidden" name="csrf" value="<?= Http::escape($_SESSION['csrf']) ?>">
            <input type="hidden" name="submission" value="<?= bin2hex(random_bytes(16)) ?>">
            <fieldset class="overlay-picker">
                <legend>Choose a frame</legend>
                <div class="overlay-options">
                    <?php foreach (['none' => 'None'] + Images::OVERLAYS as $key => $name): ?>
                    <label class="overlay-option">
                        <input type="radio" name="overlay" value="<?= $key ?>" required
                            <?= $key === 'none' ? 'checked' : '' ?>>
                        <span class="overlay-swatch">
                            <?php if ($key === 'none'): ?>
                            <span class="none-frame-icon">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <rect x="4" y="4" width="16" height="16" rx="3" />
                                    <path d="m3 21 18-18" />
                                </svg>
                            </span>
                            <?php else: ?>
                            <img src="/assets/images/overlays/<?= $key ?>.png" alt="">
                            <?php endif; ?>
                        </span>
                        <span class="overlay-check" aria-hidden="true">✓</span>
                        <span><?= $name ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <div class="studio-submit" id="photo-actions" role="tabpanel"
                aria-labelledby="photo-mode-tab">
                <button class="button" id="capture-button" type="submit" disabled>
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="m12 3 2.5 6.5L21 12l-6.5 2.5L12 21l-2.5-6.5L3 12l6.5-2.5Z" />
                    </svg>Take photo <span aria-hidden="true">↗</span>
                </button>
            </div>
            <p class="form-status" role="status"></p>
        </form>
        <form action="/photos/gif" method="post" enctype="multipart/form-data" id="gif-form" hidden>
            <input type="hidden" name="csrf" value="<?= Http::escape($_SESSION['csrf']) ?>">
            <input type="hidden" name="submission" value="<?= bin2hex(random_bytes(16)) ?>">
            <input type="hidden" name="overlay" id="gif-overlay">
            <div class="studio-submit">
                <button class="button" id="gif-upload" type="submit" disabled>
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="m12 3 2.5 6.5L21 12l-6.5 2.5L12 21l-2.5-6.5L3 12l6.5-2.5Z" />
                    </svg>Create GIF <span aria-hidden="true">↗</span>
                </button>
                <p id="gif-create-hint">Record a burst or upload 2–8 photos to enable Create GIF.
                </p>
            </div>
            <p class="form-status" role="status"></p>
        </form>
        <noscript>
            <p class="flash">JavaScript is needed to select an overlay, preview your source, and
                capture a photo.</p>
        </noscript>
    </section>
    <aside class="history">
        <div class="section-heading">
            <h2>Your roll.</h2>
        </div>
        <p class="muted">Little moments, made by you.</p>
        <div id="history-grid" class="history-grid">
            <?php foreach ($history['photos'] as $photo): ?>
            <a class="history-item" href="/photos/<?= (int) $photo['id'] ?>">
                <img src="/media/<?= (int) $photo['id'] ?>/thumb"
                    alt="Your <?= $photo['kind'] === 'gif' ? 'animation' : 'photo' ?>" width="320"
                    height="240">
            </a>
            <?php endforeach; ?>
        </div>
        <?php if (!$history['photos']): ?>
        <p class="history-empty">Your story starts with the first frame.</p>
        <?php endif; ?>
        <?php if ($history['more']): ?>
        <button type="button" class="button button-outline load-more" data-feed="/editor/photos"
            data-cursor="<?= Http::escape($history['cursor']) ?>" data-target="history-grid">More
            from your roll ↓</button>
        <?php endif; ?>
    </aside>
</div>
