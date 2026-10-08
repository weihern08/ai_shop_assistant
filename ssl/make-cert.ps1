# Generate a self-signed HTTPS certificate for your LAN IP (requires openssl).
# Usage:  powershell -ExecutionPolicy Bypass -File ssl\make-cert.ps1

$ErrorActionPreference = 'Stop'
$ip = '10.74.199.177'
$outDir = Join-Path $PSScriptRoot 'certs'
New-Item -ItemType Directory -Force -Path $outDir | Out-Null

$openssl = $null
foreach ($candidate in @(
    'openssl',
    'C:\xampp\apache\bin\openssl.exe',
    'C:\Program Files\Git\usr\bin\openssl.exe'
)) {
    if ($candidate -eq 'openssl') {
        $cmd = Get-Command openssl -ErrorAction SilentlyContinue
        if ($cmd) { $openssl = $cmd.Source; break }
    } elseif (Test-Path $candidate) {
        $openssl = $candidate; break
    }
}

if (-not $openssl) {
    Write-Host 'OpenSSL not found. Install Git for Windows or use XAMPP openssl, then re-run.'
    exit 1
}

$key = Join-Path $outDir 'ai-shop.key'
$crt = Join-Path $outDir 'ai-shop.crt'
$cnf = Join-Path $outDir 'openssl.cnf'

@"
[req]
default_bits = 2048
prompt = no
default_md = sha256
distinguished_name = dn
x509_extensions = v3_req

[dn]
CN = $ip

[v3_req]
subjectAltName = @alt_names
basicConstraints = CA:FALSE
keyUsage = digitalSignature, keyEncipherment
extendedKeyUsage = serverAuth

[alt_names]
IP.1 = $ip
IP.2 = 127.0.0.1
DNS.1 = localhost
"@ | Set-Content -Path $cnf -Encoding ASCII

& $openssl req -x509 -nodes -days 825 -newkey rsa:2048 -keyout $key -out $crt -config $cnf

Write-Host ''
Write-Host "Certificate created for IP $ip"
Write-Host "  $crt"
Write-Host "  $key"
Write-Host ''
Write-Host "Phone URL: https://${ip}:8443/scan.php"
