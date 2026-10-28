(() => {
  const root = document.querySelector('[data-notify-root]');
  if (root) {
    const toggle = root.querySelector('[data-notify-toggle]');
    const panel = root.querySelector('[data-notify-panel]');
    const countEl = root.querySelector('[data-notify-count]');
    const list = root.querySelector('[data-notify-list]');
    const url = root.dataset.notifyUrl;

    const render = (payload) => {
      const count = Number(payload.count || 0);
      countEl.hidden = count < 1;
      countEl.textContent = count > 99 ? '99+' : String(count);
      if (!payload.items || !payload.items.length) {
        list.innerHTML = '<p class="meta">No unread notifications.</p>';
        return;
      }
      list.innerHTML = payload.items.map((item) => `
      <a class="notify-item" href="/dealer/notifications/${item.id}/read">
        <b>${item.title}</b>
        ${item.body ? `<span>${item.body}</span>` : ''}
        <small>${item.created_at || ''}</small>
      </a>
    `).join('');
    };

    const refresh = () => fetch(url, { headers: { Accept: 'application/json' } })
      .then((r) => r.json())
      .then(render)
      .catch(() => {});

    toggle?.addEventListener('click', () => {
      const open = panel.hasAttribute('hidden');
      panel.hidden = !open;
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open) refresh();
    });

    document.addEventListener('click', (e) => {
      if (!root.contains(e.target)) {
        panel.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
      }
    });

    refresh();
    setInterval(refresh, 30000);
  }

  document.querySelectorAll('[data-staff-perms]').forEach((section) => {
    let presets = {};
    try {
      presets = JSON.parse(section.dataset.presets || '{}');
    } catch (_) {
      presets = {};
    }

    const select = section.querySelector('[data-role-preset]');
    const grid = section.querySelector('[data-perm-grid]');
    const adminNote = section.querySelector('[data-admin-note]');
    if (!select || !grid) return;

    const applyRole = () => {
      const role = select.value;
      const isAdmin = role === 'admin';
      grid.style.display = isAdmin ? 'none' : '';
      if (adminNote) {
        adminNote.style.display = isAdmin ? '' : 'none';
      }
      if (isAdmin) {
        return;
      }
      const chosen = Array.isArray(presets[role]) ? presets[role] : [];
      grid.querySelectorAll('input[type="checkbox"][name="permissions[]"]').forEach((box) => {
        box.checked = chosen.includes(box.value);
      });
    };

    select.addEventListener('change', applyRole);
  });

  document.querySelector('.dealer-nav a.selected')?.scrollIntoView({
    inline: 'center',
    block: 'nearest',
    behavior: 'smooth',
  });
})();
