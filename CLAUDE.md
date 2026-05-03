# CLAUDE.md

Backend for the **Flock** church leadership mobile app.

## Tech Stack

- Laravel (PHP) — framework root
- Vue 3 + Inertia.js — web/admin views
- Tailwind CSS
- Chart.js + vue-chartjs — dashboards
- Puppeteer — likely for PDF/report generation

## Commands

```bash
composer install         # PHP deps
npm install              # JS deps
php artisan serve        # Dev server
npm run dev              # Vite dev
php artisan migrate      # DB migrations
php artisan test         # Run tests
```

---

## Related Projects

This project lives in a multi-project workspace at `~/Projects/`. Other projects to be aware of:

### Paired sibling

- **`~/Projects/flock/app`** — **This server's mobile client.** Expo/React Native + TypeScript (package name `flock`). Uses Zustand for auth, TanStack Query v5 for server state. Portrait-only. File-based routing via Expo Router. Consumes this backend's API. Look there when:
  - Investigating how the mobile app calls an endpoint
  - Matching API response fields to mobile UI
  - Coordinating schema or response shape changes
  - Reproducing a mobile-reported bug

### Other projects in the workspace (unrelated products, same developer)

- `~/Projects/poimen/app` + `~/Projects/poimen/server` — Poimen church management (Expo + Laravel)
- `~/Projects/bishops-school/web` + `~/Projects/bishops-school/api` — course tracker (Next.js + Laravel)
- `~/Projects/voice-song-projection` — ProPresenter auto-advance tool (Python)
- `~/Projects/pharmacist-evolve` — pharmacist mentoring site (Laravel+Vue)
