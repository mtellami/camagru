import { test, expect, Page, APIRequestContext } from '@playwright/test';
import { randomBytes } from 'node:crypto';

const password = 'A playful password 12345';
const inbox = process.env.MAILPIT_URL || 'http://localhost:8025';

async function emailLink(request: APIRequestContext, email: string, subject: string) {
  let link = '';
  await expect.poll(async () => {
    const response = await request.get(`${inbox}/api/v1/messages`);
    const listing = await response.json();
    const message = listing.messages.find((message: any) => message.To.some((to: any) => to.Address === email) && message.Subject.includes(subject));
    if (!message) return false;
    const detail = await (await request.get(`${inbox}/api/v1/message/${message.ID}`)).json();
    link = detail.Text.match(/http:\/\/[^\s]+/)?.[0] || '';
    expect(detail.HTML).toContain('camagru');
    expect(detail.HTML).toContain(`href="${link.replace(/&/g, '&amp;')}"`);
    expect(detail.HTML).toContain(subject.includes('Reset your') ? 'Reset password' : subject.includes('new email') ? 'Confirm new email' : 'Confirm email');
    return !!link;
  }, { timeout: 20000, message: 'Expected account email to arrive in the local inbox' }).toBe(true);
  return new URL(link).pathname + new URL(link).search;
}

async function register(page: Page, request: APIRequestContext, username: string) {
  const email = `${username}@example.test`;
  await page.goto('/register');
  await page.getByLabel('Username', { exact: true }).fill(username);
  await page.getByLabel('Email address').fill(email);
  await page.getByLabel('Password', { exact: true }).fill(password);
  await page.getByLabel('Confirm password').fill(password);
  await page.getByRole('button', { name: 'Create your account' }).click();
  await expect(page).toHaveURL(/\/login$/);
  const csrf = await page.locator('meta[name="csrf-token"]').getAttribute('content');
  const invalid = await page.request.post('/login', {
    headers: { Accept: 'application/json', 'X-CSRF-Token': csrf! },
    form: { username, password: 'An incorrect password 99999' }
  });
  expect(invalid.status()).toBe(422);
  expect((await invalid.json()).error.message).toBe('The username or password is incorrect.');
  await page.getByLabel('Username', { exact: true }).fill(username);
  await page.getByLabel('Password', { exact: true }).fill('An incorrect password 99999');
  const failedLogin = page.waitForResponse(response => new URL(response.url()).pathname === '/login' && response.request().method() === 'POST');
  await page.getByRole('button', { name: 'Sign in', exact: true }).click();
  expect((await failedLogin).status()).toBe(303);
  await expect(page).toHaveURL(/\/login$/);
  await expect(page.getByRole('alert')).toContainText('The username or password is incorrect.');
  await expect(page.getByRole('alert').getByRole('link', { name: 'Reset your password' })).toHaveAttribute('href', '/forgot');
  await expect(page.getByLabel('Username', { exact: true })).toHaveValue(username);
  await expect(page.getByLabel('Password', { exact: true })).toHaveValue('');
  await page.getByLabel('Password', { exact: true }).fill(password);
  await page.getByRole('button', { name: 'Sign in', exact: true }).click();
  await expect(page).toHaveURL(/\/login$/);
  await expect(page.getByRole('alert')).toContainText('Confirm your email first.');
  await expect(page.getByRole('alert').getByRole('link', { name: 'resend your confirmation email' })).toHaveAttribute('href', '/resend');
  await expect(page.getByLabel('Password', { exact: true })).toHaveValue('');
  await page.reload();
  await expect(page.getByRole('alert')).toHaveCount(0);
  const confirmation = await emailLink(request, email, 'confirm your email');
  await page.goto(confirmation);
  await page.getByRole('button', { name: 'Confirm email' }).click();
  await expect(page).toHaveURL(/\/login$/);
  await page.getByLabel('Username', { exact: true }).fill(username);
  await page.getByLabel('Password', { exact: true }).fill(password);
  await page.getByRole('button', { name: 'Sign in', exact: true }).click();
  await expect(page).toHaveURL(/\/editor$/);
  return email;
}

test('Public pages are responsive, private pages reject visitors, and fallback APIs are optional', async ({ page }) => {
  const errors: string[] = [];
  page.on('pageerror', error => errors.push(error.message));
  page.on('console', message => errors.push(message.text()));
  await page.goto('/');
  await expect(page.getByRole('heading', { name: /Life looks better/ })).toBeVisible();
  await page.setViewportSize({ width: 375, height: 812 });
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
  await page.goto('/editor');
  await expect(page).toHaveURL(/\/login$/);
  await page.goto('/settings');
  await expect(page).toHaveURL(/\/login$/);
  expect(errors).toEqual([]);
});

