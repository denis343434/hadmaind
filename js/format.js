let timezone = 'Europe/Moscow';
export const setTimezone = (value) => {
  if (value) timezone = value;
};
export const getTimezone = () => timezone;

const rub = new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB', maximumFractionDigits: 0 });
/** Цены в API — в копейках. */
export const formatPrice = (kopecks) => (kopecks > 0 ? rub.format(kopecks / 100) : 'Бесплатно');

export function formatDuration(min) {
  const hours = Math.floor(min / 60);
  const rest = min % 60;
  if (!hours) return `${rest} мин`;
  return rest ? `${hours} ч ${rest} мин` : `${hours} ч`;
}

export function plural(n, [one, few, many]) {
  const mod10 = n % 10;
  const mod100 = n % 100;
  if (mod10 === 1 && mod100 !== 11) return one;
  if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return few;
  return many;
}
export const guestsLabel = (n) => `${n} ${plural(n, ['гость', 'гостя', 'гостей'])}`;

// ─── Даты в часовом поясе заведения ──────────────────────
// Даты "YYYY-MM-DD" считаем UTC-полночью, чтобы арифметика не зависела от пояса браузера.

export const todayISO = () => new Intl.DateTimeFormat('en-CA', { timeZone: timezone }).format(new Date());

export function addDays(iso, days) {
  const d = new Date(`${iso}T00:00:00Z`);
  d.setUTCDate(d.getUTCDate() + days);
  return d.toISOString().slice(0, 10);
}

export function dayParts(iso) {
  const d = new Date(`${iso}T00:00:00Z`);
  const fmt = (o) => new Intl.DateTimeFormat('ru-RU', { timeZone: 'UTC', ...o }).format(d);
  const weekday = d.getUTCDay();
  return {
    wd: fmt({ weekday: 'short' }),
    num: d.getUTCDate(),
    mon: fmt({ month: 'short' }).replace('.', ''),
    long: fmt({ weekday: 'long', day: 'numeric', month: 'long' }),
    weekend: weekday === 0 || weekday === 6,
  };
}

/** ISO из API → "18:00" / "пт, 10 октября" во времени заведения. */
export const formatTime = (iso) =>
  new Intl.DateTimeFormat('ru-RU', { timeZone: timezone, hour: '2-digit', minute: '2-digit' }).format(new Date(iso));
export const formatDate = (iso) =>
  new Intl.DateTimeFormat('ru-RU', { timeZone: timezone, weekday: 'short', day: 'numeric', month: 'long' }).format(
    new Date(iso),
  );

export const STATUS = {
  pending: 'Ожидает подтверждения',
  confirmed: 'Подтверждена',
  cancelled: 'Отменена',
  completed: 'Завершена',
  no_show: 'Не состоялась',
};
