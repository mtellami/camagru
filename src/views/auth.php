<?php

use Camagru\Http\Http;

$headings = [
    'login' => 'Good to see you.',
    'register' => 'A little more you.',
    'forgot' => 'A fresh start.',
    'resend' => 'One more little step.',
    'verify' => 'Make it official.',
    'reset' => 'Your fresh start.',
];
$descriptions = [
    'login' => 'Your next favorite moment is waiting.',
    'register' => 'Make something ordinary a little extraordinary.',
    'forgot' => 'We’ll send you a link to reset your password.',
    'resend' => 'We’ll send a new confirmation link to your inbox.',
    'verify' => 'Confirm your email to finish this step.',
    'reset' => 'Choose a new password for your account.',
];
?>
<section class="account-shell">
    <div class="account-art" aria-hidden="true">
        <small>The everyday, remixed.</small>
        <svg class="cam" viewBox="0 0 340 280" aria-hidden="true">
            <g transform="rotate(-9 170 140)">
                <rect x="40" y="82" width="270" height="170" rx="26" fill="#D3D7C6" />
                <rect x="26" y="68" width="270" height="170" rx="26" fill="#B85F48" />
                <path d="M52 68v-14a10 10 0 0110-10h62a10 10 0 0110 10v14z" fill="#B85F48" />
                <rect x="236" y="86" width="34" height="18" rx="6" fill="#EBD9B8" />
                <circle cx="160" cy="154" r="62" fill="#EBD9B8" />
                <circle cx="160" cy="154" r="50" fill="#2F3A33" />
                <g stroke="#E3C795" stroke-width="3.5" stroke-linecap="round">
                    <path d="M160 126v56M136 140l48 28M136 168l48-28" />
                </g>
            </g>
        </svg>
        <q>There’s a little
            <br>magic in <em>every day.</em>
        </q>
        <svg class="star" width="64" height="64" viewBox="0 0 64 64" aria-hidden="true">
            <path d="M32 2l5.2 21.8L58 32l-20.8 8.2L32 62l-5.2-21.8L6 32l20.8-8.2z"
                fill="#B9C4AA" />
        </svg>
    </div>
    <div class="account-panel">
        <?php if (in_array($mode, ['login', 'register'], true)): ?>
        <nav class="auth-tabs" aria-label="Account">
            <a href="/login" <?= $mode === 'login' ? 'aria-current="page"' : '' ?>>Sign in</a>
            <a href="/register" <?= $mode === 'register' ? 'aria-current="page"' : '' ?>>Create
                account</a>
        </nav>
        <?php endif; ?>
        <h1>
            <?php if (in_array($mode, ['login', 'register'], true)): ?> <?= $mode === 'login' ? 'Good to see' : 'A little more' ?>
            <em>you.</em>
            <?php else: ?> <?= Http::escape($headings[$mode]) ?> <?php endif; ?>
        </h1>
        <p class="muted"><?= Http::escape($descriptions[$mode]) ?></p>
        <form method="post" action="/<?= Http::escape($mode) ?>" class="stack-form auth-form">
            <?php if ($mode === 'login' && !empty($loginError)): ?>
            <div class="login-error" id="login-error" role="alert">
                <p><?= Http::escape($loginError['message']) ?></p>
                <a href="/forgot">Reset your password</a> or <a href="/resend">resend your
                    confirmation email</a>.
            </div>
            <?php endif; ?>
            <input type="hidden" name="csrf" value="<?= Http::escape($_SESSION['csrf']) ?>">
            <?php if ($mode === 'reset'): ?>
            <input type="text" hidden name="username" autocomplete="username">
            <?php endif; ?>
            <?php if (in_array($mode, ['register','login'], true)): ?>
            <label>Username
                <input name="username" required minlength="3" maxlength="30" autocomplete="username"
                    pattern="[A-Za-z0-9_]{3,30}" placeholder="Your creative alias"
                    value="<?= Http::escape($mode === 'login' ? ($loginError['username'] ?? '') : '') ?>"
                    <?= !empty($loginError) ? 'aria-describedby="login-error"' : '' ?>>
            </label>
            <?php endif; ?>
            <?php if (in_array($mode, ['register','forgot','resend'], true)): ?>
            <label>Email address
                <input type="email" name="email" required maxlength="254" autocomplete="email"
                    placeholder="you@example.com">
            </label>
            <?php endif; ?>
            <?php if (in_array($mode, ['register','login','reset'], true)): ?>
            <div class="auth-field">
                <div class="auth-field-heading">
                    <label for="auth-password">Password</label>
                    <?php if ($mode === 'login'): ?>
                    <a href="/forgot">Forgot password?</a>
                    <?php endif; ?>
                </div>
                <span class="password-input">
                    <input id="auth-password" type="password" name="password" required
                        <?= $mode !== 'login' ? 'minlength="15" maxlength="128"' : '' ?>
                        autocomplete="<?= $mode === 'login' ? 'current-password' : 'new-password' ?>"
                        <?= $mode !== 'login' ? 'aria-describedby="auth-password-rules"' : '' ?>>
                    <button type="button" class="password-visibility" aria-label="Show password">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z" />
                            <circle cx="12" cy="12" r="3" />
                            <path class="eye-slash" d="M3 3l18 18" />
                        </svg>
                    </button>
                </span>
            </div>
            <?php endif; ?>
            <?php if (in_array($mode, ['register','reset'], true)): ?>
            <ul class="auth-password-rules" id="auth-password-rules"
                aria-label="Password requirements">
                <li data-rule="length">
                    <span aria-hidden="true"></span>15–128 characters
                </li>
                <li data-rule="letter">
                    <span aria-hidden="true"></span>A letter
                </li>
                <li data-rule="number">
                    <span aria-hidden="true"></span>A number
                </li>
            </ul>
            <label>Confirm password<span class="password-input">
                    <input type="password" name="confirmation" required minlength="15"
                        maxlength="128" autocomplete="new-password">
                    <button type="button" class="password-visibility" aria-label="Show password">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z" />
                            <circle cx="12" cy="12" r="3" />
                            <path class="eye-slash" d="M3 3l18 18" />
                        </svg>
                    </button>
                </span>
            </label>
            <?php endif; ?>
            <?php if (in_array($mode, ['verify','reset'], true)): ?>
            <input type="hidden" name="token" value="<?= Http::escape($token) ?>">
            <?php endif; ?>
            <?php if ($mode === 'verify'): ?>
            <input type="hidden" name="purpose" value="<?= Http::escape($purpose) ?>">
            <?php endif; ?>
            <button class="button" type="submit"><?= Http::escape(match ($mode) {
        'register' => 'Create your account', 'login' => 'Sign in', 'forgot','resend' => 'Send me a link', 'verify' => 'Confirm email', 'reset' => 'Save new password'
    }) ?> <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor"
                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                    aria-hidden="true">
                    <path d="M4 12L12 4M5 4h7v7" />
                </svg>
            </button>
        </form>
        <?php if ($mode === 'login'): ?>
        <p class="auth-alternative">Did not get your confirmation email? <a href="/resend">Resend it
            </a>
        </p>
        <?php elseif ($mode === 'register'): ?>
        <p class="auth-alternative">Already have an account? <a href="/login">Sign in</a>
        </p>
        <?php else: ?>
        <p class="auth-alternative">
            <a href="/login">Back to sign in</a>
        </p>
        <?php endif; ?>
    </div>
</section>
