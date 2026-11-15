import { h } from './dom.js';

/** Маска +7 (999) 123-45-67. Сервер всё равно нормализует номер — маска только для удобства. */
export function maskPhone(input) {
  input.addEventListener('input', (e) => {
    if (e.inputType?.startsWith('delete')) return; // не мешаем стирать скобки и дефисы
    let d = input.value.replace(/\D/g, '');
    if (!d) return;
    if (d[0] === '8') d = `7${d.slice(1)}`;
    if (d[0] !== '7') d = `7${d}`;
    d = d.slice(0, 11);
    const parts = [d.slice(1, 4), d.slice(4, 7), d.slice(7, 9), d.slice(9, 11)];
    let out = '+7';
    if (parts[0]) out += ` (${parts[0]}`;
    if (parts[0].length === 3) out += ')';
    if (parts[1]) out += ` ${parts[1]}`;
    if (parts[2]) out += `-${parts[2]}`;
    if (parts[3]) out += `-${parts[3]}`;
    input.value = out;
  });
}

export function clearErrors(form) {
  form.querySelectorAll('.field__error').forEach((el) => el.remove());
  form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
  const general = form.querySelector('.form__error');
  if (general) general.hidden = true;
}

function fieldWrap(form, name) {
  return form.querySelector(`[data-field="${name}"]`) ?? form.elements.namedItem(name)?.closest?.('.field');
}

/** Ошибки API ({ details: [{ path, message }] }) раскладываются по полям, остальное — в общий блок. */
export function showErrors(form, err) {
  clearErrors(form);
  const unmatched = [];
  for (const d of Array.isArray(err.details) ? err.details : []) {
    const wrap = fieldWrap(form, d.path);
    if (!wrap) {
      unmatched.push(d.message);
      continue;
    }
    if (!wrap.classList.contains('is-invalid')) {
      wrap.classList.add('is-invalid');
      wrap.append(h('span', { class: 'field__error' }, d.message));
    }
  }
  if (!form.querySelector('.is-invalid') || unmatched.length) {
    const general = form.querySelector('.form__error');
    general.textContent = unmatched.length ? unmatched.join('. ') : err.message;
    general.hidden = false;
  }
  form.querySelector('.is-invalid input, .is-invalid textarea')?.focus();
}

/** Проверка обязательных полей до запроса. */
export function validateRequired(form, messages) {
  const details = [];
  for (const [name, message] of Object.entries(messages)) {
    const el = form.elements.namedItem(name);
    const empty = el.type === 'checkbox' ? !el.checked : !el.value.trim();
    if (empty) details.push({ path: name, message });
  }
  if (details.length) showErrors(form, { message: 'Проверьте поля формы', details });
  return details.length === 0;
}

/** Блокирует кнопку отправки на время запроса. */
export async function withPending(form, fn) {
  const btn = form.querySelector('[type="submit"]');
  const label = btn.textContent;
  btn.disabled = true;
  btn.textContent = 'Отправляем…';
  try {
    return await fn();
  } finally {
    btn.disabled = false;
    btn.textContent = label;
  }
}

/** utm-метки и страница — уходят в meta заявки. */
export function trackingMeta() {
  const params = new URLSearchParams(location.search);
  const meta = { page: location.pathname };
  for (const key of ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term']) {
    if (params.get(key)) meta[key] = params.get(key);
  }
  return meta;
}
