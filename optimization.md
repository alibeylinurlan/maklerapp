# Makler App — Optimizasiya Planı

## Stress Test Nəticəsi (2026-04-23, 50 VU)
- p(95) cavab vaxtı: 3.83s (hədəf: <2s)
- Ortalama: 1.12s
- Xəta: 0%
- makler_app pik CPU: 384% (4 core dolu)
- makler_mysql pik CPU: 105%

---

## Problemlər və Həllər

### 1. Octane Worker Sayı Az
**Problem:** FrankenPHP default worker sayı azdır, 50 VU-da 4 core tam dolur.
**Həll:** `--workers` sayını artır.
**Fayl:** `docker/docker-compose.yml` → `app` service `command`
**Status:** ✅ Həll edildi — `--workers=12` əlavə edildi (6 core × 2)

---

### 2. MySQL Sorğular Yavaş
**Problem:** Properties səhifəsindəki sorğular yük altında 105% CPU yeyir — çətin ki index tam işlənsin.
**Həll:** Ən çox işlədilən sorğuları tap, çatışmayan index-ləri əlavə et.
**Fayl:** `app/` altındakı Property model sorğuları
**Status:** ☐ Həll edilməyib

---

### 3. Properties Səhifəsi Cache Yoxdur
**Problem:** Hər request-də MySQL-dən tam sorğu gedir, heç bir cache mexanizmi yoxdur.
**Həll:** Redis cache ilə sorğu nəticələrini 30-60 saniyə cache et.
**Status:** ☐ Həll edilməyib

---

### 4. Response Time Pik: 13.63s
**Problem:** Bəzi requestlər 13 saniyəyə qədər uzanır — çox yüksəkdir.
**Həll:** Yavaş sorğuları MySQL slow query log ilə tap.
**Status:** ☐ Həll edilməyib

---

## Priority Sırası
1. Octane worker sayı (ən sürətli həll, dərhal effekt)
2. MySQL slow query log aç, problemli sorğuları tap
3. Index-lər əlavə et
4. Redis cache tətbiq et
