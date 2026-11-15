/**
 * Строительные блоки админки: поля форм по описанию, диалог-форма, таблица, форматирование.
 *
 * Описание поля: { name, label, type, options, required, half, hint, numeric, nullable, rows, placeholder, advanced }
 *   advanced — только в полном режиме (см. mode.js)
 *   type: text | email | tel | date | time | password | textarea | number | money | checkbox | select | multiselect | image
 *   money — ввод в рублях, наружу копейки; numeric — привести значение select/multiselect к числу;
 *   nullable — пустое поле отправляется как null (чтобы очистить значение), иначе не отправляется вовсе.
 */
import { h, toast } from '../../js/dom.js';
import { clearErrors, maskPhone, showErrors, withPending } from '../../js/forms.js';
import { getTimezone } from '../../js/format.js';
import { uploadFiles } from './api.js';
import { visible } from './mode.js';

// ─── Форматирование ─────────────────────────────────────

const rub = new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB', minimumFractionDigits: 0, maximumFractionDigits: 2 });
/** Прозрачная картинка-заглушка: пустая миниатюра без значка «битой» картинки. */
export const BLANK_IMG = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
export const money = (kopecks) => (kopecks == null ? '—' : rub.format(kopecks / 100));

export function prettyPhone(phone) {
  const d = String(phone ?? '').replace(/\D/g, '');
  if (d.length !== 11 || d[0] !== '7') return phone ?? '';
  return `+7 (${d.slice(1, 4)}) ${d.slice(4, 7)}-${d.slice(7, 9)}-${d.slice(9)}`;
}
export const phoneLink = (phone) => phone && h('a', { href: `tel:${phone}`, onclick: (e) => e.stopPropagation() }, prettyPhone(phone));

/** ISO → { date: 'YYYY-MM-DD', time: 'HH:mm' } во времени заведения. */
export function localParts(iso) {
  const d = new Date(iso);
  const date = new Intl.DateTimeFormat('en-CA', { timeZone: getTimezone() }).format(d);
  const time = new Intl.DateTimeFormat('ru-RU', { timeZone: getTimezone(), hour: '2-digit', minute: '2-digit' }).format(d);
  return { date, time };
}
export const formatDateTime = (iso) =>
  new Intl.DateTimeFormat('ru-RU', { timeZone: getTimezone(), day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }).format(new Date(iso));
export const formatDayTitle = (isoDate) =>
  new Intl.DateTimeFormat('ru-RU', { timeZone: 'UTC', weekday: 'long', day: 'numeric', month: 'long' }).format(new Date(`${isoDate}T00:00:00Z`));

const TRANSLIT = { а: 'a', б: 'b', в: 'v', г: 'g', д: 'd', е: 'e', ё: 'e', ж: 'zh', з: 'z', и: 'i', й: 'y', к: 'k', л: 'l', м: 'm', н: 'n', о: 'o', п: 'p', р: 'r', с: 's', т: 't', у: 'u', ф: 'f', х: 'h', ц: 'ts', ч: 'ch', ш: 'sh', щ: 'sch', ъ: '', ы: 'y', ь: '', э: 'e', ю: 'yu', я: 'ya' };
export const slugify = (text) =>
  String(text)
    .toLowerCase()
    .split('')
    .map((c) => TRANSLIT[c] ?? c)
    .join('')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 60);

// ─── Разметка ───────────────────────────────────────────

export const pageHeader = (title, ...actions) =>
  h('div', { class: 'page-head' }, h('h1', null, title), h('div', { class: 'page-head__actions' }, actions));

export const statusBadge = (status, titles) => h('span', { class: `status status--${status}` }, titles[status] ?? status);

export const emptyState = (text) => h('div', { class: 'empty' }, text);

/**
 * Таблица. columns: [{ title, render(row) → Node|string, class, advanced }]
 * rows могут содержать { group: 'Заголовок' } — строка-разделитель.
 */
export function table({ columns: allColumns, rows, onRowClick, rowClass, empty = 'Ничего не найдено' }) {
  if (rows.length === 0) return emptyState(empty);
  const columns = visible(allColumns); // колонки с advanced — только в полном режиме
  return h(
    'div',
    { class: 'table-wrap' },
    h(
      'table',
      { class: 'table' },
      h('thead', null, h('tr', null, columns.map((c) => h('th', { class: c.class }, c.title)))),
      h(
        'tbody',
        null,
        rows.map((row) =>
          row.group
            ? h('tr', { class: 'group' }, h('td', { colspan: columns.length }, row.group))
            : h(
                'tr',
                {
                  class: [onRowClick && 'is-clickable', rowClass?.(row)].filter(Boolean).join(' '),
                  onclick: onRowClick && ((e) => !e.target.closest('button, a, input, select, label') && onRowClick(row)),
                },
                columns.map((c) => h('td', { class: c.class }, c.render(row))),
              ),
        ),
      ),
    ),
  );
}

