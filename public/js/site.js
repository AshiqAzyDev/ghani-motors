document.documentElement.classList.add('js');

const menu = document.querySelector('[data-menu]');
const menuButton = document.querySelector('[data-menu-button]');
const setMenuOpen = (open) => {
  if (!menu || !menuButton) return;
  menu.classList.toggle('open', open);
  menuButton.setAttribute('aria-expanded', open ? 'true' : 'false');
};
menuButton?.addEventListener('click', (event) => {
  event.stopPropagation();
  setMenuOpen(!menu.classList.contains('open'));
});
document.addEventListener('click', (event) => {
  if (!menu?.classList.contains('open')) return;
  if (menu.contains(event.target) || menuButton?.contains(event.target)) return;
  setMenuOpen(false);
});
document.addEventListener('keydown', (event) => {
  if (event.key === 'Escape') setMenuOpen(false);
});

const isAuthenticated = document.body.classList.contains('public-site')
  && !!document.querySelector('header .call-account, header .call-logout, header .nav-logout');
let savedIds = (() => {
  const server = (document.body.dataset.saved || '').split(',').filter(Boolean).map(String);
  if (isAuthenticated) {
    return server;
  }
  const local = JSON.parse(localStorage.getItem('ghani-saved') || '[]').map(String);
  return Array.from(new Set([...local, ...server]));
})();
const syncSavedFields = () => {
  document.body.dataset.saved = savedIds.join(',');
  localStorage.setItem('ghani-saved', JSON.stringify(savedIds));
  document.querySelectorAll('[name=saved_ids]').forEach((field) => { field.value = savedIds.join(','); });
  document.querySelectorAll('.stock-check span').forEach((label) => {
    if (!/saved cars only/i.test(label.textContent || '')) return;
    label.textContent = `Saved cars only (${savedIds.length})`;
  });
};
syncSavedFields();

const paintSaveButton = (button, isSaved) => {
  button.classList.toggle('is-saved', isSaved);
  button.classList.toggle('saved', isSaved);
  if (button.classList.contains('heart') || !button.querySelector('svg')) {
    button.textContent = isSaved ? '♥' : '♡';
  }
  if (button.classList.contains('car-btn')) {
    button.textContent = isSaved ? 'Saved' : 'Save this car';
  }
  const title = button.getAttribute('aria-label') || '';
  if (title.startsWith('Save ') || title.startsWith('Unsave ')) {
    const name = title.replace(/^(Un)?Save\s+/i, '');
    button.setAttribute('aria-label', `${isSaved ? 'Unsave' : 'Save'} ${name}`);
  }
};

const markSaved = (root = document) => {
  root.querySelectorAll('[data-save]').forEach((button) => {
    paintSaveButton(button, savedIds.includes(String(button.dataset.save)));
  });
};

document.addEventListener('submit', (event) => {
  const form = event.target;
  if (!(form instanceof HTMLFormElement)) return;
  const button = form.querySelector('[data-save], .car-btn[type="submit"]');
  const isSaveForm = form.action.includes('/save') || form.querySelector('[data-save]');
  if (!isSaveForm) return;
  event.preventDefault();
  const token = form.querySelector('input[name="_token"]')?.value
    || document.querySelector('meta[name="csrf-token"]')?.content;
  const submitter = button || form.querySelector('button[type="submit"]');
  if (submitter) submitter.disabled = true;
  fetch(form.action, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': token || '',
    },
    body: new FormData(form),
    credentials: 'same-origin',
  })
    .then(async (response) => {
      if (response.status === 401 || response.status === 403 || response.redirected) {
        const login = new URL('/login', window.location.origin);
        login.searchParams.set('redirect', window.location.pathname + window.location.search + window.location.hash);
        window.location.assign(login.toString());
        return null;
      }
      if (!response.ok) throw new Error('Save failed');
      const type = response.headers.get('content-type') || '';
      if (!type.includes('application/json')) {
        const login = new URL('/login', window.location.origin);
        login.searchParams.set('redirect', window.location.pathname + window.location.search + window.location.hash);
        window.location.assign(login.toString());
        return null;
      }
      return response.json();
    })
    .then((payload) => {
      if (!payload) return;
      savedIds = (payload.ids || []).map(String);
      syncSavedFields();
      markSaved(document);
      if (submitter && payload.vehicle_id != null) {
        paintSaveButton(submitter, !!payload.saved);
      }
    })
    .catch(() => {
      const login = new URL('/login', window.location.origin);
      login.searchParams.set('redirect', window.location.pathname + window.location.search + window.location.hash);
      window.location.assign(login.toString());
    })
    .finally(() => {
      if (submitter) submitter.disabled = false;
    });
});

