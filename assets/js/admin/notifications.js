/**
 * SmartOps Source: assets/js/admin/notifications.js
 * Purpose: Js / Admin: Notifications client-side interactions and dynamic UI behaviour.
 * Developer Guide: named functions are separated into searchable sections.
 */

(() => {
  'use strict';

  const root = document.querySelector('[data-admin-notifications]');
  if (!root) return;

  const button = root.querySelector('[data-notification-toggle]');
  const dropdown = root.querySelector('[data-notification-dropdown]');
  const list = root.querySelector('[data-notification-list]');
  const badge = root.querySelector('[data-notification-count]');
  const unreadLabel = root.querySelector('[data-notification-unread-label]');
  const endpoint = root.dataset.endpoint;
  let lastUnread = Number((badge?.textContent || '0').replace(/\D/g, '') || 0);

  const escapeHtml = value => String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;');

  // ---------------------------------------------------------------------------
  // SECTION: Set Open
  // ---------------------------------------------------------------------------
  const setOpen = open => {
    dropdown?.classList.toggle('is-open', open);
    button?.setAttribute('aria-expanded', open ? 'true' : 'false');
  };

  // ---------------------------------------------------------------------------
  // SECTION: Render
  // ---------------------------------------------------------------------------
  const render = payload => {
    const unread = Number(payload.unread_count || 0);
    if (badge) {
      badge.textContent = unread > 99 ? '99+' : String(unread);
      badge.classList.toggle('is-hidden', unread <= 0);
    }
    if (button) button.classList.toggle('has-unread', unread > 0);
    if (unreadLabel) unreadLabel.textContent = `${unread} unread`;

    const notifications = Array.isArray(payload.notifications) ? payload.notifications : [];
    if (list) {
      if (!notifications.length) {
        list.innerHTML = '<div class="admin-notification-empty">No completion notifications yet.</div>';
      } else {
        list.innerHTML = notifications.map(item => `
          <a class="admin-notification-item${item.unread ? ' is-unread' : ''}" href="${escapeHtml(item.open_url)}">
            <span class="admin-notification-icon" aria-hidden="true">✓</span>
            <span class="admin-notification-copy">
              <strong>${escapeHtml(item.title)}</strong>
              <span>${escapeHtml(item.message)}</span>
              <small>${escapeHtml(item.created_at)}</small>
            </span>
            ${item.unread ? '<span class="admin-notification-new">New</span>' : ''}
          </a>
        `).join('');
      }
    }

    // Briefly emphasize the bell when a technician completes a new case while
    // the manager is already on an Admin dashboard.
    if (unread > lastUnread && button) {
      button.animate(
        [
          { transform: 'scale(1)', background: '#fff' },
          { transform: 'scale(1.10)', background: '#eaf3ff' },
          { transform: 'scale(1)', background: '#fff' }
        ],
        { duration: 650, easing: 'ease-out' }
      );
    }
    lastUnread = unread;
  };

  // ---------------------------------------------------------------------------
  // SECTION: Refresh
  // ---------------------------------------------------------------------------
  const refresh = async () => {
    if (!endpoint) return;
    try {
      const response = await fetch(endpoint, { cache: 'no-store', headers: { Accept: 'application/json' } });
      if (!response.ok) return;
      const payload = await response.json();
      if (payload && payload.success) render(payload);
    } catch (error) {
      // Keep the Admin UI usable if polling is temporarily unavailable.
    }
  };

  button?.addEventListener('click', event => {
    event.stopPropagation();
    setOpen(!dropdown?.classList.contains('is-open'));
    refresh();
  });

  dropdown?.addEventListener('click', event => event.stopPropagation());
  // ---------------------------------------------------------------------------
  // SECTION: Page Initialization
  // ---------------------------------------------------------------------------
  document.addEventListener('click', () => setOpen(false));
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') setOpen(false);
  });

  refresh();
  window.setInterval(refresh, 5000);
})();
