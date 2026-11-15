/** Amino Orbit: minimal activity beacon and accessible widget refresh. */
(() => {
  'use strict';
  const config = window.aminoOrbitConfig;
  if (!config || !config.ajaxUrl || !config.nonce) return;

  let lastInteraction = Date.now();
  let lastReported = 0;
  let sending = false;

  function post(action) {
    const body = new URLSearchParams({ action, nonce: config.nonce });
    return fetch(config.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString(),
      cache: 'no-store'
    }).then((response) => {
      if (!response.ok) throw new Error('Amino Orbit request failed');
      return response.json();
    });
  }

  const trackActivity = config.trackActivity === true || config.trackActivity === 1 || config.trackActivity === '1';
  const markInteraction = () => { lastInteraction = Date.now(); };
  if (trackActivity) {
    ['pointerdown', 'keydown', 'scroll', 'touchstart'].forEach((name) => {
      document.addEventListener(name, markInteraction, { passive: true });
    });
    window.addEventListener('focus', markInteraction, { passive: true });
  }

  function ping() {
    if (sending || document.visibilityState !== 'visible') return;
    // Avoid turning a forgotten browser tab into a false "active" presence.
    if (lastInteraction <= lastReported || Date.now() - lastInteraction > 5 * 60 * 1000) return;
    sending = true;
    const interactionAtSend = lastInteraction;
    post('amino_orbit_ping')
      .then((result) => { if (result.success) lastReported = interactionAtSend; })
      .catch(() => { /* Non-critical telemetry: fail silently. */ })
      .finally(() => { sending = false; });
  }

  if (trackActivity) {
    ping();
    window.setInterval(ping, 60 * 1000);
  }

  if (!config.canView) return;
  const shell = document.getElementById('amino_orbit_widget');
  if (!shell) return;
  let refreshing = false;
  // Only already-authorized, rendered rows are filtered and sorted locally.
  // None of these UI choices generate tracking, persistence or extra requests.
  let selectedStatus = 'all';
  let selectedSort = 'suggested';
  let searchQuery = '';
  let searchInput = '';
  const statuses = ['all', 'active', 'idle', 'session'];
  const sortModes = ['suggested', 'newest', 'oldest'];
  const normalizeSearch = (value) => String(value || '').normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase().trim();

  function applyFilter(panel) {
    if (!panel) return;
    const list = panel.querySelector('.amino-orbit__list');
    const rows = [...panel.querySelectorAll('.amino-orbit__user[data-orbit-status]')];
    // The initial server order is stable and can always be restored.
    rows.sort((a, b) => {
      const initial = Number(a.dataset.orbitOrder) - Number(b.dataset.orbitOrder);
      if (selectedSort === 'suggested') return initial;
      const delta = Number(b.dataset.orbitLogin) - Number(a.dataset.orbitLogin);
      return (selectedSort === 'newest' ? delta : -delta) || initial;
    });
    if (list) rows.forEach((row) => list.appendChild(row));
    let shown = 0;
    rows.forEach((row) => {
      // Deliberately exclude native session IPs and any hidden/internal metadata.
      const terms = ['.amino-orbit__identity strong', '.amino-orbit__identity small', '.amino-orbit__device']
        .map((selector) => row.querySelector(selector)?.textContent || '').join(' ');
      const visible = (selectedStatus === 'all' || row.dataset.orbitStatus === selectedStatus) &&
        normalizeSearch(terms).includes(searchQuery);
      row.hidden = !visible;
      if (visible) shown += 1;
    });
    panel.querySelectorAll('.amino-orbit__filter[data-orbit-status]').forEach((button) => {
      button.setAttribute('aria-pressed', String(button.dataset.orbitStatus === selectedStatus));
    });
    const sort = panel.querySelector('.amino-orbit__sort');
    if (sort) sort.value = selectedSort;
    const count = panel.querySelector('.amino-orbit__filter-count');
    if (count) count.textContent = shown + ' / ' + rows.length;
  }

  shell.addEventListener('click', (event) => {
    const button = event.target.closest('.amino-orbit__filter[data-orbit-status]');
    if (!button || !shell.contains(button) || !statuses.includes(button.dataset.orbitStatus)) return;
    selectedStatus = button.dataset.orbitStatus;
    applyFilter(document.getElementById('amino-orbit-panel'));
  });
  // This listener must not be nested under a filter click: search works immediately.
  shell.addEventListener('input', (event) => {
    if (!event.target.matches('.amino-orbit__search')) return;
    searchInput = event.target.value;
    searchQuery = normalizeSearch(searchInput);
    applyFilter(document.getElementById('amino-orbit-panel'));
  });
  shell.addEventListener('change', (event) => {
    if (!event.target.matches('.amino-orbit__sort') || !sortModes.includes(event.target.value)) return;
    selectedSort = event.target.value;
    applyFilter(document.getElementById('amino-orbit-panel'));
  });
  applyFilter(document.getElementById('amino-orbit-panel'));

  function refresh() {
    if (refreshing || document.visibilityState !== 'visible') return;
    const current = document.getElementById('amino-orbit-panel');
    if (!current) return;
    // Preserve keyboard focus: replacing a focused Settings link would move focus.
    if (current.contains(document.activeElement)) return;
    refreshing = true;
    post('amino_orbit_refresh')
      .then((result) => {
        if (!result.success || !result.data || typeof result.data.html !== 'string') return;
        // Safe HTML is generated by a server callback enforcing the viewer permission.
        const template = document.createElement('template');
        template.innerHTML = result.data.html.trim();
        const next = template.content.querySelector('#amino-orbit-panel');
        // Focus may move into the panel after the request starts: recheck now.
        if (next && current.isConnected && !current.contains(document.activeElement)) {
          current.replaceWith(next);
          const input = next.querySelector('.amino-orbit__search');
          if (input) input.value = searchInput;
          applyFilter(next);
        }
      })
      .catch(() => { /* Keep the previous panel when offline. */ })
      .finally(() => { refreshing = false; });
  }
  // Refresh promptly when returning to a backgrounded tab, without polling hidden tabs.
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
      if (trackActivity) ping();
      refresh();
    }
  });
  window.setInterval(refresh, Math.max(30000, Number(config.refreshMs) || 60000));
})();