document.addEventListener('click', (event) => {
  if (event.target.closest('[data-enquiry-open]')) {
    document.querySelector('#enquiry')?.showModal();
  }
  if (event.target.closest('[data-enquiry-close]')) {
    document.querySelector('#enquiry')?.close();
  }
});

(() => {
  const dock = document.querySelector('.dock');
  if (!dock) return;

  const labels = () => [...dock.querySelectorAll('.dock-fab-label')];

  const relativeLuminance = (r, g, b) => {
    const channel = (value) => {
      const s = value / 255;
      return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
    };
    return 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b);
  };

  const opaqueBackground = (node) => {
    if (!node || node.nodeType !== 1) return null;
    const style = getComputedStyle(node);
    const color = style.backgroundColor;
    if (!color || color === 'transparent' || color === 'rgba(0, 0, 0, 0)') {
      return null;
    }
    const parts = color.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([0-9.]+))?\)/);
    if (!parts) return null;
    const alpha = parts[4] === undefined ? 1 : Number(parts[4]);
    if (alpha < 0.35) return null;
    return [Number(parts[1]), Number(parts[2]), Number(parts[3])];
  };

  const sampleBackground = (label) => {
    const rect = label.getBoundingClientRect();
    const x = Math.min(window.innerWidth - 1, Math.max(0, rect.left + rect.width / 2));
    const y = Math.min(window.innerHeight - 1, Math.max(0, rect.top + rect.height / 2));
    const hit = document.elementsFromPoint(x, y).find((el) => !el.closest?.('.dock')) || document.body;
    let node = hit;

    while (node && node !== document.documentElement) {
      const rgb = opaqueBackground(node);
      if (rgb) return rgb;
      if (getComputedStyle(node).backgroundImage !== 'none') {
        return [16, 28, 35];
      }
      node = node.parentElement;
    }

    return opaqueBackground(document.body) || [255, 255, 255];
  };

  const syncDockLabelContrast = () => {
    labels().forEach((label) => {
      const [r, g, b] = sampleBackground(label);
      const onDark = relativeLuminance(r, g, b) < 0.45;
      label.classList.toggle('is-on-dark', onDark);
      label.classList.toggle('is-on-light', !onDark);
    });
  };

  let frame = 0;
  const schedule = () => {
    if (frame) return;
    frame = window.requestAnimationFrame(() => {
      frame = 0;
      syncDockLabelContrast();
    });
  };

  syncDockLabelContrast();
  window.addEventListener('scroll', schedule, { passive: true });
  window.addEventListener('resize', schedule);
})();

const filtersToggle = document.querySelector('[data-filters-toggle]');
const filtersPanel = document.querySelector('[data-filters]');
const activeFilterKeys = ['q', 'make', 'model', 'price', 'transmission', 'fuel', 'year', 'mileage', 'body', 'saved'];
const countActiveFilters = (form) => {
  if (!form) return 0;
  let total = 0;
  activeFilterKeys.forEach((key) => {
    const fields = form.querySelectorAll(`[name="${key}"]`);
    if (!fields.length) return;
    const field = fields[0];
    if (field.type === 'checkbox') {
      if (field.checked) total += 1;
      return;
    }
    if (field.type === 'range') {
      if (String(field.value).trim() !== '' && Number(field.value) < Number(field.max)) total += 1;
      return;
    }
    if (String(field.value || '').trim() !== '') total += 1;
  });
  return total;
};
const paintFiltersToggle = (open, form = filtersToggle?.closest('form')) => {
  if (!filtersToggle) return;
  const active = countActiveFilters(form);
  const badge = active > 0 ? `<span class="stock-filter-count">${active}</span>` : '';
  const label = open ?? filtersPanel?.classList.contains('is-open');
  filtersToggle.innerHTML = `${label ? 'Hide filters' : 'Show filters'}${badge}`;
  filtersToggle.setAttribute('aria-expanded', label ? 'true' : 'false');
};
filtersToggle?.addEventListener('click', () => {
  const open = !filtersPanel?.classList.contains('is-open');
  filtersPanel?.classList.toggle('is-open', open);
  paintFiltersToggle(open);
});

document.querySelector('.account-side-nav a.selected')?.scrollIntoView({
  inline: 'center',
  block: 'nearest',
  behavior: 'smooth',
});

markSaved();