test('Account lifecycle, camera and upload compositing, GIF, interactions, ownership, and reset', async ({ page, request, browser }, testInfo) => {
  test.setTimeout(180000);
  const suffix = `${testInfo.project.name.slice(0,2)}${Date.now().toString(36)}`;
  const username = `owner_${suffix}`;
  let email = await register(page, request, username);
  const errors: string[] = [];
  page.on('pageerror', error => errors.push(error.message));
  page.on('console', message => errors.push(message.text()));
  await expect(page.getByRole('button', { name: 'Capture moment' })).toBeDisabled();
  await page.getByRole('button', { name: 'Turn on camera' }).click();
  await expect(page.locator('#camera-status')).toContainText('Camera is on', { timeout: 15000 });
  await expect(page.getByRole('button', { name: 'Capture moment' })).toBeDisabled();
  await page.locator('input[value="postcard"]').check();
  await expect(page.locator('#overlay-preview')).toBeVisible();
  await page.getByRole('button', { name: 'Capture moment' }).click();
  await expect(page.locator('#history-grid a')).toHaveCount(1, { timeout: 15000 });
  const photoPath = await page.locator('#history-grid a').first().getAttribute('href');
  await page.locator('.gif-studio summary').click();
  await page.getByRole('button', { name: 'Record camera burst' }).click();
  await expect(page.locator('#history-grid a')).toHaveCount(2, { timeout: 20000 });
  const gifPath = await page.locator('#history-grid a').first().getAttribute('href');
  await page.goto(gifPath!);
  await expect(page.locator('.detail-image img')).toBeVisible();
  const gif = await page.request.get((await page.locator('.detail-image img').getAttribute('src'))!);
  expect(gif.headers()['content-type']).toBe('image/gif');
  expect((await gif.body()).subarray(0,6).toString()).toBe('GIF89a');
  await page.goto(photoPath!);
  await page.locator('form[data-ajax="like"] button').click();
  await expect(page.locator('form[data-ajax="like"] button')).toHaveAttribute('aria-pressed','true');
  await page.getByLabel('Leave a note').fill('<script>window.injected=true</script> a kind note');
  await page.getByRole('button', { name: 'Post comment' }).click();
  await expect(page.locator('.comment p')).toContainText('<script>');
  expect(await page.evaluate(() => (window as any).injected)).toBeUndefined();
  await expect.poll(async () => {
    const messages = await (await request.get(`${inbox}/api/v1/messages`)).json();
    return messages.messages.some((message: any) => message.To.some((to: any) => to.Address === email) && message.Subject.includes('new comment'));
  }, { timeout: 15000 }).toBe(true);
  await page.addInitScript(() => { Object.defineProperty(navigator, 'share', { value: undefined, configurable: true }); Object.defineProperty(window, 'IntersectionObserver', { value: undefined, configurable: true }); });
  await page.reload();
  await page.getByRole('button', { name: 'Share' }).click();
  await expect(page.locator('.share-fallback')).toBeVisible();

  const other = await browser.newContext();
  const otherPage = await other.newPage();
  await register(otherPage, request, `other_${suffix}`);
  const csrf = await otherPage.locator('meta[name="csrf-token"]').getAttribute('content');
  const forged = await otherPage.request.post(`${photoPath}/delete`, { headers: { Accept: 'application/json', 'X-CSRF-Token': csrf! } });
  expect(forged.status()).toBe(403);
  const missingCsrf = await otherPage.request.post(`${photoPath}/like`, { headers: { Accept: 'application/json' } });
  expect(missingCsrf.status()).toBe(403);
  await other.close();

  await page.goto('/settings');
  await page.getByLabel('Email me about new comments').uncheck();
  await page.getByRole('button', { name: 'Save preference' }).click();
  await expect(page.locator('form[data-ajax="preference"] .form-status')).toContainText('saved');
  await page.reload();
  await expect(page.getByLabel('Email me about new comments')).not.toBeChecked();
  await page.goto('/editor');
  // Upload mode works without camera APIs and blocks tampered source types.
  const png = await request.get('/assets/images/overlays/bloom.png');
  await page.locator('#image-input').setInputFiles({ name: 'source.png', mimeType: 'image/png', buffer: await png.body() });
  await page.locator('input[value="orbit"]').check();
  await page.getByRole('button', { name: 'Capture moment' }).click();
  await expect(page.locator('#history-grid a')).toHaveCount(3, { timeout: 15000 });
  const requestToken = await page.locator('meta[name="csrf-token"]').getAttribute('content');
  const headers = { Accept: 'application/json', 'X-CSRF-Token': requestToken! };
  const badUpload = await page.request.post('/photos', { headers, multipart: { overlay: 'orbit', submission: randomBytes(16).toString('hex'), image: { name: 'fake.jpg', mimeType: 'image/jpeg', buffer: Buffer.from('<svg/>') } } });
  expect(badUpload.status()).toBe(422);
  const badOverlay = await page.request.post('/photos', { headers, multipart: { overlay: '../../etc/passwd', submission: randomBytes(16).toString('hex'), image: { name: 'source.png', mimeType: 'image/png', buffer: await png.body() } } });
  expect(badOverlay.status()).toBe(422);
  const source = await png.body();
  const tooManyFrames = new FormData();
  tooManyFrames.append('overlay','orbit');
  tooManyFrames.append('submission',randomBytes(16).toString('hex'));
  for (let i = 0; i < 9; i++) tooManyFrames.append('frames[]',new Blob([source],{ type: 'image/png' }),`frame-${i}.png`);
  expect((await page.request.post('/photos/gif',{ headers, multipart: tooManyFrames })).status()).toBe(422);
  for (let i = 0; i < 10; i++) {
    const response = await page.request.post('/photos', { headers, multipart: { overlay: 'postcard', submission: randomBytes(16).toString('hex'), image: { name: 'source.png', mimeType: 'image/png', buffer: source } } });
    expect(response.status()).toBe(201);
  }
  const first = await (await page.request.get('/gallery', { headers: { Accept: 'application/json' } })).json();
  expect(first.data.photos).toHaveLength(10);
  const second = await (await page.request.get(`/gallery?before=${encodeURIComponent(first.data.cursor)}`, { headers: { Accept: 'application/json' } })).json();
  const ids = new Set(first.data.photos.map((photo: any) => photo.id));
  expect(second.data.photos.every((photo: any) => !ids.has(photo.id))).toBe(true);
  await page.goto('/gallery');
  await expect(page.locator('#gallery-grid .photo-card')).toHaveCount(10);
  await page.locator('.load-more').scrollIntoViewIfNeeded();
  await expect.poll(() => page.locator('#gallery-grid .photo-card').count()).toBeGreaterThan(10);

  await page.goto('/settings');
  const updatedUsername = username + '_new';
  await page.getByLabel('Username', { exact: true }).fill(updatedUsername);
  await page.getByRole('button', { name: 'Save username' }).click();
  await expect(page.getByLabel('Username', { exact: true })).toHaveValue(updatedUsername);
  const newEmail = `new_${suffix}@example.test`;
  await page.getByLabel('New email', { exact: true }).fill(newEmail);
  await page.locator('form[action="/settings/email"] input[name="current_password"]').fill(password);
  await page.getByRole('button', { name: 'Send confirmation' }).click();
  await expect(page.getByText(`Current email: ${email}.`, { exact: false })).toBeVisible();
  await page.goto(await emailLink(request,newEmail,'Confirm your new email'));
  await page.getByRole('button', { name: 'Confirm email' }).click();
  email = newEmail;
  await page.goto('/settings');
  await expect(page.getByText(`Current email: ${email}.`, { exact: false })).toBeVisible();
  const settingsPassword = page.locator('form[action="/settings/password"]');
  await settingsPassword.getByLabel('Current password').fill(password);
  await settingsPassword.getByLabel('New password', { exact: true }).fill('An intermediate password 54321');
  await settingsPassword.getByLabel('Confirm new password').fill('An intermediate password 54321');
  await settingsPassword.getByRole('button', { name: 'Update password' }).click();
  await expect(page.locator('.flash')).toContainText('Other sessions');

  const oldSession = await browser.newContext();
  const oldPage = await oldSession.newPage();
  await oldPage.goto('/login');
  await oldPage.getByLabel('Username', { exact: true }).fill(updatedUsername);
  await oldPage.getByLabel('Password', { exact: true }).fill('An intermediate password 54321');
  await oldPage.getByRole('button', { name: 'Sign in', exact: true }).click();
  await expect(oldPage).toHaveURL(/\/editor$/);
  await page.goto(photoPath!);
  page.once('dialog', dialog => dialog.accept());
  await page.getByRole('button', { name: 'Delete this creation' }).click();
  await expect(page).toHaveURL(/\/editor$/);
  expect((await page.request.get(`${photoPath!.replace('/photos/','/media/')}/image`)).status()).toBe(404);

  await page.getByRole('button', { name: 'Sign out' }).click();
  await page.waitForLoadState('load');
  await page.goto('/forgot');
  await page.getByLabel('Email address').fill(email);
  await page.getByRole('button', { name: 'Send me a link' }).click();
  await page.goto(await emailLink(request,email,'Reset your'));
  await page.getByLabel('Password', { exact: true }).fill('A different password 67890');
  await page.getByLabel('Confirm password').fill('A different password 67890');
  await page.getByRole('button', { name: 'Save new password' }).click();
  await expect(page).toHaveURL(/\/login$/);
  await oldPage.goto('/editor');
  await expect(oldPage).toHaveURL(/\/login$/);
  await oldSession.close();
  await page.getByLabel('Username', { exact: true }).fill(updatedUsername);
  await page.getByLabel('Password', { exact: true }).fill('A different password 67890');
  await page.getByRole('button', { name: 'Sign in', exact: true }).click();
  await expect(page).toHaveURL(/\/editor$/);
  expect(errors).toEqual([]);
});
