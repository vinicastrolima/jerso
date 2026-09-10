/**
 * Configuração Automática de Conexão com a API do AlaPoker / Jerso Poker
 */
window.ALAPOKER_CONFIG = {
  API_BASE_URL: window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1'
    ? 'http://localhost:8000/api'
    : 'https://api-poker.vcldev.com.br/api'
};
