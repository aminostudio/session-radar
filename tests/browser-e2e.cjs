/**
 * Browser-level Orbit QA in a disposable WordPress installation.
 *
 * Requires the GitHub Actions WordPress fixture at 127.0.0.1:8080.
 * Exercises authenticated HTTP, real browser AJAX, focus behavior and axe-core.
 * No external or production site credentials are used.
 */
'use strict';

const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const { resolve } = require('node:path');
const { chromium } = require('playwright');

const URL = 'http://127.0.0.1:8080';
const ADMIN = 'orbit_ci_admin';
const ADMIN_PASSWORD = 'temporary-ci-password-not-reused';
const EDITOR = 'orbit_ci_editor';
const EDITOR_PASSWORD = 'temporary-editor-ci-password';
const WP_ROOT = process.env.WP_ROOT || '/tmp/orbit-wp';
const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'];
const QA_FILE = resolve(__dirname, 'browser-fixture.php');

function check(condition, description) {
  assert.ok(condition, description);
  console.log('PASS: ' + description);
}

function fixture(mode) {
  const output = execFileSync(
    'wp',
    ['eval-file', QA_FILE, '--path=' + WP_ROOT],
    {
      env: { ...process.env, ORBIT_BROWSER_FIXTURE_MODE: mode },
      encoding: 'utf8',
      stdio: ['ignore', 'pipe', 'pipe']
    }
  );
  console.log('FIXTURE: ' + output.trim());
}

async function login(page, name, password) {
  await page.goto(URL + '/wp-login.php', { waitUntil: 'domcontentloaded' });
  await page.locator('#user_login').fill(name);
  await page.locator('#user_pass').fill(password);
  await Promise.all([
    page.waitForURL(/\/wp-admin(?:\/|\/index\.php)/, { timeout: 15000 }),
    page.locator('#wp-submit').click()
  ]);
  check(page.url().includes('/wp-admin'), name + ' authenticated in a real browser');
}

async function refresh(page) {
  const responsePromise = page.waitForResponse(
    (r) => r.url().includes('admin-ajax.php') &&
      (r.request().postData() || '').includes('action=amino_orbit_refresh'),
    { timeout: 10000 }
  );
  await page.evaluate(() => {
    document.dispatchEvent(new Event('visibilitychange'));
  });
  const response = await responsePromise;
  check(response.status() === 200, 'Authenticated widget AJAX refresh returned HTTP 200');
  const payload = await response.json();
  check(payload.success === true && typeof payload.data?.html === 'string', 'AJAX returned permission-checked HTML');
}

async function axeScan(page, selector) {
  await page.addScriptTag({ path: require.resolve('axe-core/axe.min.js') });
  const result = await page.evaluate(async ({ selector, tags }) => {
    const findings = await window.axe.run(selector, {
      runOnly: { type: 'tag', values: tags }
    });
    return {
      violations: findings.violations.map((item) => ({
        id: item.id,
        impact: item.impact,
        nodes: item.nodes.map((node) => ({
          html: node.html,
          summary: node.failureSummary
        }))
      })),
      passes: findings.passes.length,
      incomplete: findings.incomplete.length
    };
  }, { selector, tags: TAGS });
  if (result.violations.length) {
    console.error('ACCESSIBILITY VIOLATIONS:', JSON.stringify(result.violations, null, 2));
  }
  check(result.violations.length === 0, 'axe-core WCAG 2.1 AA scope ' + selector + ': zero automatic violations');
  console.log('INFO: axe ' + selector + ': ' + result.passes + ' passing rules, ' + result.incomplete + ' manual-review items');
}

