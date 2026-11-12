<section class="hero home-hero">
    <div class="home-copy">
        <div class="eyebrow"><span class="status-dot"></span> A SMALL SPACE FOR BIG IMAGINATION</div>
        <h1>Life looks better<br><span>with a little <em>play.</em></span></h1>
        <p>Catch a moment. Add your own twist. A collection of everyday things, seen a little differently.</p>
        <div class="home-actions">
            <a class="button button-pill" href="<?= $user ? '/editor' : '/register' ?>"><?= $user ? 'Make a moment' : 'Join the fun' ?> <span aria-hidden="true">↗</span></a>
            <a class="button button-pill button-outline" href="/gallery">Browse the gallery</a>
        </div>
    </div>
    <div class="home-camera" aria-label="A little preview of your creative space">
        <div class="home-viewfinder">
            <span class="viewfinder-corner corner-tl" aria-hidden="true"></span><span class="viewfinder-corner corner-tr" aria-hidden="true"></span>
            <span class="viewfinder-corner corner-bl" aria-hidden="true"></span><span class="viewfinder-corner corner-br" aria-hidden="true"></span>
            <div class="viewfinder-message"><span class="hero-doodle" aria-hidden="true">✳</span><p><?php if ($user): ?>No perfect shots needed.<br>Just you, being you.<?php else: ?>Your creative corner is waiting.<br>Come make it yours.<?php endif; ?></p></div>
        </div>
        <div class="camera-controls" aria-hidden="true"><span class="camera-stickers"><i>✳</i><i>♡</i><i>◌</i></span><span class="camera-shutter"></span><span class="camera-control-spacer"></span></div>
    </div>
</section>
