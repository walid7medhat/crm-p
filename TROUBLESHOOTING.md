# Troubleshooting

## Opening the wrong project

This app (crm-p) must be opened at **its own URL**. If you open `http://localhost` you may see another project.

- **MAMP:** Open **http://localhost:8888/crm-p/public** (or your MAMP port + `/crm-p/public`).
- **php artisan serve:** Run `php artisan serve` and open the URL it prints (e.g. http://127.0.0.1:8000).

Set `APP_URL` in `.env` to that exact URL so links and redirects stay in this project.

---

## "Server error" and API 404s

### 1. Start MySQL (MAMP)

Your `.env` uses MySQL on **port 8889**. The app will show "Server error" if MySQL is not running.

- Open **MAMP** and click **Start** for the servers.
- Confirm MySQL is running on port **8889** (MAMP → Preferences → Ports).

### 2. Run Laravel

The frontend uses relative URLs (`/api`), so it works on **any port**. From the project root:

```bash
php artisan serve
```

Then open the URL shown (e.g. **http://127.0.0.1:8000**). To use a specific port:

```bash
php artisan serve --port=8080
```

### 2b. 404 on `/api/auth/login` or `/api/sidebar/counts` when using **npm run dev** (Vite)

If you open the app at the **Vite dev server** URL (e.g. **http://127.0.0.1:5173** or **http://127.0.0.1:8001**), the browser sends `/api` requests to that same port. Vite does not serve Laravel routes, so you get **404** on login and sidebar counts.

**Fix:** Use **two terminals**:

1. **Terminal 1 – Laravel (API):**
   ```bash
   ./kill-server.sh   # if ports are in use
   php artisan serve
   ```
   Leave it running (default: **http://127.0.0.1:8000**).

2. **Terminal 2 – Vite (frontend):**
   ```bash
   npm run dev
   ```
   Open the URL Vite prints (e.g. http://127.0.0.1:5173 or 8001). Vite **proxies** `/api` to Laravel on port 8000, so login and sidebar counts work.

If your Laravel server runs on a **different port** (e.g. 8011), add to `.env`:

```env
VITE_DEV_API_TARGET=http://127.0.0.1:8011
```

Then **restart** `npm run dev` (stop with Ctrl+C and run again).

### 3. Clear config cache

If you changed `.env` (e.g. DB or cache), clear config cache:

```bash
php artisan config:clear
php artisan cache:clear
```

### 4. (Optional) Show detailed errors

In `.env` set:

```env
APP_DEBUG=true
```

Reload the page to see the real exception instead of "Server error". Set back to `false` when done.

---

## "Address already in use" when running `php artisan serve`

Ports 8000–8010 are already in use (e.g. from a previous `php artisan serve` or another app).

**Fix:** In the project root, run:

```bash
./kill-server.sh
```

Then run:

```bash
php artisan serve
```

Or use another port:

```bash
php artisan serve --port=8011
```

Then open **http://127.0.0.1:8011** in your browser. If you use **npm run dev**, set in `.env`: `VITE_DEV_API_TARGET=http://127.0.0.1:8011` and restart `npm run dev`.

---

## Summary checklist

| Issue              | Fix                                                                 |
|--------------------|---------------------------------------------------------------------|
| Server error       | Start MAMP MySQL (port 8889)                                        |
| 404 on /api/*      | Run `php artisan serve`; if using `npm run dev`, see section 2b above |
| 404 on login (Vite)| Run Laravel in one terminal, `npm run dev` in another; proxy sends /api to Laravel |
| Address already in use | Run `./kill-server.sh` then `php artisan serve` (or use `--port=8011`) |
| Stale config       | `php artisan config:clear`                                          |
| Permissions policy | Browser warning only; safe to ignore                               |