async function main() {
  fixture('setup');
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));

  try {
    const anon = await context.request.post(URL + '/wp-admin/admin-ajax.php', {
      form: { action: 'amino_orbit_refresh', nonce: 'incorrect-anonymous-nonce' }
    });
    const anonBody = await anon.text();
    check(!anonBody.includes('amino-orbit-panel') && anon.status() >= 400,
      'Anonymous AJAX request cannot obtain session dashboard data');

    await login(page, ADMIN, ADMIN_PASSWORD);
    const panel = page.locator('#amino-orbit-panel');
    await panel.waitFor({ state: 'visible', timeout: 12000 });
    check(await panel.count() === 1, 'Dashboard widget is visible to administrator');
    check([true, 1, '1'].includes(await page.evaluate(() => window.aminoOrbitConfig.trackActivity)),
      'Disposable site explicitly enables activity monitoring');
    check(Number(await page.evaluate(() => window.aminoOrbitConfig.refreshMs)) === 30000,
      'The installed browser config uses 30-second refresh');
    await page.waitForTimeout(500);
    await refresh(page);
    await page.locator('#amino-orbit-panel .amino-orbit__status--active')
      .first().waitFor({ timeout: 9000 });
    check(true, 'Real authenticated browser ping becomes an active session after AJAX refresh');

    // Focus inside the widget must prevent automatic HTML replacement.
    const oldPanel = await panel.elementHandle();
    const settingsLink = page.locator('#amino-orbit-panel .amino-orbit__footer a');
    await settingsLink.focus();
    check(await settingsLink.evaluate((el) => el === document.activeElement),
      'Settings link can receive keyboard focus');
    await page.evaluate(() => document.dispatchEvent(new Event('visibilitychange')));
    await page.waitForTimeout(400);
    check(await oldPanel.evaluate((el) => el.isConnected),
      'Auto-refresh does not replace the focused widget DOM');

    await page.evaluate(() => document.activeElement.blur());
    await refresh(page);
    await page.waitForFunction((node) => !node.isConnected, oldPanel, { timeout: 10000 });
    check(true, 'Unfocused widget is safely replaced by refreshed server HTML');

    // Regression: the user may focus Settings while an AJAX response is in flight.
    // The widget must not replace a newly focused link after the fetch resolves.
    const racingPanel = await page.locator('#amino-orbit-panel').elementHandle();
    const racingLink = page.locator('#amino-orbit-panel .amino-orbit__footer a');
    await page.route('**/wp-admin/admin-ajax.php', async (route) => {
      if ((route.request().postData() || '').includes('action=amino_orbit_refresh')) {
        await new Promise((done) => setTimeout(done, 500));
      }
      return route.continue();
    });
    const racingRequest = page.waitForRequest(
      (request) => request.url().includes('admin-ajax.php') &&
        (request.postData() || '').includes('action=amino_orbit_refresh')
    );
    await page.evaluate(() => document.dispatchEvent(new Event('visibilitychange')));
    await racingRequest;
    await racingLink.focus();
    await page.waitForResponse(
      (response) => response.url().includes('admin-ajax.php') &&
        (response.request().postData() || '').includes('action=amino_orbit_refresh')
    );
    await page.waitForTimeout(120);
    check(await racingPanel.evaluate((node) =>
      node.isConnected && node.contains(document.activeElement)
    ), 'Keyboard focus survives an in-flight AJAX response');
    await page.unroute('**/wp-admin/admin-ajax.php');
    await page.evaluate(() => document.activeElement.blur());

    await axeScan(page, '#amino-orbit-panel');

    // Layout reflow checks at CSS-equivalent 200% and 400% zoom.
    // CSS zoom is an automated approximation, not a manual browser zoom audit.
    for (const zoom of ['2', '4']) {
      const result = await page.evaluate((factor) => {
        document.documentElement.style.zoom = factor;
        const widget = document.querySelector('#amino-orbit-panel');
        const link = widget?.querySelector('.amino-orbit__footer a');
        return {
          panelPresent: Boolean(widget),
          hasOverflow: widget ? widget.scrollWidth > widget.clientWidth + 2 : true,
          linkVisible: Boolean(link && link.getClientRects().length),
          viewportWidth: document.documentElement.clientWidth
        };
      }, zoom);
      check(result.panelPresent && !result.hasOverflow && result.linkVisible,
        'Widget reflows without internal horizontal overflow at simulated ' +
        (Number(zoom) * 100) + '% CSS zoom');
    }
    await page.evaluate(() => { document.documentElement.style.zoom = ''; });


    // The page can be viewed with simulated narrow viewport, 200% zoom,
    // high-contrast colors and reduced motion. Axe does not replace manual QA.
    await page.setViewportSize({ width: 375, height: 812 });
    await page.emulateMedia({ forcedColors: 'active', reducedMotion: 'reduce' });
    const dimensions = await page.locator('#amino-orbit-panel').evaluate((el) => ({
      width: el.getBoundingClientRect().width,
      viewport: document.documentElement.clientWidth
    }));
    check(dimensions.width <= dimensions.viewport + 1,
      'Widget fits narrow viewport in high-contrast mode');

    // Block *future* pings while we verify an expired presence state.
    await page.route('**/wp-admin/admin-ajax.php', async (route) => {
      if ((route.request().postData() || '').includes('action=amino_orbit_ping')) {
        return route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ success: true, data: { tracked: false } })
        });
      }
      return route.continue();
    });
    fixture('idle');
    await refresh(page);
    await page.locator('#amino-orbit-panel .amino-orbit__status--idle')
      .first().waitFor({ timeout: 12000 });
    check(true, 'A native session with ten-minute-old activity is displayed inactive');

    // Restore pings, send a genuine keyboard signal and refresh again.
    await page.unroute('**/wp-admin/admin-ajax.php');
    const pingResponse = page.waitForResponse(
      (r) => r.url().includes('admin-ajax.php') &&
        (r.request().postData() || '').includes('action=amino_orbit_ping'),
      { timeout: 10000 }
    );
    await page.keyboard.press('ArrowDown');
    await page.evaluate(() => document.dispatchEvent(new Event('visibilitychange')));
    const ping = await pingResponse;
    check(ping.status() === 200 && (await ping.json()).data?.tracked === true,
      'Real AJAX activity ping records resumed interaction');
    await refresh(page);
    await page.locator('#amino-orbit-panel .amino-orbit__status--active')
      .first().waitFor({ timeout: 12000 });
    check(true, 'An inactive session becomes active after actual keyboard interaction');

    // WordPress-admin accessibility, including accessible names and input labels.
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.emulateMedia({ forcedColors: 'none', reducedMotion: 'no-preference' });
    await page.goto(URL + '/wp-admin/options-general.php?page=amino-orbit');
    await page.locator('.amino-orbit-settings').waitFor({ state: 'visible' });
    check(await page.locator('.amino-orbit-settings fieldset legend').count() > 0,
      'Settings roles use a real fieldset and legend');
    await axeScan(page, '.amino-orbit-settings');

    for (const zoom of ['2', '4']) {
      const result = await page.evaluate((factor) => {
        document.documentElement.style.zoom = factor;
        const settings = document.querySelector('.amino-orbit-settings');
        return settings && settings.scrollWidth <= settings.clientWidth + 2;
      }, zoom);
      check(Boolean(result), 'Settings panel reflows at simulated ' +
        (Number(zoom) * 100) + '% CSS zoom');
    }
    await page.evaluate(() => { document.documentElement.style.zoom = ''; });


    // WordPress user roles are checked over real HTTP authentication.
    const editorContext = await browser.newContext();
    const editorPage = await editorContext.newPage();
    await login(editorPage, EDITOR, EDITOR_PASSWORD);
    check(await editorPage.locator('#amino_orbit_widget').count() === 0,
      'Unauthorized editor cannot see the dashboard widget');
    const rejected = await editorPage.evaluate(async () => {
      const config = window.aminoOrbitConfig;
      const response = await fetch(config.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'amino_orbit_refresh', nonce: config.nonce })
      });
      return { status: response.status, payload: await response.json() };
    });
    check(rejected.status === 403 && rejected.payload.success === false,
      'Unauthorized editor gets HTTP 403 with a valid logged-in nonce');

    fixture('allow-editor');
    await editorPage.reload({ waitUntil: 'domcontentloaded' });
    await editorPage.locator('#amino-orbit-panel').waitFor({ state: 'visible' });
    check(true, 'Explicitly allowed editor sees the widget after reload');
    check(!(await editorPage.locator('#amino-orbit-panel').innerText()).includes('192.0.2.'),
      'Non-admin editor never sees stored WordPress IP addresses');
    await editorContext.close();

    // These are real WordPress admin page requests with the logged-in
    // administrator's locale, not mocked gettext or isolated PHP output.
    const localeCases = [
      {
        mode: 'locale-fr',
        refresh: 'Actualisation : 30 s',
        heading: 'Qui peut voir le widget ?'
      },
      {
        mode: 'locale-es',
        refresh: 'Actualización: 30 s',
        heading: '¿Quién puede ver el widget?'
      },
      {
        mode: 'locale-en',
        refresh: 'Refresh: 30 s',
        heading: 'Who can see the widget?'
      }
    ];
    for (const locale of localeCases) {
      fixture(locale.mode);
      await page.goto(URL + '/wp-admin/index.php', {
        waitUntil: 'domcontentloaded'
      });
      await page.locator('#amino-orbit-panel').waitFor({ state: 'visible' });
      check((await page.locator('#amino-orbit-panel').innerText()).includes(locale.refresh),
        'WordPress dashboard renders ' + locale.mode + ' locale in the real browser');
      await page.goto(URL + '/wp-admin/options-general.php?page=amino-orbit', {
        waitUntil: 'domcontentloaded'
      });
      await page.locator('.amino-orbit-settings').waitFor({ state: 'visible' });
      check((await page.locator('.amino-orbit-settings').innerText()).includes(locale.heading),
        'WordPress settings page renders ' + locale.mode + ' locale in the real browser');
    }

    // Logout must revoke access to the formerly authenticated AJAX endpoint.
    const logout = await page.locator('#wp-admin-bar-logout a').getAttribute('href');
    check(!!logout, 'WordPress provides a logout URL');
    await page.goto(logout, { waitUntil: 'domcontentloaded' });
    const denied = await context.request.post(URL + '/wp-admin/admin-ajax.php', {
      form: { action: 'amino_orbit_refresh', nonce: 'stale' }
    });
    check(denied.status() >= 400, 'After logout, an AJAX refresh cannot obtain session data');

    check(errors.length === 0, 'No uncaught JavaScript exceptions during browser QA');
    console.log('SUCCESS: Real Chromium + WordPress integration, AJAX, idle, focus and accessibility tests passed.');
  } catch (error) {
    console.error('FAIL:', error.stack || String(error));
    try {
      await page.screenshot({ path: '/tmp/orbit-browser-failure.png', fullPage: true });
      console.error('Browser screenshot saved in CI failure artifact.');
    } catch (_) { /* Best-effort debug aid. */ }
    throw error;
  } finally {
    await context.close();
    await browser.close();
  }
}

main().catch(() => { process.exitCode = 1; });
