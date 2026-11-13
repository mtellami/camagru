<?php

use Camagru\Http\Http;
?>

<section class="page-intro settings-intro">
    <span class="eyebrow">
        <span class="status-dot" aria-hidden="true"></span> MAKE YOURSELF AT HOME
    </span>
    <h1>Your little <em>corner.</em></h1>
    <p class="settings-intro-description">The details that make it yours.</p>
</section>
<div class="settings-layout">
    <aside class="settings-sidebar">
        <div class="settings-sidebar-profile">
            <span class="settings-avatar" aria-hidden="true">
                <?= Http::escape(strtoupper(substr($user['username'], 0, 1))) ?></span>
            <div class="settings-profile-copy">
                <strong><?= Http::escape($user['username']) ?></strong>
                <span><?= Http::escape($user['email']) ?></span>
            </div>
        </div>
        <nav aria-label="Settings sections">
            <a href="#profile" aria-current="location">Profile</a>
            <a href="#notifications">Notifications</a>
            <a href="#email">Email</a>
            <a href="#password">Password</a>
        </nav>
    </aside>
    <div class="settings-grid">
        <section class="settings-card" id="profile">
            <header class="settings-card-heading">
                <span class="settings-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <circle cx="12" cy="8" r="4" />
                        <path d="M4 21a8 8 0 0 1 16 0" />
                    </svg>
                </span>
                <div>
                    <h2>Your name here.</h2>
                    <p>This is how you show up next to your moments in the gallery.</p>
                </div>
            </header>
            <form action="/settings/username" method="post" class="settings-form">
                <input type="hidden" name="csrf" value="<?= Http::escape($_SESSION['csrf']) ?>">
                <div class="settings-profile-row">
                    <span class="settings-avatar" aria-hidden="true">
                        <?= Http::escape(strtoupper(substr($user['username'], 0, 1))) ?></span>
                    <label>Username
                        <input name="username" required minlength="3" maxlength="30"
                            pattern="[A-Za-z0-9_]{3,30}"
                            value="<?= Http::escape($user['username']) ?>" autocomplete="username">
                    </label>
                </div>
                <footer class="settings-card-footer">
                    <button class="button" type="submit">Save username</button>
                </footer>
            </form>
        </section>
        <section class="settings-card" id="notifications">
            <header class="settings-card-heading">
                <span class="settings-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" />
                    </svg>
                </span>
                <div>
                    <h2>Keep in the loop.</h2>
                    <p>Choose when Camagru writes to you.</p>
                </div>
            </header>
            <form method="post" action="/settings/notifications" class="settings-form"
                data-ajax="preference">
                <input type="hidden" name="csrf" value="<?= Http::escape($_SESSION['csrf']) ?>">
                <div class="settings-form-body">
                    <label class="settings-toggle">
                        <span><strong>Email me about new comments</strong>
                            <small>Get an email when someone comments on your creations.</small>
                        </span>
                        <input type="checkbox" name="notify" value="1"
                            <?= $user['notify_comments'] ? 'checked' : '' ?>>
                        <span class="toggle-track" aria-hidden="true"></span>
                    </label>
                    <p class="form-status" role="status"></p>
                </div>
                <footer class="settings-card-footer">
                    <button class="button" type="submit">Save preference</button>
                </footer>
            </form>
        </section>
        <section class="settings-card" id="email">
            <header class="settings-card-heading">
                <span class="settings-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <rect x="3" y="5" width="18" height="14" rx="3" />
                        <path d="m4 7 8 6 8-6" />
                    </svg>
                </span>
                <div>
                    <h2>A new inbox.</h2>
                    <p>Your address only changes once you confirm the new one.</p>
                </div>
            </header>
            <form method="post" action="/settings/email" class="settings-form">
                <input type="hidden" name="csrf" value="<?= Http::escape($_SESSION['csrf']) ?>">
                <div class="settings-form-body">
                    <p class="current-email"><strong>Current email</strong>
                        <span><?= Http::escape($user['email']) ?></span>
                    </p>
                    <ol class="email-steps">
                        <li>
                            <span>1</span>Enter your new address
                        </li>
                        <li>
                            <span>2</span>Confirm it from your inbox
                        </li>
                    </ol>
                    <div class="settings-fields settings-fields-two">
                        <label>New email
                            <input name="email" type="email" required maxlength="254"
                                autocomplete="email" placeholder="name@example.com">
                        </label>
                        <input type="text" hidden name="username" autocomplete="username"
                            value="<?= Http::escape($user['username']) ?>">
                        <label>Current password<span class="password-input">
                                <input name="current_password" type="password" required
                                    autocomplete="current-password">
                                <button type="button" class="password-visibility"
                                    aria-label="Show password">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z" />
                                        <circle cx="12" cy="12" r="3" />
                                        <path class="eye-slash" d="M3 3l18 18" />
                                    </svg>
                                </button>
                            </span>
                        </label>
                    </div>
                </div>
                <footer class="settings-card-footer">
                    <button class="button" type="submit">Send confirmation</button>
                </footer>
            </form>
        </section>
        <section class="settings-card" id="password">
            <header class="settings-card-heading">
                <span class="settings-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <rect x="4" y="10" width="16" height="11" rx="3" />
                        <path d="M8 10V7a4 4 0 0 1 8 0v3" />
                    </svg>
                </span>
                <div>
                    <h2>A fresh password.</h2>
                    <p>Pick something only you would think of.</p>
                </div>
            </header>
            <form method="post" action="/settings/password" class="settings-form">
                <input type="hidden" name="csrf" value="<?= Http::escape($_SESSION['csrf']) ?>">
                <input type="text" hidden name="username" autocomplete="username"
                    value="<?= Http::escape($user['username']) ?>">
                <div class="settings-form-body settings-fields">
                    <label>Current password<span class="password-input">
                            <input name="current_password" type="password" required
                                autocomplete="current-password">
                            <button type="button" class="password-visibility"
                                aria-label="Show password">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z" />
                                    <circle cx="12" cy="12" r="3" />
                                    <path class="eye-slash" d="M3 3l18 18" />
                                </svg>
                            </button>
                        </span>
                    </label>
                    <label>New password<span class="password-input">
                            <input name="password" type="password" required minlength="15"
                                maxlength="128" autocomplete="new-password">
                            <button type="button" class="password-visibility"
                                aria-label="Show password">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z" />
                                    <circle cx="12" cy="12" r="3" />
                                    <path class="eye-slash" d="M3 3l18 18" />
                                </svg>
                            </button>
                        </span>
                    </label>
                    <div class="password-hint">
                        <span></span>
                        <span></span>
                        <span></span>
                        <span></span>
                        <p>15–128 characters, with a letter and a number.</p>
                    </div>
                    <ul class="password-rules">
                        <li>15–128 characters</li>
                        <li>Contains a letter</li>
                        <li>Contains a number</li>
                    </ul>
                    <label>Confirm new password<span class="password-input">
                            <input name="confirmation" type="password" required minlength="15"
                                maxlength="128" autocomplete="new-password">
                            <button type="button" class="password-visibility"
                                aria-label="Show password">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z" />
                                    <circle cx="12" cy="12" r="3" />
                                    <path class="eye-slash" d="M3 3l18 18" />
                                </svg>
                            </button>
                        </span>
                    </label>
                </div>
                <footer class="settings-card-footer">
                    <button class="button" type="submit">Update password</button>
                </footer>
            </form>
        </section>
    </div>
</div>