// ─── Поля ───────────────────────────────────────────────

let uid = 0;

export function imageInput(name, value, onChange) {
  const id = `f-${++uid}`;
  const preview = h('img', { class: 'image-input__preview', alt: '' });
  const url = h('input', { type: 'text', name, id, placeholder: 'Ссылка на картинку или загрузите файл' });
  const file = h('input', { type: 'file', accept: 'image/*' });
  const sync = () => {
    preview.src = url.value || BLANK_IMG;
    onChange?.(url.value);
  };
  url.value = value ?? '';
  url.addEventListener('input', sync);
  file.addEventListener('change', async () => {
    if (!file.files.length) return;
    btn.disabled = true;
    btn.textContent = 'Загрузка…';
    try {
      const [uploaded] = await uploadFiles(file.files);
      url.value = uploaded.url;
      // Как будто ввели руками — чтобы формы-подписчики узнали об изменении
      url.dispatchEvent(new Event('input', { bubbles: true }));
    } catch (err) {
      toast(err.message, { error: true });
    } finally {
      btn.disabled = false;
      btn.textContent = 'Загрузить';
      file.value = '';
    }
  });
  const btn = h('button', { type: 'button', class: 'btn btn--ghost btn--small', onclick: () => file.click() }, 'Загрузить');
  sync();
  return { el: h('div', { class: 'image-input' }, preview, url, btn, file), id };
}

export function fieldEl(f, value) {
  const id = `f-${++uid}`;
  const wrapClass = `field ${f.half ? 'field--half' : ''}`;
  const hint = f.hint && h('p', { class: 'hint' }, f.hint);
  const label = (forId = id) => h('label', { for: forId }, f.label, f.required && ' *');

  switch (f.type) {
    case 'checkbox': {
      const input = h('input', { type: 'checkbox', name: f.name, id });
      input.checked = value ?? f.default ?? false;
      return h('label', { class: `check field ${f.half ? 'field--half' : ''}`, 'data-field': f.name }, input, h('span', null, f.label));
    }
    case 'select': {
      const select = h('select', { name: f.name, id }, f.options.map(([v, l]) => h('option', { value: v }, l)));
      // Без значения оставляем выбранным первый вариант — браузер так делает сам
      const initial = value ?? f.default;
      if (initial != null) select.value = String(initial);
      return h('div', { class: wrapClass, 'data-field': f.name }, label(), select, hint);
    }
    case 'multiselect': {
      const selected = new Set((value ?? []).map(String));
      return h(
        'div',
        { class: wrapClass, 'data-field': f.name },
        h('span', { class: 'repeater__label' }, f.label),
        f.options.length
          ? h(
              'div',
              { class: 'checks' },
              f.options.map(([v, l]) => {
                const input = h('input', { type: 'checkbox', name: f.name, value: v });
                input.checked = selected.has(String(v));
                return h('label', { class: 'check' }, input, h('span', null, l));
              }),
            )
          : h('p', { class: 'hint' }, f.emptyOptions ?? 'Нет вариантов'),
        hint,
      );
    }
    case 'image': {
      const { el, id: imgId } = imageInput(f.name, value);
      return h('div', { class: wrapClass, 'data-field': f.name }, label(imgId), el, hint);
    }
    case 'textarea': {
      const ta = h('textarea', { name: f.name, id, rows: f.rows ?? 3, placeholder: f.placeholder });
      ta.value = value ?? '';
      return h('div', { class: wrapClass, 'data-field': f.name }, label(), ta, hint);
    }
    default: {
      const type = f.type === 'money' ? 'number' : (f.type ?? 'text');
      const input = h('input', {
        type,
        name: f.name,
        id,
        placeholder: f.placeholder,
        step: f.type === 'money' ? '0.01' : f.step,
        min: f.min ?? (f.type === 'money' ? 0 : undefined),
        max: f.max,
        autocomplete: f.autocomplete ?? 'off',
        inputmode: f.type === 'money' ? 'decimal' : undefined,
      });
      input.value = value == null ? '' : f.type === 'money' ? String(value / 100) : f.type === 'tel' ? prettyPhone(value) : String(value);
      if (f.type === 'tel') maskPhone(input);
      return h('div', { class: wrapClass, 'data-field': f.name }, label(), input, hint);
    }
  }
}

