<section class="hero home-hero">
    <div class="eyebrow"><span class="status-dot"></span> A SMALL SPACE FOR BIG IMAGINATION</div>
    <div class="hero-row"><div><h1>Life looks better<br>with a little <em>play.</em></h1><p>Catch a moment. Add your own twist.<br>A collection of everyday things, seen a little differently.</p></div>
    <div class="hero-aside"><span class="hero-doodle" aria-hidden="true">✳</span><p><?php if ($user): ?>No perfect shots needed.<br>Just you, being you.<?php else: ?>Your creative corner is waiting.<br>Come make it yours.<?php endif; ?></p><a class="button" href="<?= $user ? '/editor' : '/register' ?>"><?= $user ? 'Make a moment' : 'Join the fun' ?> <span aria-hidden="true">↗</span></a></div></div>
</section>
