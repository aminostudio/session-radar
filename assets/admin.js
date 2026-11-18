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
  let selectedRole = '';
  let searchQuery = '';
  let searchInput = '';
  let compactView = false;
  let autoRefreshPaused = false;
  let lastRenderedAt = Date.now();
  const statuses = ['all', 'active', 'idle', 'session'];
  const sortModes = ['suggested', 'newest', 'oldest'];
  const sortedLists = new WeakMap();
  const normalizeSearch = (value) => String(value || '').normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase().trim();

  function applyFilter(panel) {
    if (!panel) return;
    const roleField = panel.querySelector('.amino-orbit__role-filter');
    // Never keep a stale role selection after refreshed data removes that role.
    if (roleField) {
      if (![...roleField.options].some((option) => option.value === selectedRole)) selectedRole = '';
      roleField.value = selectedRole;
    }
    const list = panel.querySelector('.amino-orbit__list');
    const rows = [...panel.querySelectorAll('.amino-orbit__user[data-orbit-status]')];
    // Reorder DOM only when sorting changes or when AJAX replaces the list.
    // Typing a search term must not detach/reappend the same 40 rows each time.
    if (list && sortedLists.get(list) !== selectedSort) {
      rows.sort((a, b) => {
        const initial = Number(a.dataset.orbitOrder) - Number(b.dataset.orbitOrder);
        if (selectedSort === 'suggested') return initial;
        const delta = Number(b.dataset.orbitLogin) - Number(a.dataset.orbitLogin);
        return (selectedSort === 'newest' ? delta : -delta) || initial;
      });
      rows.forEach((row) => list.appendChild(row));
      sortedLists.set(list, selectedSort);
    }
    let shown = 0;
    rows.forEach((row) => {
      // Deliberately exclude native session IPs and any hidden/internal metadata.
      const terms = ['.amino-orbit__identity strong', '.amino-orbit__identity small', '.amino-orbit__device']
        .map((selector) => row.querySelector(selector)?.textContent || '').join(' ');
      const matchesRole = !selectedRole ||
        (row.dataset.orbitRoles || '').split(/\s+/).includes(selectedRole);
      const visible = matchesRole &&
        (selectedStatus === 'all' || row.dataset.orbitStatus === selectedStatus) &&
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
    if (count) {
      const template = config.labels?.visibleCount || 'Showing %1$s of %2$s displayed sessions';
      count.textContent = template.replace('%1$s', String(shown)).replace('%2$s', String(rows.length));
    }
    const checkedAt = panel.querySelector('.amino-orbit__last-checked');
    if (checkedAt) {
      const stamp = new Date(lastRenderedAt).toLocaleTimeString(undefined, {
        hour: '2-digit', minute: '2-digit'
      });
      checkedAt.textContent = (config.labels?.checkedAt || 'Checked at %s').replace('%s', stamp);
    }
    const empty = panel.querySelector('.amino-orbit__empty-filter');
    if (empty) empty.hidden = shown !== 0 || rows.length === 0;
    panel.classList.toggle('amino-orbit--compact', compactView);
    const density = panel.querySelector('.amino-orbit__compact-toggle');
    if (density) density.setAttribute('aria-pressed', String(compactView));
    // The pause choice belongs to this browser tab only. Neither localStorage
    // nor a WordPress option records it; manual refresh remains available.
    const pause = panel.querySelector('.amino-orbit__pause-refresh');
    if (pause) {
      pause.setAttribute('aria-pressed', String(autoRefreshPaused));
      pause.textContent = autoRefreshPaused
        ? (config.labels?.resumeRefresh || 'Resume automatic refresh')
        : (config.labels?.pauseRefresh || 'Pause automatic refresh');
    }
    const live = panel.querySelector('.amino-orbit__live');
    if (live) {
      const message = autoRefreshPaused
        ? (config.labels?.paused || 'Paused')
        : (config.labels?.auto || 'Auto');
      const liveLabel = autoRefreshPaused
        ? (config.labels?.pauseDescription || 'Automatic refresh paused')
        : (config.labels?.autoDescription || 'Automatic refresh, not real-time presence');
      const text = live.querySelector('.amino-orbit__live-label');
      if (text) text.textContent = message;
      live.setAttribute('aria-label', liveLabel);
      live.setAttribute('title', liveLabel);
      live.classList.toggle('amino-orbit__live--paused', autoRefreshPaused);
    }
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
  // Search-only Escape reset. Do not reset role, status, sort or display mode.
  // Explicitly handle it because native type=search Escape behavior varies.
  shell.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape' || !event.target.matches('.amino-orbit__search') || !event.target.value) return;
    event.preventDefault();
    event.target.value = '';
    searchInput = '';
    searchQuery = '';
    applyFilter(document.getElementById('amino-orbit-panel'));
  });
  shell.addEventListener('change', (event) => {
    if (event.target.matches('.amino-orbit__role-filter')) {
      selectedRole = event.target.value;
      applyFilter(document.getElementById('amino-orbit-panel'));
      return;
    }
    if (!event.target.matches('.amino-orbit__sort') || !sortModes.includes(event.target.value)) return;
    selectedSort = event.target.value;
    applyFilter(document.getElementById('amino-orbit-panel'));
  });
  // v0.9: reset the existing local controls without losing the dashboard focus.
  shell.addEventListener('click', (event) => {
    if (event.target.closest('.amino-orbit__reset')) {
      selectedStatus = 'all';
      selectedSort = 'suggested';
      selectedRole = '';
      searchQuery = '';
      searchInput = '';
      const field = document.querySelector('#amino-orbit-panel .amino-orbit__search');
      if (field) field.value = '';
      applyFilter(document.getElementById('amino-orbit-panel'));
    } else if (event.target.closest('.amino-orbit__compact-toggle')) {
      compactView = !compactView;
      applyFilter(document.getElementById('amino-orbit-panel'));
    }
  });
  applyFilter(document.getElementById('amino-orbit-panel'));

  function refresh(manual = false) {
    if (refreshing || (!manual && (autoRefreshPaused || document.visibilityState !== 'visible'))) return;
    const current = document.getElementById('amino-orbit-panel');
    if (!current) return;
    // Background refresh never replaces focused controls, inputs or links.
    if (!manual && current.contains(document.activeElement)) return;
    const feedback = current.querySelector('.amino-orbit__refresh-feedback');
    const labels = config.labels || {};
    const announce = (panel, message) => {
      const target = panel.querySelector('.amino-orbit__refresh-feedback');
      if (target) target.textContent = message;
    };
    const manualButton = manual ? current.querySelector('.amino-orbit__refresh-now') : null;
    // Keep the button focusable while communicating its busy state.
    // Disabled native buttons may discard focus before AJAX DOM replacement.
    if (manualButton) {
      manualButton.setAttribute('aria-disabled', 'true');
      manualButton.setAttribute('aria-busy', 'true');
    }
    if (manual && feedback) announce(current, labels.refreshing || 'Updating…');
    refreshing = true;
    post('amino_orbit_refresh')
      .then((result) => {
        if (!result.success || !result.data || typeof result.data.html !== 'string') {
          if (manual && current.isConnected) announce(current, labels.failed || 'Refresh failed');
          return;
        }
        // Pausing an *already-running* automatic request must still win.
        // Discard its returned markup without resetting filters, replacing the
        // focused DOM, or incorrectly advancing the last-checked timestamp.
        if (!manual && autoRefreshPaused) return;
        const template = document.createElement('template');
        template.innerHTML = result.data.html.trim();
        const next = template.content.querySelector('#amino-orbit-panel');
        const focused = current.contains(document.activeElement);
        const canRestore = manual && focused &&
          document.activeElement.matches('.amino-orbit__refresh-now');
        if (next && current.isConnected && (!focused || canRestore)) {
          current.replaceWith(next);
          lastRenderedAt = Date.now();
          const input = next.querySelector('.amino-orbit__search');
          if (input) input.value = searchInput;
          applyFilter(next);
          if (manual) {
            announce(next, labels.updated || 'Updated');
            if (canRestore) next.querySelector('.amino-orbit__refresh-now')?.focus();
          }
        } else if (manual && current.isConnected) {
          // An editor changed focus while the request was in flight.
          // Preserve their work and never misreport a successful fetch as a failure.
          announce(current, labels.skipped || 'Update postponed while editing');
        }
      })
      .catch(() => {
        if (manual && current.isConnected) announce(current, labels.failed || 'Refresh failed');
      })
      .finally(() => {
        refreshing = false;
        if (manualButton && manualButton.isConnected) {
          manualButton.removeAttribute('aria-disabled');
          manualButton.removeAttribute('aria-busy');
        }
      });
  }

  shell.addEventListener('click', (event) => {
    if (event.target.closest('.amino-orbit__pause-refresh')) {
      autoRefreshPaused = !autoRefreshPaused;
      applyFilter(document.getElementById('amino-orbit-panel'));
      return;
    }
    if (!event.target.closest('.amino-orbit__refresh-now')) return;
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
