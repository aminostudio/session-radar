/**
 * Eight distinct, fully authenticated WordPress administrators issue AJAX
 * refreshes in parallel on a disposable local CI server with eight PHP workers.
 *
 * Two synchronized request waves begin approximately 30 seconds apart.
 * This tests a bounded burst of eight independent users, NOT an internet-scale
 * performance benchmark or the browser's natural 30-second timer.
 */
'use strict';

const assert = require('node:assert/strict');
const { performance } = require('node:perf_hooks');
const { chromium } = require('playwright');

const ORIGIN = 'http://127.0.0.1:8080';
const USERS = 8;
const POLL_MS = 30000;
const PASSWORD = 'temporary-ci-load-password-not-reused';

function check(ok, message) {
  assert.ok(ok, message);
  console.log('PASS: ' + message);
}

function quantile(sorted, p) {
  return sorted[Math.max(0, Math.ceil(sorted.length * p) - 1)];
}

async function authenticate(browser, index) {
  const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
  const page = await context.newPage();
  const login = 'orbit_load_admin_' + String(index).padStart(2, '0');
  try {
    await page.goto(ORIGIN + '/wp-login.php', { waitUntil: 'domcontentloaded' });
    await page.locator('#user_login').fill(login);
    await page.locator('#user_pass').fill(PASSWORD);
    await Promise.all([
      page.waitForURL(/\/wp-admin(?:\/|\/index\.php)/, { timeout: 15000 }),
      page.locator('#wp-submit').click()
    ]);
    await page.locator('#amino-orbit-panel').waitFor({ state: 'visible', timeout: 15000 });
    const config = await page.evaluate(() => window.aminoOrbitConfig);
    check(Number(config?.refreshMs) === POLL_MS, login + ': configured 30-second poll');
    check(typeof config?.nonce === 'string' && !!config.nonce,
      login + ': WordPress supplies session-specific nonce');
    const cookies = await context.cookies();
    const authCookie = cookies.find((cookie) => cookie.name.startsWith('wordpress_logged_in_'));
    check(!!authCookie?.value, login + ': has genuine WordPress authenticated cookie');
    // Never print cookie or nonce values. The caller uses the API request
    // context, which shares WordPress cookies with this browser context.
    return { login, context, page, nonce: config.nonce, cookie: authCookie.value };
  } catch (error) {
    await context.close();
    throw error;
  }
}

async function requestWave(clients, waveNumber) {
  const start = performance.now();
  const results = await Promise.all(clients.map(async (client) => {
    const begin = performance.now();
    const response = await client.context.request.post(
      ORIGIN + '/wp-admin/admin-ajax.php',
      {
        form: { action: 'amino_orbit_refresh', nonce: client.nonce },
        timeout: 25000
      }
    );
    const elapsed = performance.now() - begin;
    const payload = await response.json();
    check(response.status() === 200 && payload?.success === true &&
      typeof payload.data?.html === 'string' &&
      payload.data.html.includes('id="amino-orbit-panel"'),
    client.login + ': wave ' + waveNumber + ' returned valid authorized widget');
    check(!payload.data.html.includes('192.0.2.'),
      client.login + ': wave ' + waveNumber + ' did not expose an IP address');
    return {
      login: client.login,
      ms: Math.round(elapsed * 1000) / 1000,
      bytes: Buffer.byteLength(payload.data.html, 'utf8')
    };
  }));

  const times = results.map((row) => row.ms).sort((a, b) => a - b);
  const total = Math.round((performance.now() - start) * 1000) / 1000;
  console.log('LOAD WAVE ' + waveNumber + ': ' + JSON.stringify({
    clients: clients.length,
    status: '8/8 successful',
    p50_ms: quantile(times, 0.50),
    p95_ms: quantile(times, 0.95),
    max_ms: Math.max(...times),
    batch_wall_ms: total,
    total_html_bytes: results.reduce((sum, row) => sum + row.bytes, 0)
  }));
  return total;
}

async function main() {
  const browser = await chromium.launch({ headless: true });
  const clients = [];
  try {
    // Distinct admin accounts ensure that each poll has an independent
    // WordPress login token, nonce, capability check and presence record.
    for (let i = 1; i <= USERS; i++) {
      clients.push(await authenticate(browser, i));
    }
    check(new Set(clients.map((client) => client.cookie)).size === USERS,
      'All eight administrators have distinct genuine WordPress auth cookies');
    const start = performance.now();
    await requestWave(clients, 1);
    const elapsed = performance.now() - start;
    if (elapsed < POLL_MS) {
      await new Promise((resolve) => setTimeout(resolve, POLL_MS - elapsed));
    }
    const delta = Math.round(performance.now() - start);
    console.log('LOAD CADENCE: wave 2 starts ' + delta +
      ' ms after wave 1 (target 30000 ms)');
    check(delta >= POLL_MS - 10, 'Two request waves are separated by approximately 30 seconds');
    await requestWave(clients, 2);
    console.log('SUCCESS: Eight independent WordPress admins passed both concurrent AJAX waves.');
  } finally {
    await Promise.allSettled(clients.map(async (client) => client.context.close()));
    await browser.close();
  }
}

main().catch((error) => {
  console.error('LOAD FAILURE:', error.stack || String(error));
  process.exitCode = 1;
});
