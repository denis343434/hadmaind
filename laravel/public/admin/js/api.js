import { ApiError } from '../../js/api.js';

const BASE = window.SITE_CONFIG?.apiBase ?? '/api';
const TOKEN_KEY = 'admin_token';
const EMAIL_KEY = 'admin_email';

// localStorage может быть недоступен (приватный режим, запрет cookies) — тогда токен живёт до перезагрузки
const memory = {};
const store = {
  get: (k) => {
    try {
      return localStorage.getItem(k) ?? memory[k] ?? null;
    } catch {
      return memory[k] ?? null;
    }
  },
  set: (k, v) => {
    memory[k] = v ?? undefined;
    try {
      if (v) localStorage.setItem(k, v);
      else localStorage.removeItem(k);
    } catch {
      /* ignore */
    }
  },
};

export const auth = {
  get token() {
    return store.get(TOKEN_KEY);
  },
  get email() {
    return store.get(EMAIL_KEY);
  },
  login(token, email) {
    store.set(TOKEN_KEY, token);
    store.set(EMAIL_KEY, email);
  },
  logout() {
    store.set(TOKEN_KEY, null);
  },
};

let onUnauthorized = () => {};
export const setUnauthorizedHandler = (fn) => {
  onUnauthorized = fn;
};

/** Запрос к /api/admin/*. `form` — FormData для загрузки файлов. */
export async function adminApi(path, { method = 'GET', body, query, form } = {}) {
  const url = new URL(`${BASE}/admin${path}`, location.origin);
  for (const [k, v] of Object.entries(query ?? {})) {
    if (v !== undefined && v !== null && v !== '') url.searchParams.set(k, v);
  }

  const headers = {};
  if (auth.token) headers.Authorization = `Bearer ${auth.token}`;
  let payload;
  if (form) payload = form;
  else if (body !== undefined) {
    headers['Content-Type'] = 'application/json';
    payload = JSON.stringify(body);
  }

  let res;
  try {
    res = await fetch(url, { method, headers, body: payload });
  } catch {
    throw new ApiError('Нет связи с сервером', 0);
  }

  if (res.status === 401 && path !== '/auth/login') {
    auth.logout();
    onUnauthorized();
  }
  if (res.status === 204) return null;
  const data = await res.json().catch(() => null);
  if (!res.ok) throw new ApiError(data?.error ?? `Ошибка ${res.status}`, res.status, data?.details);
  return data;
}

export async function uploadFiles(files) {
  const form = new FormData();
  for (const file of files) form.append('files', file);
  return adminApi('/media', { method: 'POST', form });
}
