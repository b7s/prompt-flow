Below is a **clean and reliable way** to make a **Laravel project start automatically on boot** in **Ubuntu (including Omarchy)** and always be available at **127.0.0.1 on a fixed port**.

---

## 🧱 Recommended Approach (Production‑like & Stable)

Use **PHP‑FPM + Nginx** and let **systemd** handle startup.

This is the **correct long‑term solution**.

---

## 1️⃣ Install Required Packages

```bash
sudo apt update
sudo apt install nginx php-fpm php-cli php-mbstring php-xml php-curl php-mysql unzip
```

Check PHP version:
```bash
php -v
```

---

## 2️⃣ Configure Laravel Environment

Inside your Laravel project:

```bash
cd /path/to/your/laravel-project
cp .env.example .env
php artisan key:generate
```

Set permissions:
```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

---

## 3️⃣ Configure Nginx (127.0.0.1 + Custom Port)

Create a new site config:

```bash
sudo nano /etc/nginx/sites-available/laravel-local
```

### Example Configuration (Port **8085**)

```nginx
server {
    listen 127.0.0.1:8085;
    server_name localhost;

    root /path/to/your/laravel-project/public;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

> ⚠️ Adjust a PHP version if needed (`php8.5-fpm.sock`, etc.)

Enable the site:
```bash
sudo ln -s /etc/nginx/sites-available/laravel-local /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

---

## 4️⃣ Enable Services on Boot

```bash
sudo systemctl enable nginx
sudo systemctl enable php8.4-fpm
```

Now your Laravel app **starts automatically after reboot**.

---

## 5️⃣ Access the Application

Open your browser:
```
http://127.0.0.1:8085
```

✔ Runs on boot  
✔ Fixed IP and port  
✔ No terminal required  
✔ Production‑like behavior

---

## 🔁 Alternative (Quick & Simple)

If you **really want** `php artisan serve`:

### Create a systemd service

```bash
sudo nano /etc/systemd/system/laravel-dev.service
```

```ini
[Unit]
Description=Prompt Flow Server
After=network.target

[Service]
User=your-username
WorkingDirectory=/path/to/your/laravel-project
ExecStart=/usr/bin/php artisan serve --host=127.0.0.1 --port=8085
Restart=always

[Install]
WantedBy=multi-user.target
```

Enable it:
```bash
sudo systemctl daemon-reload
sudo systemctl enable laravel-dev
sudo systemctl start laravel-dev
```
