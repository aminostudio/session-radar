/** Sessivue: minimal activity beacon and accessible widget refresh. */
(() => {
  'use strict';
  const config = window.sessivueConfig;
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
      if (!response.ok) throw new Error('Sessivue request failed');
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
    post('sessivue_ping')
      .then((result) => { if (result.success) lastReported = interactionAtSend; })
      .catch(() => { /* Non-critical telemetry: fail silently. */ })
      .finally(() => { sending = false; });
  }

  if (trackActivity) {
    ping();
    window.setInterval(ping, 60 * 1000);
  }

  if (!config.canView) return;
  const shell = document.getElementById('sessivue_widget');
  if (!shell) return;
  let refreshing = false;
  // Only already-authorized, rendered rows are filtered and sorted locally.
  // None of these UI choices generate tracking, persistence or extra requests.
  let selectedStatus = 'all';
  let selectedSort = 'suggested';
  let selectedRole = '';
  let searchQuery = '';
  let searchInput = '';
  let compactView = false;
  const statuses = ['all', 'active', 'idle', 'session'];
  const sortModes = ['suggested', 'newest', 'oldest'];
  const normalizeSearch = (value) => String(value || '').normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase().trim();

  function applyFilter(panel) {
    if (!panel) return;
    const roleField = panel.querySelector('.sessivue__role-filter');
    // Never keep a stale role selection after refreshed data removes that role.
    if (roleField) {
      if (![...roleField.options].some((option) => option.value === selectedRole)) selectedRole = '';
      roleField.value = selectedRole;
    }
    const list = panel.querySelector('.sessivue__list');
    const rows = [...panel.querySelectorAll('.sessivue__user[data-sessivue-status]')];
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
      const terms = ['.sessivue__identity strong', '.sessivue__identity small', '.sessivue__device']
        .map((selector) => row.querySelector(selector)?.textContent || '').join(' ');
      const matchesRole = !selectedRole ||
        (row.dataset.orbitRoles || '').split(/\s+/).includes(selectedRole);
      const visible = matchesRole &&
        (selectedStatus === 'all' || row.dataset.orbitStatus === selectedStatus) &&
        normalizeSearch(terms).includes(searchQuery);
      row.hidden = !visible;
      if (visible) shown += 1;
    });
    panel.querySelectorAll('.sessivue__filter[data-sessivue-status]').forEach((button) => {
      button.setAttribute('aria-pressed', String(button.dataset.orbitStatus === selectedStatus));
    });
    const sort = panel.querySelector('.sessivue__sort');
    if (sort) sort.value = selectedSort;
    const count = panel.querySelector('.sessivue__filter-count');
    if (count) count.textContent = shown + ' / ' + rows.length;
    const empty = panel.querySelector('.sessivue__empty-filter');
    if (empty) empty.hidden = shown !== 0 || rows.length === 0;
    panel.classList.toggle('sessivue--compact', compactView);
    const density = panel.querySelector('.sessivue__compact-toggle');
    if (density) density.setAttribute('aria-pressed', String(compactView));
  }

  shell.addEventListener('click', (event) => {
    const button = event.target.closest('.sessivue__filter[data-sessivue-status]');
    if (!button || !shell.contains(button) || !statuses.includes(button.dataset.orbitStatus)) return;
    selectedStatus = button.dataset.orbitStatus;
    applyFilter(document.getElementById('sessivue-panel'));
  });
  // This listener must not be nested under a filter click: search works immediately.
  shell.addEventListener('input', (event) => {
    if (!event.target.matches('.sessivue__search')) return;
    searchInput = event.target.value;
    searchQuery = normalizeSearch(searchInput);
    applyFilter(document.getElementById('sessivue-panel'));
  });
  shell.addEventListener('change', (event) => {
    if (event.target.matches('.sessivue__role-filter')) {
      selectedRole = event.target.value;
      applyFilter(document.getElementById('sessivue-panel'));
      return;
    }
    if (!event.target.matches('.sessivue__sort') || !sortModes.includes(event.target.value)) return;
    selectedSort = event.target.value;
    applyFilter(document.getElementById('sessivue-panel'));
  });
  // v0.9: reset the existing local controls without losing the dashboard focus.
  shell.addEventListener('click', (event) => {
    if (event.target.closest('.sessivue__reset')) {
      selectedStatus = 'all';
      selectedSort = 'suggested';
      selectedRole = '';
      searchQuery = '';
      searchInput = '';
      const field = document.querySelector('#sessivue-panel .sessivue__search');
      if (field) field.value = '';
      applyFilter(document.getElementById('sessivue-panel'));
    } else if (event.target.closest('.sessivue__compact-toggle')) {
      compactView = !compactView;
      applyFilter(document.getElementById('sessivue-panel'));
    }
  });
  applyFilter(document.getElementById('sessivue-panel'));

  function refresh(manual = false) {
    if (refreshing || (!manual && document.visibilityState !== 'visible')) return;
    const current = document.getElementById('sessivue-panel');
    if (!current) return;
    // Background refresh never replaces focused controls, inputs or links.
    if (!manual && current.contains(document.activeElement)) return;
    const feedback = current.querySelector('.sessivue__refresh-feedback');
    const labels = config.labels || {};
    const announce = (panel, message) => {
      const target = panel.querySelector('.sessivue__refresh-feedback');
      if (target) target.textContent = message;
    };
    if (manual && feedback) announce(current, labels.refreshing || 'Updating…');
    refreshing = true;
    post('sessivue_refresh')
      .then((result) => {
        if (!result.success || !result.data || typeof result.data.html !== 'string') {
          if (manual && current.isConnected) announce(current, labels.failed || 'Refresh failed');
          return;
        }
        const template = document.createElement('template');
        template.innerHTML = result.data.html.trim();
        const next = template.content.querySelector('#sessivue-panel');
        const focused = current.contains(document.activeElement);
        const canRestore = manual && focused &&
          document.activeElement.matches('.sessivue__refresh-now');
        if (next && current.isConnected && (!focused || canRestore)) {
          current.replaceWith(next);
          const input = next.querySelector('.sessivue__search');
          if (input) input.value = searchInput;
          applyFilter(next);
          if (manual) {
            announce(next, labels.updated || 'Updated');
            if (canRestore) next.querySelector('.sessivue__refresh-now')?.focus();
          }
        } else if (manual && current.isConnected) {
          announce(current, labels.failed || 'Refresh failed');
        }
      })
      .catch(() => {
        if (manual && current.isConnected) announce(current, labels.failed || 'Refresh failed');
      })
      .finally(() => { refreshing = false; });
  }

  shell.addEventListener('click', (event) => {
    if (!event.target.closest('.sessivue__refresh-now')) return;
    refresh(true);
  });
  // Refresh promptly when returning to a backgrounded tab, without polling hidden tabs.
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
      if (trackActivity) ping();
      refresh();
    }
  });
  window.setInterval(refresh, Math.max(30000, Number(config.refreshMs) || 60000));
})();