const make = document.querySelector('[data-make]');
const model = document.querySelector('[data-model]');
if (make && model) {
  const options = Array.from(model.options);
  const filter = () => {
    const selected = make.value;
    options.forEach((option) => {
      option.hidden = option.value !== '' && selected !== '' && option.dataset.make && option.dataset.make !== selected;
    });
    if (model.selectedOptions[0]?.hidden) model.value = '';
  };
  make.addEventListener('change', filter);
  filter();
}

document.querySelectorAll('form[data-live-search]').forEach((form) => {
  const target = document.querySelector(form.dataset.liveSearch);
  if (!target) return;
  let controller;
  let timer;

  const queryFromForm = () => {
    const params = new URLSearchParams();
    new FormData(form).forEach((value, key) => {
      if (String(value).trim() !== '') params.append(key, value);
    });
    return params;
  };

  const applyBtn = form.querySelector('[data-stock-apply]');
  const filtersPanel = form.querySelector('[data-filters]');
  const filtersToggle = form.querySelector('[data-filters-toggle]');

  const paintApplyLabel = () => {
    if (!applyBtn) return;
    const countText = target.querySelector('.stock-countbar b')?.textContent || '';
    const match = countText.match(/([\d,]+)/);
    if (!match) {
      applyBtn.textContent = 'Show cars';
      return;
    }
    const total = match[1];
    applyBtn.textContent = `Show ${total} ${total === '1' ? 'car' : 'cars'}`;
  };

  const load = (params, { closeFilters = false } = {}) => {
    const query = params || queryFromForm();
    const slider = form.querySelector('[data-price-slider]');
    if (slider && Number(slider.value) >= Number(slider.max)) query.delete('price');
    query.delete('fragment');
    // Live search always resets to page 1 when filters change
    query.delete('page');
    const next = new URL(window.location.href);
    next.search = query.toString();
    history.replaceState(null, '', next);
    query.set('fragment', '1');
    const url = new URL(form.action, window.location.origin);
    url.search = query.toString();
    controller?.abort();
    controller = new AbortController();
    target.setAttribute('aria-busy', 'true');
    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: controller.signal })
      .then((response) => response.text())
      .then((html) => { target.innerHTML = html; })
      .catch((error) => { if (error.name !== 'AbortError') throw error; })
      .finally(() => {
        target.removeAttribute('aria-busy');
        markSaved(target);
        paintApplyLabel();
        if (closeFilters && filtersPanel) {
          filtersPanel.classList.remove('is-open');
        }
        paintFiltersToggle(filtersPanel?.classList.contains('is-open') ?? false, form);
      });
  };

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    // Mobile drawer: apply once and close. Desktop live-search already updated.
    load(undefined, { closeFilters: window.matchMedia('(max-width: 1020px)').matches });
  });
  form.addEventListener('change', () => load());
  form.addEventListener('input', (event) => {
    if (!event.target.matches('input[type="search"], input[type="text"], input[type="number"], input[type="range"]')) return;
    clearTimeout(timer);
    const delay = event.target.type === 'range' ? 120 : 250;
    timer = setTimeout(() => load(), delay);
  });
  paintApplyLabel();
  target.addEventListener('click', (event) => {
    const link = event.target.closest('a');
    if (!link) return;
    const url = new URL(link.href, window.location.origin);
    const stock = new URL(form.action, window.location.origin);
    if (url.pathname !== stock.pathname) return;
    event.preventDefault();
    const params = new URLSearchParams(url.search);
    params.delete('fragment');
    form.querySelectorAll('input, select').forEach((field) => {
      if (!field.name || field.type === 'submit') return;
      if (field.type === 'checkbox') field.checked = params.get(field.name) === field.value;
      else if (params.has(field.name)) field.value = params.get(field.name);
      else if (field.type === 'range') field.value = field.max;
    });
    load(params);
  });

  const applyStockView = (mode) => {
    const useList = mode === 'list' && window.matchMedia('(min-width: 721px)').matches;
    target.classList.toggle('is-list', useList);
    document.querySelectorAll('[data-stock-view]').forEach((item) => {
      const on = useList ? item.dataset.stockView === 'list' : item.dataset.stockView === 'grid';
      item.classList.toggle('is-on', on);
    });
  };
  document.querySelectorAll('[data-stock-view]').forEach((button) => {
    button.addEventListener('click', () => applyStockView(button.dataset.stockView));
  });
  window.matchMedia('(min-width: 721px)').addEventListener('change', () => {
    const current = target.classList.contains('is-list') ? 'list' : 'grid';
    applyStockView(current);
  });

  const slider = form.querySelector('[data-price-slider]');
  const priceLabel = form.querySelector('[data-price-label]');
  if (slider && priceLabel) {
    const paint = () => {
      const value = Number(slider.value);
      priceLabel.textContent = value >= Number(slider.max)
        ? 'Any price'
        : `Up to £${value.toLocaleString('en-GB')}`;
    };
    slider.addEventListener('input', paint);
  }
});

