# Yeni Server Quraşdırma Təlimatı

## Tövsiyə olunan Server: Hetzner CX32
- 4 vCPU, 8 GB RAM, 80 GB SSD
- ~€15/ay
- Ubuntu 24.04

---

## 1. Server ilkin quraşdırma

```bash
apt update && apt upgrade -y
apt install -y docker.io docker-compose-plugin git curl
systemctl enable docker
```

---

## 2. Repo klonla

```bash
cd /root/apps
git clone <repo-url> makler
```

---

## 3. .env faylını köçür

Köhnə serverdən `.env` faylını kopyala:
```bash
scp root@old-server:/root/apps/makler/repo/.env /root/apps/makler/repo/.env
```

---

## 4. Docker konteynerləri başlat

```bash
cd /root/apps/makler/docker
docker compose up -d
```

---

## 5. Kod optimizasiyaları (köhnə serverdə edilib, yeni serverdə repo-dan gələcək)

### 5.1. MatchNewPropertyJob — chunk optimizasiyası

**Fayl:** `app/Jobs/MatchNewPropertyJob.php`

Mövcud kod:
```php
$requests = CustomerRequest::where('is_active', true)
    ->with(['customer', 'user'])
    ->get();

foreach ($requests as $request) {
    // ...
}
```

Dəyişdirilməli kod:
```php
CustomerRequest::where('is_active', true)
    ->with(['customer', 'user'])
    ->chunk(200, function ($requests) use ($property, $telegram, &$matchCount) {
        foreach ($requests as $request) {
            // eyni məntiq
        }
    });
```

Bu dəyişiklik 2000+ user-də RAM-ın dolmasının qarşısını alır.

### 5.2. Queue worker sayını artır

**Fayl:** `docker/docker-compose.yml`

`queue_default` servisinin `command` sətirini dəyiş:
```yaml
command: php artisan queue:work redis --queue=default,notifications --tries=3 --sleep=3 --max-jobs=1000 --workers=4
```

### 5.3. DB index-lər əlavə et

Aşağıdakı migration-u yarat və işlət:

```bash
docker exec makler_app php artisan make:migration add_performance_indexes
```

Migration məzmunu:
```php
public function up(): void
{
    Schema::table('customer_requests', function (Blueprint $table) {
        $table->index('is_active');
        $table->index(['user_id', 'is_active']);
    });

    Schema::table('property_matches', function (Blueprint $table) {
        $table->index('status');
        $table->index(['user_id', 'status']);
        $table->index(['customer_request_id', 'status']);
        $table->index('dismissed_at');
        $table->index('created_at');
    });

    Schema::table('properties', function (Blueprint $table) {
        $table->index('is_owner');
        $table->index(['is_owner', 'is_business']);
        $table->index('updated_at');
    });
}
```

```bash
docker exec makler_app php artisan migrate --force
```

---

## 6. Artisan komandaları

```bash
# Cache təmizlə
docker exec makler_app php artisan optimize:clear

# Migration işlət
docker exec makler_app php artisan migrate --force

# Queue işlədiyini yoxla
docker logs makler_queue_default --tail 20

# Scraper işlədiyini yoxla
docker logs makler_scraper --tail 20

# Scheduler işlədiyini yoxla
docker logs makler_scheduler --tail 20
```

---

## 7. Nginx proxy quraşdırma

Köhnə serverdən `nginx-proxy` konteynerini köçür və domenləri yönləndir.

SSL Cloudflare Flexible rejimindədir — serverdə SSL lazım deyil.

---

## 8. Yoxlama siyahısı

- [ ] Bütün konteynerlər işləyir (`docker ps`)
- [ ] `makler_app` — PHP/Octane
- [ ] `makler_queue_default` — Queue worker
- [ ] `makler_scraper` — Bina.az scraper
- [ ] `makler_scheduler` — Cron jobs
- [ ] `makler_socket` — Socket.IO
- [ ] `makler_mysql` — MySQL 8
- [ ] `makler_redis` — Redis
- [ ] Sayt açılır
- [ ] Telegram bildirişi işləyir (Settings → Test göndər)
- [ ] Canlı elanlar gəlir
