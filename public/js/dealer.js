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
      list.replaceChildren();
      if (!payload.items || !payload.items.length) {
        const empty = document.createElement('p');
        empty.className = 'meta';
        empty.textContent = 'No unread notifications.';
        list.appendChild(empty);
        return;
      }
      payload.items.forEach((item) => {
        const id = Number(item.id);
        if (!Number.isInteger(id) || id < 1) {
          return;
        }

        const link = document.createElement('a');
        link.className = 'notify-item';
        link.href = `/dealer/notifications/${id}/read`;

        const title = document.createElement('b');
        title.textContent = item.title || '';
        link.appendChild(title);

        if (item.body) {
          const body = document.createElement('span');
          body.textContent = item.body;
          link.appendChild(body);
        }

        const when = document.createElement('small');
        when.textContent = item.created_at || '';
        link.appendChild(when);

        list.appendChild(link);
      });
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

  document.querySelectorAll('[data-booking-create]').forEach((form) => {
    const type = form.querySelector('[data-booking-type]');
    const time = form.querySelector('[data-booking-time]');
    if (!type || !time) return;
    const sync = () => {
      const needsTime = type.value !== 'reservation';
      time.required = needsTime;
    };
    type.addEventListener('change', sync);
    sync();
  });

  document.querySelectorAll('[data-lead-update]').forEach((form) => {
    const status = form.querySelector('[data-lead-status]');
    const notes = form.querySelector('[data-lead-contact-notes]');
    if (!status || !notes) return;
    const hasNotes = form.dataset.hasContactNotes === '1';
    const sync = () => {
      notes.required = status.value === 'contacted' && !hasNotes;
    };
    status.addEventListener('change', sync);
    sync();
  });

  document.querySelector('.dealer-nav a.selected')?.scrollIntoView({
    inline: 'center',
    block: 'nearest',
    behavior: 'smooth',
  });
})();
