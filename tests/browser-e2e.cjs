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

    // v0.6 regression: search must respond before any filter button is clicked.
    const immediateSearch = page.locator('#amino-orbit-panel .amino-orbit__search');
    await immediateSearch.fill('nonexistent-orbit-session-query');
    check(await page.locator('#amino-orbit-panel .amino-orbit__user:not([hidden])').count() === 0,
      'Live search works without first clicking a status filter');
    await immediateSearch.fill('');
    check(await page.locator('#amino-orbit-panel .amino-orbit__user:not([hidden])').count() >= 1,
      'Clearing search restores the local visible session list');

    // RC7: each status button shows the number of already-rendered sessions
    // matching role and search, ignoring the currently selected status facet.
    const facetCounters = page.locator('#amino-orbit-panel .amino-orbit__filter .amino-orbit__status-count');
    check(await facetCounters.count() === 4, 'Each activity status shows a local count');
    const facetTotals = await page.locator('#amino-orbit-panel').evaluate((panel) => {
      const counts = {};
      for (const button of panel.querySelectorAll('.amino-orbit__filter')) {
        counts[button.dataset.orbitStatus] = Number(button.querySelector('.amino-orbit__status-count')?.textContent);
      }
      return counts;
    });
    check(facetTotals.all === await page.locator('#amino-orbit-panel .amino-orbit__user').count() &&
      facetTotals.active + facetTotals.idle + facetTotals.session === facetTotals.all,
      'Status badges partition only the currently authorized session rows');
    await immediateSearch.fill('nonexistent-facet-query');
    check(await facetCounters.evaluateAll((nodes) => nodes.every((node) => node.textContent.trim() === '0')),
      'Search immediately reduces all status badges to zero without a network request');
    await immediateSearch.fill('');
    check(Number(await facetCounters.first().textContent()) === facetTotals.all,
      'Clearing search restores original status badge counts');

    // v0.4: filters are local, keyboard-operable and survive AJAX replacement.
    const activeFilter = page.locator('#amino-orbit-panel .amino-orbit__filter[data-orbit-status="active"]');
    await activeFilter.click();
    check(await activeFilter.getAttribute('aria-pressed') === 'true',
      'Active filter exposes pressed state after click');
    check(await page.locator('#amino-orbit-panel .amino-orbit__user:not([hidden])').count() >= 1,
      'Active filter retains matching sessions');
    const filteredCount = await page.locator('#amino-orbit-panel .amino-orbit__user:not([hidden])').count();
    const totalCount = await page.locator('#amino-orbit-panel .amino-orbit__user').count();
    check(filteredCount <= totalCount &&
      await page.locator('#amino-orbit-panel .amino-orbit__filter-count').textContent() === 'Showing ' + filteredCount + ' of ' + totalCount + ' displayed sessions',
      'Status filtering preserves all rows and announces the visible count');
    await page.evaluate(() => document.activeElement.blur());
    await refresh(page);
    await page.waitForFunction(() => {
      const panel = document.getElementById('amino-orbit-panel');
      return panel?.querySelector('.amino-orbit__filter[data-orbit-status="active"]')?.getAttribute('aria-pressed') === 'true';
    });
    check(await page.locator('#amino-orbit-panel .amino-orbit__filter[data-orbit-status="active"]').getAttribute('aria-pressed') === 'true',
      'Selected filter persists after authenticated refresh');
    await page.locator('#amino-orbit-panel .amino-orbit__filter[data-orbit-status="all"]').focus();
    await page.keyboard.press('Enter');
    check(await page.locator('#amino-orbit-panel .amino-orbit__filter[data-orbit-status="all"]').getAttribute('aria-pressed') === 'true',
      'Keyboard Enter restores all sessions');
    await page.evaluate(() => document.activeElement.blur());

    // v0.5: search and status filters compose locally, excluding IP metadata.
    const searchField = page.locator('#amino-orbit-panel .amino-orbit__search');
    await searchField.fill('no-such-orbit-user-2026');
    check(await page.locator('#amino-orbit-panel .amino-orbit__user:not([hidden])').count() === 0,
      'A nonexistent query hides all rendered session rows');
    check((await page.locator('#amino-orbit-panel .amino-orbit__filter-count').textContent()).startsWith('Showing 0 of '),
      'Empty search announces zero visible results');
    await searchField.fill('192.0.2.');
    check(await page.locator('#amino-orbit-panel .amino-orbit__user:not([hidden])').count() === 0,
      'WordPress session IP addresses are never searchable');
    await searchField.fill('admin');
    check(await page.locator('#amino-orbit-panel .amino-orbit__user:not([hidden])').count() >= 1,
      'Visible user and role metadata are searchable');
    await page.locator('#amino-orbit-panel .amino-orbit__filter[data-orbit-status="active"]').click();
    check(await page.locator('#amino-orbit-panel .amino-orbit__user:not([hidden])').count() >= 1,
      'Live search composes with selected status');
    const searchPanel = await page.locator('#amino-orbit-panel').elementHandle();
    await page.evaluate(() => document.activeElement.blur());
    await refresh(page);
    await page.waitForFunction((node) => !node.isConnected, searchPanel, { timeout: 10000 });
    check(await page.locator('#amino-orbit-panel .amino-orbit__search').inputValue() === 'admin',
      'Search query persists across an authorized AJAX panel replacement');
    await page.locator('#amino-orbit-panel .amino-orbit__search').fill('');
    await page.locator('#amino-orbit-panel .amino-orbit__filter[data-orbit-status="all"]').click();
    await page.evaluate(() => document.activeElement.blur());

    // RC4: filtering text must not detach/reappend unchanged session rows.
    const stableFirstRow = await page.locator('#amino-orbit-panel .amino-orbit__user').first().elementHandle();
    await page.locator('#amino-orbit-panel .amino-orbit__search').fill('example');
    check(await stableFirstRow.evaluate((node) => node.isConnected &&
      node === document.querySelector('#amino-orbit-panel .amino-orbit__user')),
      'Live search preserves existing row node order when sort mode is unchanged');
    await page.locator('#amino-orbit-panel .amino-orbit__search').fill('');

    // v0.6: deterministic sort from preexisting WordPress login timestamps.
    const sortField = page.locator('#amino-orbit-panel .amino-orbit__sort');
    await sortField.selectOption('oldest');
    const loginTimes = await page.locator('#amino-orbit-panel .amino-orbit__user')
      .evaluateAll((rows) => rows.map((row) => Number(row.dataset.orbitLogin)));
    check(loginTimes.every((value, i) => i === 0 || loginTimes[i - 1] <= value),
      'Oldest login sorting is ascending and deterministic');
    const sortedPanel = await page.locator('#amino-orbit-panel').elementHandle();
    await page.evaluate(() => document.activeElement.blur());
    await refresh(page);
    await page.waitForFunction((node) => !node.isConnected, sortedPanel, { timeout: 10000 });
    check(await page.locator('#amino-orbit-panel .amino-orbit__sort').inputValue() === 'oldest',
      'Sorting selection persists across AJAX replacement');
    await page.locator('#amino-orbit-panel .amino-orbit__sort').selectOption('newest');
    const newestTimes = await page.locator('#amino-orbit-panel .amino-orbit__user')
      .evaluateAll((rows) => rows.map((row) => Number(row.dataset.orbitLogin)));
    check(newestTimes.every((value, i) => i === 0 || newestTimes[i - 1] >= value),
      'Newest login sorting is descending');
    await page.locator('#amino-orbit-panel .amino-orbit__sort').selectOption('suggested');
    await page.evaluate(() => document.activeElement.blur());

    // v0.7: explicit refresh is accessible and restores its own keyboard focus.
    const manualButton = page.locator('#amino-orbit-panel .amino-orbit__refresh-now');
    check(await manualButton.count() === 1, 'Manual session refresh control is present');
    const manualRequest = page.waitForResponse((response) =>
      response.url().includes('admin-ajax.php') &&
      (response.request().postData() || '').includes('action=amino_orbit_refresh')
    );
    await manualButton.click();
    check((await manualRequest).status() === 200, 'Manual refresh reaches authorized AJAX endpoint');
    await page.waitForFunction(() =>
      document.querySelector('.amino-orbit__refresh-feedback')?.textContent?.includes('Sessions updated')
    );
    check(await page.locator('#amino-orbit-panel .amino-orbit__refresh-now')
      .evaluate((node) => node === document.activeElement),
      'Manual refresh preserves its button focus across panel replacement');
    await page.evaluate(() => document.activeElement.blur());

    // v0.8: role choices only describe already-rendered authorized sessions.
    const roleField = page.locator('#amino-orbit-panel .amino-orbit__role-filter');
    check(await roleField.count() === 1, 'Accessible role filter is present');
    const availableRoles = await roleField.locator('option').evaluateAll(
      (options) => options.map((option) => option.value).filter(Boolean)
    );
    check(availableRoles.includes('administrator'),
      'Visible administrator sessions provide an administrator role choice');
    await roleField.selectOption('administrator');
    check(await page.locator('#amino-orbit-panel .amino-orbit__user:not([hidden])')
      .evaluateAll((rows) => rows.every((row) =>
        (row.dataset.orbitRoles || '').split(/\s+/).includes('administrator')
      )), 'Role filter never exposes rows of other roles');
    const rolePanel = await page.locator('#amino-orbit-panel').elementHandle();
    await page.evaluate(() => document.activeElement.blur());
    await refresh(page);
    await page.waitForFunction((node) => !node.isConnected, rolePanel, { timeout: 10000 });
    check(await page.locator('#amino-orbit-panel .amino-orbit__role-filter')
      .inputValue() === 'administrator', 'Role selection survives AJAX replacement');
    await page.locator('#amino-orbit-panel .amino-orbit__role-filter').selectOption('');
    await page.evaluate(() => document.activeElement.blur());

    // v0.9: unambiguous no-match message and single-action reset.
    await page.locator('#amino-orbit-panel .amino-orbit__search').fill('orbit-no-matches-ever');
    check(await page.locator('#amino-orbit-panel .amino-orbit__empty-filter').isVisible(),
      'No matching sessions reports an explicit accessible empty state');
    const resetButton = page.locator('#amino-orbit-panel .amino-orbit__reset');
    await resetButton.click();
    check(await page.locator('#amino-orbit-panel .amino-orbit__search').inputValue() === '' &&
      await page.locator('#amino-orbit-panel .amino-orbit__role-filter').inputValue() === '' &&
      await page.locator('#amino-orbit-panel .amino-orbit__sort').inputValue() === 'suggested' &&
      await page.locator('#amino-orbit-panel .amino-orbit__filter[data-orbit-status="all"]')
        .getAttribute('aria-pressed') === 'true',
      'One reset clears query, role, order and status');
    check(await page.locator('#amino-orbit-panel .amino-orbit__empty-filter').isHidden(),
      'Reset hides no-matches message once sessions are visible');
    const compactButton = page.locator('#amino-orbit-panel .amino-orbit__compact-toggle');
    await compactButton.click();
    check(await compactButton.getAttribute('aria-pressed') === 'true' &&
      await page.locator('#amino-orbit-panel').evaluate((panel) =>
        panel.classList.contains('amino-orbit--compact')
      ), 'Compact display exposes correct pressed state');
    const densePanel = await page.locator('#amino-orbit-panel').elementHandle();
    await page.evaluate(() => document.activeElement.blur());
    await refresh(page);
    await page.waitForFunction((node) => !node.isConnected, densePanel, { timeout: 10000 });
    check(await page.locator('#amino-orbit-panel .amino-orbit__compact-toggle')
      .getAttribute('aria-pressed') === 'true',
      'Compact preference survives AJAX refresh without persistence');
    await page.locator('#amino-orbit-panel .amino-orbit__compact-toggle').click();
    await page.evaluate(() => document.activeElement.blur());

    // v1.1: pausing auto-refresh must prevent network polling, but manual refresh
    // remains usable, and the tab-local preference survives DOM replacement.
    const pauseButton = page.locator('#amino-orbit-panel .amino-orbit__pause-refresh');
    check(await pauseButton.count() === 1 && await pauseButton.getAttribute('aria-pressed') === 'false',
      'Accessible automatic refresh pause control is initially unpressed');
    await pauseButton.click();
    check(await pauseButton.getAttribute('aria-pressed') === 'true' &&
      (await pauseButton.textContent()).includes('Resume automatic refresh'),
      'Pause state is announced through pressed state and resume label');
    await page.evaluate(() => document.activeElement.blur());
    const autoRequests = [];
    const recordAutoRequests = (request) => {
      if ((request.postData() || '').includes('action=amino_orbit_refresh')) autoRequests.push(request);
    };
    page.on('request', recordAutoRequests);
    await page.evaluate(() => document.dispatchEvent(new Event('visibilitychange')));
    await page.waitForTimeout(250);
    check(autoRequests.length === 0, 'Paused tab does not refresh when it becomes visible');
    page.off('request', recordAutoRequests);
    const manualWhilePaused = page.waitForResponse((response) =>
      response.url().includes('admin-ajax.php') &&
      (response.request().postData() || '').includes('action=amino_orbit_refresh')
    );
    await page.locator('#amino-orbit-panel .amino-orbit__refresh-now').click();
    check((await manualWhilePaused).status() === 200,
      'Explicit manual refresh still works while automatic refresh is paused');
    await page.waitForFunction(() =>
      document.querySelector('#amino-orbit-panel .amino-orbit__pause-refresh')?.getAttribute('aria-pressed') === 'true'
    );
    check((await page.locator('#amino-orbit-panel .amino-orbit__live-label').textContent()).trim() === 'Paused',
      'Paused mode survives AJAX panel replacement and updates dashboard indicator');
    await page.locator('#amino-orbit-panel .amino-orbit__pause-refresh').click();
    check(await page.locator('#amino-orbit-panel .amino-orbit__pause-refresh').getAttribute('aria-pressed') === 'false',
      'Resuming automatic refresh restores the button state');
    await page.evaluate(() => document.activeElement.blur());

    // RC6: pausing while an automatic response is still in flight must win.
    // The already-authorized server response must not silently unpause the
    // dashboard or change the last-checked timestamp after the user's choice.
    await page.route('**/wp-admin/admin-ajax.php', async (route) => {
      if ((route.request().postData() || '').includes('action=amino_orbit_refresh')) {
        await new Promise((done) => setTimeout(done, 420));
      }
      return route.continue();
    });
    const beforePausePanel = await page.locator('#amino-orbit-panel').elementHandle();
    const beforePauseStamp = await page.locator('#amino-orbit-panel .amino-orbit__last-checked').textContent();
    const pendingAutoRequest = page.waitForRequest((request) =>
      request.url().includes('admin-ajax.php') &&
      (request.postData() || '').includes('action=amino_orbit_refresh')
    );
    const pendingAutoResponse = page.waitForResponse((response) =>
      response.url().includes('admin-ajax.php') &&
      (response.request().postData() || '').includes('action=amino_orbit_refresh')
    );
    await page.evaluate(() => document.dispatchEvent(new Event('visibilitychange')));
    await pendingAutoRequest;
    await page.locator('#amino-orbit-panel .amino-orbit__pause-refresh').click();
    await page.evaluate(() => document.activeElement.blur());
    check((await pendingAutoResponse).status() === 200,
      'Previously dispatched automatic request may still complete after pause');
    await page.waitForTimeout(80);
    check(await beforePausePanel.evaluate((node) => node.isConnected) &&
      await page.locator('#amino-orbit-panel .amino-orbit__pause-refresh').getAttribute('aria-pressed') === 'true' &&
      await page.locator('#amino-orbit-panel .amino-orbit__last-checked').textContent() === beforePauseStamp,
      'Pausing in flight prevents auto replacement and false freshness update');
    await page.unroute('**/wp-admin/admin-ajax.php');
    await page.locator('#amino-orbit-panel .amino-orbit__pause-refresh').click();
    await page.evaluate(() => document.activeElement.blur());

    // RC2: the explicit refresh button remains focusable and prevents repeated
    // requests. Changing focus while the response is in flight must not erase
    // the user's editing state or report a successful request as a failure.
    let guardedRefreshRequests = 0;
    await page.route('**/wp-admin/admin-ajax.php', async (route) => {
      if ((route.request().postData() || '').includes('action=amino_orbit_refresh')) {
        guardedRefreshRequests += 1;
        await new Promise((done) => setTimeout(done, 350));
      }
      return route.continue();
    });
    const deferredPanel = await page.locator('#amino-orbit-panel').elementHandle();
    const deferredResponse = page.waitForResponse((response) =>
      response.url().includes('admin-ajax.php') &&
      (response.request().postData() || '').includes('action=amino_orbit_refresh')
    );
    await page.locator('#amino-orbit-panel .amino-orbit__refresh-now').click();
    check(await page.locator('#amino-orbit-panel .amino-orbit__refresh-now')
      .getAttribute('aria-disabled') === 'true',
      'Manual refresh reports busy without disabling keyboard focus');
    await page.locator('#amino-orbit-panel .amino-orbit__refresh-now').evaluate((button) => button.click());
    await page.locator('#amino-orbit-panel .amino-orbit__search').focus();
    check((await deferredResponse).status() === 200, 'Deferred refresh request completes normally');
    await page.waitForFunction(() =>
      document.querySelector('.amino-orbit__refresh-feedback')?.textContent?.includes('Update postponed while editing')
    );
    check(guardedRefreshRequests === 1 && await deferredPanel.evaluate((node) =>
      node.isConnected && node.contains(document.activeElement)
    ), 'Duplicate request suppressed, focused input preserved and successful deferred response labeled accurately');
    await page.waitForFunction(() =>
      !document.querySelector('.amino-orbit__refresh-now')?.hasAttribute('aria-busy')
    );
    await page.unroute('**/wp-admin/admin-ajax.php');
    await page.evaluate(() => document.activeElement.blur());

    // RC3: Escape clears only the current search, not the other local facets.
    await page.locator('#amino-orbit-panel .amino-orbit__filter[data-orbit-status="active"]').click();
    const shortcutSearch = page.locator('#amino-orbit-panel .amino-orbit__search');
    check(await shortcutSearch.getAttribute('aria-keyshortcuts') === 'Escape' &&
      await shortcutSearch.getAttribute('aria-describedby') === 'session-radar-search-tip',
      'Search shortcut is discoverable through keyboard and assistive-technology metadata');
    await shortcutSearch.fill('nonexistent-session-radar-string');
    check(await page.locator('#amino-orbit-panel .amino-orbit__empty-filter').isVisible(),
      'Search produces no-match state before Escape');
    await shortcutSearch.press('Escape');
    check(await shortcutSearch.inputValue() === '' &&
      await page.locator('#amino-orbit-panel .amino-orbit__empty-filter').isHidden() &&
      await page.locator('#amino-orbit-panel .amino-orbit__filter[data-orbit-status="active"]')
        .getAttribute('aria-pressed') === 'true',
      'Escape restores search results without resetting the selected activity status');
    await page.locator('#amino-orbit-panel .amino-orbit__filter[data-orbit-status="all"]').click();
    await page.evaluate(() => document.activeElement.blur());

    // RC5: the freshness label is local to the rendered widget, not a visit log.
    const freshness = page.locator('#amino-orbit-panel .amino-orbit__last-checked');
    check(await freshness.count() === 1 &&
      (await freshness.textContent()).startsWith('Checked at '),
      'Local last-checked time is shown without extra network or storage');
    const beforeSearch = await freshness.textContent();
    await page.locator('#amino-orbit-panel .amino-orbit__search').fill('search-with-no-match');
    check(await freshness.textContent() === beforeSearch,
      'Filtering locally does not masquerade as a server check');
    await page.locator('#amino-orbit-panel .amino-orbit__search').fill('');
    const checkedRequest = page.waitForResponse((response) =>
      response.url().includes('admin-ajax.php') &&
      (response.request().postData() || '').includes('action=amino_orbit_refresh')
    );
    await page.locator('#amino-orbit-panel .amino-orbit__refresh-now').click();
    check((await checkedRequest).status() === 200, 'Freshness updates after the authorized manual refresh');
    await page.waitForFunction(() =>
      document.querySelector('#amino-orbit-panel .amino-orbit__last-checked')?.textContent?.startsWith('Checked at ')
    );
    await page.evaluate(() => document.activeElement.blur());

    // Focus inside the widget must prevent automatic HTML replacement.
    const oldPanel = await panel.elementHandle();
    const settingsLink = page.locator('#amino-orbit-panel .amino-orbit__footer a:not(.amino-orbit__open-workspace)');
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
    const racingLink = page.locator('#amino-orbit-panel .amino-orbit__footer a:not(.amino-orbit__open-workspace)');
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

    // v2.0: full-width Sessions workspace uses the same authorized,
    // bounded dashboard renderer with a separate menu route.
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.emulateMedia({ forcedColors: 'none', reducedMotion: 'no-preference' });
    await page.goto(URL + '/wp-admin/index.php', { waitUntil: 'domcontentloaded' });
    const fullView = page.locator('#amino-orbit-panel .amino-orbit__open-workspace');
    check(await fullView.count() === 1, 'Dashboard widget links to the dedicated sessions workspace');
    await fullView.click();
    await page.waitForURL(/index\.php\?page=session-radar-sessions/);
    await page.locator('.session-radar-workspace #amino-orbit-panel').waitFor({ state: 'visible' });
    check(await page.locator('.session-radar-workspace #amino-orbit-panel').count() === 1,
      'Dedicated Sessions page embeds exactly one permission-checked session renderer');
    // 2.0 alpha.2: the read-only workspace switches grid/list locally.
    const layoutToggle = page.locator('.session-radar-workspace .amino-orbit__workspace-list-toggle');
    check(await layoutToggle.count() === 1 && await layoutToggle.getAttribute('aria-pressed') === 'false',
      'Full workspace defaults to accessible grid layout');
    await layoutToggle.click();
    check(await layoutToggle.getAttribute('aria-pressed') === 'true' &&
      await page.locator('.session-radar-workspace #amino-orbit-panel')
        .evaluate((panel) => panel.classList.contains('amino-orbit--workspace-list')),
      'Workspace list mode is keyboard/ARIA-operable and changes layout');
    const listColumnCount = await page.locator('.session-radar-workspace .amino-orbit__list')
      .evaluate((node) => getComputedStyle(node).gridTemplateColumns.trim().split(/\\s+/).length);
    check(listColumnCount === 1, 'Workspace list switch produces one CSS grid column');
    const layoutRefresh = page.waitForResponse((response) =>
      response.url().includes('admin-ajax.php') &&
      (response.request().postData() || '').includes('action=amino_orbit_refresh')
    );
    await page.locator('.session-radar-workspace .amino-orbit__refresh-now').click();
    check((await layoutRefresh).status() === 200, 'Workspace refresh remains available in list mode');
    await page.waitForFunction(() =>
      document.querySelector('.session-radar-workspace .amino-orbit__workspace-list-toggle')
        ?.getAttribute('aria-pressed') === 'true'
    );
    check(await page.locator('.session-radar-workspace #amino-orbit-panel')
      .evaluate((panel) => panel.classList.contains('amino-orbit--workspace-list')),
      'List preference survives authorized AJAX panel replacement without persistence');
    await layoutToggle.click();
    check(await layoutToggle.getAttribute('aria-pressed') === 'false',
      'Workspace toggle restores the responsive grid layout');

    check((await page.locator('.session-radar-workspace__intro').innerText())
      .includes('does not prove that someone is online'),
      'Full workspace does not describe login sessions as live presence');
    const workspaceConfig = await page.evaluate(() => window.aminoOrbitConfig);
    check([true, 1, '1'].includes(workspaceConfig.canView) && workspaceConfig.ajaxUrl.includes('admin-ajax.php'),
      'Dedicated Sessions page enqueues authorized AJAX config');
    await page.locator('.session-radar-workspace .amino-orbit__search').fill('nonexistent-workspace-user');
    check(await page.locator('.session-radar-workspace .amino-orbit__empty-filter').isVisible(),
      'Full workspace filters rendered sessions without a new endpoint');
    await page.locator('.session-radar-workspace .amino-orbit__search').fill('');
    const fullRefresh = page.waitForResponse((response) =>
      response.url().includes('admin-ajax.php') &&
      (response.request().postData() || '').includes('action=amino_orbit_refresh')
    );
    await page.locator('.session-radar-workspace .amino-orbit__refresh-now').click();
    check((await fullRefresh).status() === 200, 'Full sessions workspace reuses the guarded AJAX refresh');
    await axeScan(page, '.session-radar-workspace');
    await page.setViewportSize({ width: 375, height: 812 });
    check(await page.locator('.session-radar-workspace #amino-orbit-panel').evaluate((panel) =>
      panel.scrollWidth <= panel.clientWidth + 2
    ), 'Full-width workspace reflows to a narrow mobile viewport');
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.goto(URL + '/wp-admin/index.php', { waitUntil: 'domcontentloaded' });
    await page.locator('#amino-orbit-panel').waitFor({ state: 'visible' });

    // WordPress-admin accessibility, including accessible names and input labels.
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.emulateMedia({ forcedColors: 'none', reducedMotion: 'no-preference' });
    await page.goto(URL + '/wp-admin/options-general.php?page=session-radar');
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
    const forbiddenWorkspace = await editorPage.goto(URL + '/wp-admin/index.php?page=session-radar-sessions', {
      waitUntil: 'domcontentloaded'
    });
    check(forbiddenWorkspace.status() === 403 &&
      await editorPage.locator('#amino-orbit-panel').count() === 0,
      'Unauthorized editor cannot load the dedicated Sessions page or session rows');
    await editorPage.goto(URL + '/wp-admin/index.php', { waitUntil: 'domcontentloaded' });
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
    await editorPage.goto(URL + '/wp-admin/index.php?page=session-radar-sessions', {
      waitUntil: 'domcontentloaded'
    });
    check(await editorPage.locator('.session-radar-workspace #amino-orbit-panel').count() === 1,
      'Explicitly authorized editor can use the full Sessions page');
    check(!(await editorPage.locator('.session-radar-workspace #amino-orbit-panel').innerText())
      .includes('192.0.2.'), 'Read-only editor cannot reveal WordPress session IPs in full view');
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
      await page.goto(URL + '/wp-admin/options-general.php?page=session-radar', {
        waitUntil: 'domcontentloaded'
      });
      await page.locator('.amino-orbit-settings').waitFor({ state: 'visible' });
      check((await page.locator('.amino-orbit-settings').innerText()).includes(locale.heading),
        'WordPress settings page renders ' + locale.mode + ' locale in the real browser');
    }

    // The fixture invokes the actual WordPress options-update hook. It fails
    // if monitoring was previously active and existing activity is not purged.
    fixture('monitoring-off');
    await page.goto(URL + '/wp-admin/index.php', { waitUntil: 'domcontentloaded' });
    await page.locator('#amino-orbit-panel').waitFor({ state: 'visible' });
    const disabledConfig = await page.evaluate(() => window.aminoOrbitConfig);
    check(![true, 1, '1'].includes(disabledConfig.trackActivity),
      'Disabling monitoring removes all front-end activity beacons');
    const disabledHtml = await page.locator('#amino-orbit-panel').innerHTML();
    check(!disabledHtml.includes('amino-orbit__status--active') &&
      !disabledHtml.includes('amino-orbit__status--idle'),
      'Previously recorded activity statuses are hidden immediately');
    const disabledPing = await page.evaluate(async () => {
      const config = window.aminoOrbitConfig;
      const response = await fetch(config.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'amino_orbit_ping', nonce: config.nonce })
      });
      return { status: response.status, payload: await response.json() };
    });
    check(disabledPing.status === 200 && disabledPing.payload.success === true &&
      disabledPing.payload.data?.tracked === false,
      'Valid authenticated pings cannot write activity after opt-out');
    fixture('monitoring-on');

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
