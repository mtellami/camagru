<?php use Camagru\Http\Http; use Camagru\Media\Images; ?>
<section class="page-intro editor-intro"><div><h1>Make a <em>moment.</em></h1><p class="editor-description">Pick a frame. Find your light. Be a little playful.</p></div><svg class="editor-spark" viewBox="0 0 48 48" aria-hidden="true"><path d="M24 4v40M6.7 14l34.6 20M6.7 34l34.6-20"/></svg></section>
<div class="editor-layout" id="editor">
<section class="studio" aria-label="Photo studio">
    <div class="viewfinder" id="viewfinder">
        <video id="camera" autoplay muted playsinline hidden></video>
        <img id="upload-preview" alt="Your uploaded source" hidden>
        <img id="overlay-preview" alt="" hidden>
        <div class="stage-chips"><span id="source-badge"><span class="status-dot" aria-hidden="true"></span>Your creative space</span><button id="switch-source" type="button" hidden></button><span id="frame-chip">Frame: None</span></div>
        <div id="camera-placeholder">
            <span class="camera-off-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4"/></svg></span>
            <h2>Your camera is off.</h2><p>It stays that way until you turn it on.</p>
            <div class="camera-off-actions">
                <button type="button" id="start-camera" class="button"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="6" width="13" height="12" rx="3"/><path d="m16 10 5-3v10l-5-3"/></svg>Turn on camera</button>
                <label class="button button-outline stage-upload"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 16V4m-5 5 5-5 5 5M4 16v4h16v-4"/></svg>Upload a photo<input id="image-input" type="file" name="image" form="capture-form" accept="image/jpeg,image/png"></label>
            </div>
            <p class="camera-file-hint">JPEG or PNG · up to 5 MiB</p>
        </div>
        <button class="capture-icon-button" id="capture-button" type="submit" form="capture-form" aria-label="Capture moment" title="Capture moment" disabled><span aria-hidden="true"></span></button>
    </div>
    <p id="camera-status" class="camera-status" role="status">Your camera stays off until you turn it on.</p>
    <form method="post" action="/photos" enctype="multipart/form-data" id="capture-form">
        <input type="hidden" name="csrf" value="<?= Http::escape($_SESSION['csrf']) ?>"><input type="hidden" name="submission" value="<?= bin2hex(random_bytes(16)) ?>">
        <fieldset class="overlay-picker"><legend>01 <span>Choose your little twist</span></legend><div class="overlay-options">
        <?php foreach (['none' => 'None'] + Images::OVERLAYS as $key => $name): ?><label class="overlay-option"><input type="radio" name="overlay" value="<?= $key ?>" required <?= $key === 'none' ? 'checked' : '' ?>><span class="overlay-swatch"><?php if ($key === 'none'): ?><span class="none-frame-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="3"/><path d="m3 21 18-18"/></svg></span><?php else: ?><img src="/assets/images/overlays/<?= $key ?>.png" alt=""><?php endif; ?></span><span class="overlay-check" aria-hidden="true">✓</span><span><?= $name ?></span></label><?php endforeach; ?>
        </div></fieldset>
        <div class="capture-row"><div><p class="capture-step"><span>02</span>Make it a memory</p><p>Your creation will appear in the public gallery.</p></div></div><p class="form-status" role="status"></p>
    </form>
    <details class="gif-studio"><summary><span>Make it move</span><span class="pill">GIF</span><svg class="gif-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg></summary><p class="muted">Capture 8 frames over 2 seconds, or upload 2–8 photos in playback order. Choose a frame or keep None for a clean animation.</p><form action="/photos/gif" method="post" enctype="multipart/form-data" id="gif-form"><input type="hidden" name="csrf" value="<?= Http::escape($_SESSION['csrf']) ?>"><input type="hidden" name="submission" value="<?= bin2hex(random_bytes(16)) ?>"><input type="hidden" name="overlay" id="gif-overlay"><label class="upload-label">Upload frames<input type="file" id="frames-input" name="frames[]" accept="image/jpeg,image/png" multiple></label><p class="field-help">2–8 frames · 5 MiB each · 20 MiB total</p><div class="gif-actions"><button class="button button-outline" id="gif-burst" type="button" disabled>Record camera burst</button><button class="button" id="gif-upload" type="submit" disabled>Create animation ↗</button></div><p class="form-status" role="status"></p></form></details>
    <noscript><p class="flash">JavaScript is needed to select an overlay, preview your source, and capture a photo.</p></noscript>
</section>
<aside class="history"><div class="section-heading"><h2>Your roll.</h2></div><p class="muted">Little moments, made by you.</p><div id="history-grid" class="history-grid"><?php foreach ($history['photos'] as $photo): ?><a class="history-item" href="/photos/<?= (int) $photo['id'] ?>"><img src="/media/<?= (int) $photo['id'] ?>/thumb" alt="Your <?= $photo['kind'] === 'gif' ? 'animation' : 'photo' ?>" width="320" height="240"></a><?php endforeach; ?></div><?php if (!$history['photos']): ?><p class="history-empty">Your story starts with the first frame.</p><?php endif; ?><?php if ($history['more']): ?><button type="button" class="button button-outline load-more" data-feed="/editor/photos" data-cursor="<?= Http::escape($history['cursor']) ?>" data-target="history-grid">More from your roll ↓</button><?php endif; ?></aside>
</div>
