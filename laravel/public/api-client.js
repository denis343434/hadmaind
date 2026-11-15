/* Мини-клиент API для обычных (не модульных) страниц сайта. */
(function () {
  const BASE = (window.SITE_CONFIG && window.SITE_CONFIG.apiBase) || '/api';

  class ApiError extends Error {
    constructor(message, status, details) {
      super(message);
      this.name = 'ApiError';
      this.status = status;
      this.details = details;
    }
  }

  async function request(path, { method = 'GET', body, query } = {}) {
    const params = new URLSearchParams();
    for (const [key, value] of Object.entries(query ?? {})) {
      if (value !== undefined && value !== null && value !== '') params.set(key, value);
    }
    const queryString = params.toString();
    const target = BASE + path + (queryString ? `?${queryString}` : '');
    let url = target;
    try {
      url = new URL(target, location.origin);
    } catch {
      /* file:// или нестандартный origin — fetch упадёт, сработает локальный резерв */
    }

    let response;
    try {
      response = await fetch(url, {
        method,
        headers: body !== undefined ? { 'Content-Type': 'application/json' } : undefined,
        body: body !== undefined ? JSON.stringify(body) : undefined,
      });
    } catch (error) {
      throw new ApiError('Нет связи с сервером. Проверьте интернет и попробуйте ещё раз.', 0);
    }

    if (response.status === 204) return null;
    const data = await response.json().catch(() => null);
    if (!response.ok) {
      throw new ApiError(data?.error ?? `Ошибка ${response.status}`, response.status, data?.details);
    }
    return data;
  }

  window.siteApi = {
    request,
    get: (path, query) => request(path, { query }),
    post: (path, body) => request(path, { method: 'POST', body }),
    ApiError,
  };
})();
