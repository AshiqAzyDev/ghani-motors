document.documentElement.classList.add('js');

const menu = document.querySelector('[data-menu]');
document.querySelector('[data-menu-button]')?.addEventListener('click', () => menu?.classList.toggle('open'));

const saved = JSON.parse(localStorage.getItem('ghani-saved') || '[]').map(String);
const server = (document.body.dataset.saved || '').split(',').filter(Boolean);
const merged = Array.from(new Set([...saved, ...server]));
localStorage.setItem('ghani-saved', JSON.stringify(merged));
document.querySelectorAll('[name=saved_ids]').forEach((field) => { field.value = merged.join(','); });

document.querySelector('[data-enquiry-open]')?.addEventListener('click', () => {
  document.querySelector('#enquiry')?.showModal();
});
document.querySelector('[data-enquiry-close]')?.addEventListener('click', () => {
  document.querySelector('#enquiry')?.close();
});

document.querySelector('[data-filters-toggle]')?.addEventListener('click', () => {
  document.querySelector('.filters')?.classList.toggle('show');
});

document.querySelectorAll('[data-save]').forEach((button) => {
  if (merged.includes(String(button.dataset.save))) {
    button.classList.add('saved');
    button.textContent = '♥';
  }
});

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

  const load = (params) => {
    const query = params || queryFromForm();
    query.delete('fragment');
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
      .finally(() => target.removeAttribute('aria-busy'));
  };

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    load();
  });
  form.addEventListener('change', () => load());
  form.addEventListener('input', (event) => {
    if (!event.target.matches('input[type="search"], input[type="text"], input[type="number"]')) return;
    clearTimeout(timer);
    timer = setTimeout(() => load(), 250);
  });
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
    });
    load(params);
  });
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
    const months = Number(calc.querySelector('[name=months]').value || 48);
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
