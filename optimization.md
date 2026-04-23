# Makler App — Optimizasiya və Produksiya Məlumatları

## Stack
- Laravel 12 + Octane (FrankenPHP) + Livewire Volt + Alpine.js
- MySQL 8.0, Redis, Socket.IO (Node.js), Laravel Reverb
- Docker Compose: `docker/docker-compose.yml`
- Domain: binokl.az (Cloudflare Flexible SSL — serverdə SSL yoxdur, HTTP işlənir)

---

## Stress Test Nəticələri (2026-04-23, 50 VU)

### Optimizasiyadan əvvəl
- p(95): 3.83s ❌ (hədəf <2s)
- Ortalama: 1.12s
- makler_app pik CPU: 384%
- makler_mysql pik CPU: 105%

### Optimizasiyadan sonra (HTTP + Socket birlikdə)
- p(95): 1.87s ✅
- Ortalama: 721ms
- makler_app pik CPU: ~400%
- makler_mysql pik CPU: ~61%
- Socket: 449 WS sessiya, ortalama 29.4s açıq, server CPU <4%

### Test faylı
`makler_test.js` — k6 skripti, həm HTTP həm Socket.IO test edir.
Sessiya cookie lazımdır: `binokl-session=...` dəyərini skriptdə yenilə.
```bash
k6 run makler_test.js
```

---

## Edilmiş Optimizasiyalar

### 1. Octane Worker Sayı
**Fayl:** `docker/docker-compose.yml` → `app` service
```
--workers=12
```
Server 6 core-dur (nproc=6), worker = core × 2. Yeni serverə köçəndə `nproc` yoxla, worker sayını ona görə ayarla.

### 2. MySQL Composite Index
**Migration:** `database/migrations/2026_04_23_000001_add_performance_indexes_to_properties_table.php`
- `properties` cədvəlinə `(is_owner, bumped_at)` composite index əlavə edildi
- Properties səhifəsinin əsas filteri bu iki sütunu birlikdə istifadə edir

### 3. Redis Cache — Properties səhifəsi
**Fayl:** `resources/views/livewire/properties/index.blade.php` → `with()` metodu
- `categories` → 5 dəqiqə cache
- `locations` → 5 dəqiqə cache
- `totalCount` → 60 saniyə cache
- Hər request-də bu 3 sorğu MySQL-ə getmirdi, Redis-dən oxunur

### 4. MySQL Slow Query Log
Yük altında yavaş sorğuları tapmaq üçün aktivdir:
```bash
docker exec makler_mysql mysql -uroot -pmakler_root_2026 -e "
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 0.5;
SET GLOBAL slow_query_log_file = '/var/lib/mysql/slow.log';
"
```
Log faylı: `/var/lib/mysql/slow.log` (konteynerin içində)
```bash
docker exec makler_mysql cat /var/lib/mysql/slow.log
```
**Qeyd:** MySQL restart-da sıfırlanır, yenidən set etmək lazımdır. Kalıcı etmək üçün `my.cnf`-ə yaz.

---

## Produksiya Yoxlama Siyahısı

Yeni serverə köçəndə mütləq yoxla:

### .env
```
APP_ENV=production
APP_DEBUG=false          # ← mütləq false olmalı, true-da DB şifrəsi ekranda görünür
LOG_LEVEL=error          # ← debug diski doldurur
SESSION_DRIVER=redis     ✅
CACHE_STORE=redis        ✅
SESSION_DOMAIN=binokl.az ✅
```

### Middleware (bootstrap/app.php)
```php
$middleware->trustProxies(at: '*');           // Cloudflare üçün şərtdir
$middleware->validateCsrfTokens(except: ['/logout']);
```
TrustProxies olmasa session, IP, HTTPS düzgün işləməz.

### Worker sayı
```bash
nproc  # core sayını öyrən
# docker-compose.yml-də: --workers=(nproc × 2)
```

### Cache təmizlə + yenilə
```bash
docker exec makler_app php artisan optimize:clear
docker exec makler_app php artisan optimize
```

---

## Arxitektura Qeydi

```
Cloudflare (HTTPS) → nginx_proxy → makler_webserver (nginx) → makler_app (FrankenPHP/Octane)
                                                             → makler_mysql
                                                             → makler_redis
                                 → makler_socket (Socket.IO :3000) ← Redis pub/sub
                                 → makler_reverb (Laravel Reverb :8080)
```

- `makler_socket`: Node.js Socket.IO, Redis `binokl-database-properties.new` kanalını dinləyir, brauzerlərə `property.created` event göndərir
- `makler_scraper`: bina.az GraphQL API-dən 5-10s intervalda yeni elanları çəkir, Redis-ə publish edir
- `makler_queue_default`: arka plan işləri (matching, bildiriş və s.)
- `makler_scheduler`: Laravel scheduler

---

## Kapasite Təxmini

| Eyni anda aktiv istifadəçi | Vəziyyət |
|---------------------------|----------|
| 0–75 | Rahat, p95 <2s |
| 75–150 | İşləyir, p95 2s-ə yaxınlaşır |
| 150–200 | CPU dolar, yavaşlama başlayar |
| 200+ | Horizontal scaling lazımdır |

Binokl.az B2B platformadır (real estate agentlər) — 150 eyni anda aktiv istifadəçi = ~500-1000 gündəlik unikal ziyarətçi. Hazırki server bu yük üçün yetərlidir.
