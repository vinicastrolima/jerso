# Guia Completo de Deploy: AlaPoker (VPS + Vercel + Subdomínio)

Este guia explica exatamente como colocar o **Backend (API Laravel)** na sua **VPS** e o **Frontend** na **Vercel**, conectando ambos através de subdomínios personalizados com SSL.

---

## 🗺️ Arquitetura de Domínios Sugerida

| Componente | Hospedagem | Subdomínio Exemplo |
| :--- | :--- | :--- |
| **Frontend (Web App)** | Vercel | `https://poker.seudominio.com` |
| **Backend (API Laravel)** | Sua VPS | `https://api-poker.seudominio.com` |

---

## 🛠️ ETAPA 1: Subir a API Laravel na sua VPS

### 1.1 Pré-requisitos na VPS (Ubuntu / Debian)
Conecte-se na sua VPS via SSH e garanta que o PHP 8.2+ e o Composer estejam instalados:
```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx git curl unzip sqlite3 \
    php-fpm php-cli php-sqlite3 php-mysql php-mbstring php-xml php-curl php-zip
```

### 1.2 Clonar o Repositório e Instalar Dependências
```bash
cd /var/www
sudo git clone https://github.com/SEU_USUARIO/alapoker.git
cd alapoker

# Instalar dependências de produção do Composer
sudo composer install --no-dev --optimize-autoloader

# Configurar arquivo .env
sudo cp .env.example .env
sudo php artisan key:generate
```

### 1.3 Configurar o Banco de Dados e Permissões
No `.env`, você pode manter o SQLite (já configurado por padrão):
```bash
# Cria o arquivo do banco SQLite se não existir
sudo touch database/database.sqlite

# Executar as migrations e dados iniciais
sudo php artisan migrate --force --seed

# Ajustar permissões para o servidor web (www-data)
sudo chown -R www-data:www-data /var/www/alapoker/storage /var/www/alapoker/bootstrap/cache /var/www/alapoker/database
sudo chmod -R 775 /var/www/alapoker/storage /var/www/alapoker/bootstrap/cache /var/www/alapoker/database
```

> **Dica**: No `.env`, defina seu Master PIN:
> `ADMIN_PIN=9999` *(ou outro PIN da sua preferência)*

### 1.4 Configuração do Nginx na VPS
Crie o arquivo de configuração do Nginx para o subdomínio da API:
```bash
sudo nano /etc/nginx/sites-available/api-poker.seudominio.com
```

Cole a configuração abaixo (substitua `api-poker.seudominio.com` pelo seu subdomínio e ajuste a versão do PHP se necessário):

```nginx
server {
    listen 80;
    server_name api-poker.seudominio.com;
    root /var/www/alapoker/public;

    index index.php index.html;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock; # ou php8.3 / php8.2
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Ative o site e reinicie o Nginx:
```bash
sudo ln -s /etc/nginx/sites-available/api-poker.seudominio.com /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 1.5 Gerar SSL Gratuito na VPS (HTTPS)
```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d api-poker.seudominio.com
```

---

## ⚡ ETAPA 2: Subir o Frontend na Vercel

O diretório `frontend/` já está 100% preparado com:
- `frontend/index.html` (design moderno 21st.dev)
- `frontend/config.js` (apontamento para sua API)
- `frontend/vercel.json` (regras de reescrita para rotas SPA como `/mesa/:code`)

### Opção A: Pelo Dashboard da Vercel (Recomendado)
1. Acesse [vercel.com](https://vercel.com) e clique em **Add New Project**.
2. Importe o repositório **alapoker**.
3. Na tela de configuração de build:
   - **Root Directory**: Clique em **Edit** e selecione a pasta `frontend`.
   - **Framework Preset**: Deixe como **Other**.
   - Não precisa de Build Command nem Output Directory.
4. Clique em **Deploy**!

### Opção B: Pelo Terminal (CLI da Vercel)
```bash
npm install -g vercel
cd /Users/vinicastrolima_dev/github/alapoker/frontend
vercel
```

### 2.2 Definir a URL da API no Frontend
No arquivo `frontend/config.js`:
```javascript
window.ALAPOKER_CONFIG = {
  API_BASE_URL: "https://api-poker.seudominio.com/api"
};
```
*(Você também pode alterar a URL da API a qualquer momento diretamente pelo ícone de servidor no cabeçalho do site!)*

---

## 🌐 ETAPA 3: Configurar os Apontamentos de DNS (Subdomínios)

Acesse o painel do seu provedor de domínio (Cloudflare, Registro.br, GoDaddy, Hostinger, etc.) e adicione as duas entradas:

### 1. Subdomínio da API (VPS)
- **Tipo**: `A`
- **Nome**: `api-poker`
- **Destino/IP**: `IP_DA_SUA_VPS` (ex: `123.45.67.89`)
- **Proxy/Nuvem**: Desativado (DNS Only) se for usar Certbot Let's Encrypt diretamente.

### 2. Subdomínio do Frontend (Vercel)
- **Tipo**: `CNAME`
- **Nome**: `poker`
- **Destino**: `cname.vercel-dns.com`

No painel da Vercel:
1. Acesse seu projeto -> **Settings** -> **Domains**.
2. Digite `poker.seudominio.com` e clique em **Add**.
3. A Vercel validará o CNAME e gerará o certificado SSL automaticamente em menos de 1 minuto!

---

## ✅ Concluído!

Pronto! Agora você tem:
- **`https://poker.seudominio.com`**: Web app rodando na infraestrutura global da Vercel com carregamento instantâneo no celular.
- **`https://api-poker.seudominio.com/api`**: API Laravel segura na sua VPS gerenciando as mesas, entradas, ranking e histórico de bankroll!