document.querySelectorAll('[data-steps]').forEach((form) => {
  const steps = Array.from(form.querySelectorAll('.sell-step'));
  const bars = Array.from(form.parentElement.querySelectorAll('.steps .step'));
  let index = 0;
  const show = () => {
    steps.forEach((step, i) => step.classList.toggle('active', i === index));
    bars.forEach((bar, i) => bar.classList.toggle('on', i <= index));
  };
  show();
  form.querySelectorAll('[data-next]').forEach((button) => button.addEventListener('click', () => {
    if (index < steps.length - 1) { index += 1; show(); }
  }));
  form.querySelectorAll('[data-back]').forEach((button) => button.addEventListener('click', () => {
    if (index > 0) { index -= 1; show(); }
  }));
});

const calc = document.querySelector('[data-calculator]');
if (calc) {
  const money = (amount) => amount.toLocaleString('en-GB', { style: 'currency', currency: 'GBP', maximumFractionDigits: 0 });
  const run = () => {
    const priceInput = calc.querySelector('[name=price]');
    const depositInput = calc.querySelector('[name=deposit]');
    const price = Number(priceInput.value || 0);
    if (depositInput.max) depositInput.max = price;
    if (Number(depositInput.value) > price) depositInput.value = price;
    const deposit = Number(depositInput.value || 0);
    const monthsInput = calc.querySelector('[name=months]');
    const months = Number(monthsInput?.value || 48);
    const monthsLabel = calc.querySelector('[data-months-label]');
    if (monthsLabel) monthsLabel.textContent = `${months} months`;
    const amount = Math.max(price - deposit, 0);
    const rate = 0.129 / 12;
    const payment = amount * rate / (1 - Math.pow(1 + rate, -months));
    const label = (selector, value) => {
      const node = calc.querySelector(selector);
      if (node) node.textContent = money(value);
    };
    label('[data-price-label]', price);
    label('[data-deposit-label]', deposit);
    calc.querySelector('[data-payment]').textContent = Number.isFinite(payment) ? `${money(payment)} /mo` : '—';
  };
  calc.addEventListener('input', run);
  run();
}

document.querySelectorAll('[data-gallery]').forEach((gallery) => {
  const main = gallery.querySelector('[data-gallery-main]');
  const count = gallery.querySelectorAll('[data-gallery-thumb]');
  count.forEach((thumb) => {
    thumb.addEventListener('click', () => {
      if (!main) return;
      main.src = thumb.dataset.full;
      main.alt = thumb.dataset.alt || main.alt;
      count.forEach((item) => item.classList.toggle('is-selected', item === thumb));
    });
  });
  const dialog = document.querySelector('[data-lightbox]');
  const dialogImage = dialog?.querySelector('[data-lightbox-image]');
  gallery.querySelector('[data-gallery-open]')?.addEventListener('click', () => {
    if (!dialog || !dialogImage || !main?.src) return;
    dialogImage.src = main.src;
    dialogImage.alt = main.alt;
    dialog.showModal();
  });
});

document.querySelector('[data-lightbox-close]')?.addEventListener('click', () => {
  document.querySelector('[data-lightbox]')?.close();
});
document.querySelector('[data-lightbox]')?.addEventListener('click', (event) => {
  if (event.target === event.currentTarget) {
    event.currentTarget.close();
  }
});

document.querySelector('[data-feature-search]')?.addEventListener('input', (event) => {
  const term = event.target.value.trim().toLowerCase();
  const extra = document.querySelector('[data-features-extra]');
  if (extra && term !== '') {
    extra.hidden = false;
    const more = document.querySelector('[data-features-more]');
    if (more) more.hidden = true;
  }
  document.querySelectorAll('[data-feature]').forEach((item) => {
    item.hidden = term !== '' && !item.dataset.feature.includes(term);
  });
  document.querySelectorAll('[data-feature-group]').forEach((group) => {
    group.hidden = group.querySelectorAll('[data-feature]:not([hidden])').length === 0;
  });
});

document.querySelector('[data-features-more]')?.addEventListener('click', (event) => {
  const extra = document.querySelector('[data-features-extra]');
  if (!extra) return;
  extra.hidden = false;
  event.currentTarget.hidden = true;
});

