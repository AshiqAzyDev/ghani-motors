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

document.querySelector('[data-enquiry-open]')?.addEventListener('click', () => {
  document.querySelector('#enquiry')?.showModal();
});
document.querySelector('[data-enquiry-close]')?.addEventListener('click', () => {
  document.querySelector('#enquiry')?.close();
});

const filtersToggle = document.querySelector('[data-filters-toggle]');
const filtersPanel = document.querySelector('[data-filters]');
filtersToggle?.addEventListener('click', () => {
  const open = !filtersPanel?.classList.contains('is-open');
  filtersPanel?.classList.toggle('is-open', open);
  filtersToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  filtersToggle.textContent = open ? 'Hide filters' : 'Show filters';
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
        if (closeFilters && filtersPanel && filtersToggle) {
          filtersPanel.classList.remove('is-open');
          filtersToggle.setAttribute('aria-expanded', 'false');
          filtersToggle.textContent = 'Show filters';
        }
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
