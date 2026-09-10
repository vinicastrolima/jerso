/**
 * Configuração de Conexão com a API do AlaPoker
 * 
 * Quando você subir a API na sua VPS, altere o valor de `API_BASE_URL` para o domínio da sua API.
 * Exemplo: "https://api-poker.seudominio.com/api"
 */
window.ALAPOKER_CONFIG = {
  // Altere aqui para a URL da sua VPS (com /api no final)
  API_BASE_URL: window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1'
    ? 'http://localhost:8000/api'
    : (localStorage.getItem('alapoker_api_base') || 'https://api-poker.seudominio.com/api')
};