document.querySelector('[data-open-reserve]')?.addEventListener('click', () => {
  const details = document.querySelector('#reserve');
  if (!details) return;
  details.open = true;
  details.scrollIntoView({ behavior: 'smooth', block: 'center' });
});

document.querySelectorAll('[data-password-toggle]').forEach((button) => {
  const input = button.parentElement?.querySelector('input');
  if (!input) return;
  button.addEventListener('click', () => {
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    button.classList.toggle('is-visible', show);
    button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    button.setAttribute('aria-pressed', show ? 'true' : 'false');
  });
});

document.querySelector('[data-add-line]')?.addEventListener('click', () => {
  const row = document.querySelector('[data-line]');
  if (!row) return;
  const copy = row.cloneNode(true);
  copy.querySelectorAll('input').forEach((input) => { input.value = ''; });
  row.parentElement.appendChild(copy);
});

document.querySelectorAll('form[data-home-search]').forEach((form) => {
  const getChipFieldValue = (field) => {
    if (field === 'body') {
      return form.querySelector('select[name="body"]')?.value || '';
    }
    return form.querySelector(`[data-home-chip-field="${field}"]`)?.value || '';
  };

  const setChipField = (field, value) => {
    if (field === 'body') {
      const body = form.querySelector('select[name="body"]');
      if (body) body.value = value;
      return;
    }
    const input = form.querySelector(`[data-home-chip-field="${field}"]`);
    if (input) input.value = value;
  };

  const clearChipField = (field, value) => {
    if (field === 'body') {
      const body = form.querySelector('select[name="body"]');
      if (body && body.value === value) body.value = '';
      return;
    }
    const input = form.querySelector(`[data-home-chip-field="${field}"]`);
    if (input && input.value === value) input.value = '';
  };

  const syncChipsFromForm = () => {
    form.querySelectorAll('[data-home-chip]').forEach((chip) => {
      const field = chip.dataset.field;
      const value = chip.dataset.value || '';
      chip.classList.toggle('is-active', value !== '' && getChipFieldValue(field) === value);
    });
  };

  form.querySelectorAll('[data-home-chip]').forEach((chip) => {
    chip.addEventListener('click', () => {
      const field = chip.dataset.field;
      const value = chip.dataset.value || '';
      const currentlyActive = value !== '' && getChipFieldValue(field) === value;
      if (currentlyActive) {
        clearChipField(field, value);
      } else {
        form.querySelectorAll(`[data-home-chip][data-field="${field}"]`).forEach((other) => {
          if (other === chip) return;
          other.classList.remove('is-active');
        });
        setChipField(field, value);
      }
      syncChipsFromForm();
    });
  });

  form.querySelector('select[name="body"]')?.addEventListener('change', () => {
    syncChipsFromForm();
  });

  syncChipsFromForm();
});

document.querySelectorAll('[data-google-reviews]').forEach((widget) => {
  const track = widget.querySelector('[data-reviews-track]');
  const prev = widget.querySelector('[data-reviews-prev]');
  const next = widget.querySelector('[data-reviews-next]');
  if (!track) {
    return;
  }

  const step = () => {
    const card = track.querySelector('[data-review-card]');
    if (!card) {
      return track.clientWidth * 0.8;
    }
    const styles = window.getComputedStyle(track);
    const gap = Number.parseFloat(styles.columnGap || styles.gap || '0') || 0;
    return card.getBoundingClientRect().width + gap;
  };

  const syncNav = () => {
    const max = Math.max(0, track.scrollWidth - track.clientWidth - 2);
    if (prev) {
      prev.hidden = track.scrollLeft <= 2;
    }
    if (next) {
      next.hidden = track.scrollLeft >= max;
    }
  };

  prev?.addEventListener('click', () => {
    track.scrollBy({ left: -step(), behavior: 'smooth' });
  });
  next?.addEventListener('click', () => {
    track.scrollBy({ left: step(), behavior: 'smooth' });
  });
  track.addEventListener('scroll', syncNav, { passive: true });
  window.addEventListener('resize', syncNav);
  syncNav();

  widget.querySelectorAll('[data-review-more]').forEach((button) => {
    button.addEventListener('click', () => {
      const card = button.closest('[data-review-card]');
      const quote = card?.querySelector('[data-review-quote]');
      if (!quote) {
        return;
      }
      const expanded = button.dataset.expanded === '1';
      quote.textContent = expanded ? quote.dataset.preview : quote.dataset.full;
      button.dataset.expanded = expanded ? '0' : '1';
      button.textContent = expanded ? 'Read more' : 'Read less';
    });
  });
});
