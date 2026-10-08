# Enable HTTPS for phone scanning (LAN IP)

Phone cameras need **HTTPS**. Your QR code points to:

```text
https://10.74.199.177/ai_shop_assistant/scan.php
```

Update the IP in `includes/config.local.php` if Wi‑Fi DHCP changes it (`ipconfig`).

## Option A — XAMPP Apache SSL (recommended)

### 1. Copy project into htdocs

```text
C:\xampp\htdocs\ai_shop_assistant\
```

### 2. Create a self-signed certificate for your IP

In **Git Bash** or any shell with `openssl`:

```bash
cd /c/xampp/apache/conf
mkdir -p ssl.crt ssl.key
openssl req -x509 -nodes -days 825 -newkey rsa:2048 \
  -keyout ssl.key/ai-shop.key \
  -out ssl.crt/ai-shop.crt \
  -subj "/CN=10.74.199.177" \
  -addext "subjectAltName=IP:10.74.199.177"
```

### 3. Edit `C:\xampp\apache\conf\extra\httpd-ssl.conf`

Find the `<VirtualHost _default_:443>` block and set:

```apache
DocumentRoot "C:/xampp/htdocs"
ServerName 10.74.199.177:443
SSLCertificateFile "conf/ssl.crt/ai-shop.crt"
SSLCertificateKeyFile "conf/ssl.key/ai-shop.key"
```

### 4. Enable SSL in `C:\xampp\apache\conf\httpd.conf`

Uncomment:

```apache
LoadModule ssl_module modules/mod_ssl.so
Include conf/extra/httpd-ssl.conf
```

### 5. Restart Apache in XAMPP

### 6. On your phone

1. Same Wi‑Fi as the PC  
2. Open `https://10.74.199.177/ai_shop_assistant/`  
3. Accept the certificate warning (Advanced → Proceed) once  
4. Allow camera permission  
5. Or scan the homepage QR code  

## Option B — Temporary: use desktop localhost

`http://localhost/...` can use the camera on the **same PC**, but phones cannot use `localhost` of your computer.

## Firewall

Allow inbound TCP **443** (and **80**) for Apache in Windows Firewall if the phone cannot connect.
