/**
 * Configuração de Conexão com a API do AlaPoker / Jerso Poker
 */
(function () {
  const PRODUCTION_API = 'https://api-poker.vcldev.com.br/api';
  const LOCAL_API = 'http://localhost:8000/api';
  const FRONTEND_HOSTS = ['jersopoker.vcldev.com.br', 'www.jersopoker.vcldev.com.br'];

  function normalizeApiBase(value) {
    if (!value) return null;

    const raw = String(value).trim().replace(/\/+$/, '');
    if (!raw) return null;
    if (raw === '/api') {
      return FRONTEND_HOSTS.includes(window.location.hostname) ? PRODUCTION_API : '/api';
    }

    try {
      const parsed = new URL(raw, window.location.origin);
      const host = parsed.hostname;
      const path = parsed.pathname.replace(/\/+$/, '');

      if (FRONTEND_HOSTS.includes(host)) {
        return PRODUCTION_API;
      }

      if (host === window.location.hostname && !FRONTEND_HOSTS.includes(host) && host !== 'localhost' && host !== '127.0.0.1') {
        return path === '/api' || path === '' ? '/api' : (path || '/api');
      }

      if (host === 'api-poker.vcldev.com.br' && path !== '/api') {
        return PRODUCTION_API;
      }

      return `${parsed.origin}${path || '/api'}`;
    } catch {
      return null;
    }
  }

  const isLocal = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
  const rawStored = localStorage.getItem('alapoker_api_base');
  const stored = normalizeApiBase(rawStored);
  const resolved = isLocal ? (stored || LOCAL_API) : (stored || PRODUCTION_API);

  if (rawStored && rawStored !== resolved) {
    localStorage.setItem('alapoker_api_base', resolved);
  }

  window.ALAPOKER_CONFIG = {
    API_BASE_URL: resolved,
    PRODUCTION_API_URL: PRODUCTION_API,
  };
})();