/** Значения формы по описанию полей, с приведением типов. */
export function collect(form, fields) {
  const data = {};
  for (const f of fields) {
    if (f.type === 'multiselect') {
      data[f.name] = [...form.querySelectorAll(`input[name="${f.name}"]:checked`)].map((i) => (f.numeric ? Number(i.value) : i.value));
      continue;
    }
    const el = form.querySelector(`[name="${f.name}"]`);
    if (!el) continue;
    if (f.type === 'checkbox') {
      data[f.name] = el.checked;
      continue;
    }
    const raw = el.value.trim();
    if (raw === '') {
      data[f.name] = f.nullable ? null : undefined;
      continue;
    }
    if (f.type === 'number' || (f.numeric && f.type === 'select')) data[f.name] = Number(raw);
    else if (f.type === 'money') data[f.name] = Math.round(parseFloat(raw.replace(',', '.')) * 100);
    else data[f.name] = raw;
  }
  return data;
}

/** Обязательные поля — проверяем до запроса, ошибки показываем у полей. */
export function checkRequired(form, fields) {
  const details = fields
    .filter((f) => f.required && f.type !== 'checkbox')
    .filter((f) => !form.querySelector(`[name="${f.name}"]`)?.value.trim())
    .map((f) => ({ path: f.name, message: 'Обязательное поле' }));
  if (details.length) showErrors(form, { message: 'Заполните обязательные поля', details });
  return details.length === 0;
}

/**
 * Диалог с формой. onSubmit(data) → результат; ошибки API показываются у полей.
 * extra(close) — дополнительные кнопки слева (например, «Удалить»).
 * Возвращает Promise с результатом onSubmit или null, если закрыли.
 */
export function formDialog({ title, intro, fields: allFields, values = {}, submitLabel = 'Сохранить', onSubmit, extra, footer }) {
  // Поля advanced в простом режиме не показываются и не отправляются — значение на сервере не меняется
  const fields = visible(allFields);
  return new Promise((resolve) => {
    let result = null;
    const form = h(
      'form',
      { class: 'form form--grid', novalidate: true },
      fields.map((f) => fieldEl(f, values[f.name])),
      h('p', { class: 'form__error', role: 'alert', hidden: true }),
      footer,
      h(
        'div',
        { class: 'dialog__actions' },
        extra && h('span', { class: 'spacer' }, extra((r) => close(r))),
        // «Закрыть», а не «Отмена» — чтобы не путать с «Отменить бронь»
        h('button', { class: 'btn btn--ghost', type: 'button', 'data-close': true }, 'Закрыть'),
        h('button', { class: 'btn', type: 'submit' }, submitLabel),
      ),
    );
    const dialog = h(
      'dialog',
      { class: 'dialog dialog--wide', 'data-sticky': true },
      h('button', { class: 'dialog__close', type: 'button', 'data-close': true, 'aria-label': 'Закрыть' }, '×'),
      h('h2', { class: 'dialog__title' }, title),
      intro && h('div', { class: 'dialog__intro' }, intro),
      form,
    );
    const close = (r) => {
      result = r;
      dialog.close();
    };

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      clearErrors(form);
      if (!checkRequired(form, fields)) return;
      try {
        const r = await withPending(form, () => onSubmit(collect(form, fields)));
        close(r ?? true);
      } catch (err) {
        showErrors(form, err);
      }
    });
    dialog.addEventListener('close', () => {
      // Тост мог переехать внутрь диалога — возвращаем, иначе он удалится вместе с диалогом
      const toastEl = dialog.querySelector('#toast');
      if (toastEl) document.body.append(toastEl);
      dialog.remove();
      resolve(result);
    });

    document.body.append(dialog);
    dialog.showModal();
    dialog.querySelector('input:not([type=checkbox]):not([type=file]), textarea, select')?.focus();
  });
}

/** Кнопка удаления для диалогов: подтверждение → запрос → закрытие. */
export function deleteButton(label, question, action) {
  return (close) =>
    h(
      'button',
      {
        class: 'btn btn--danger',
        type: 'button',
        onclick: async (e) => {
          if (!confirm(question)) return;
          e.target.disabled = true;
          try {
            await action();
            close('deleted');
          } catch (err) {
            toast(err.message, { error: true });
            e.target.disabled = false;
          }
        },
      },
      label,
    );
}

/** Ошибку действия показываем тостом, без падения страницы. */
export async function attempt(fn, success) {
  try {
    const r = await fn();
    if (success) toast(success);
    return r;
  } catch (err) {
    toast(err.message, { error: true });
    return undefined;
  }
}
