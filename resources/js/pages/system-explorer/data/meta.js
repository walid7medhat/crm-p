/** Catalog metadata — regenerate by re-tracing code; do not invent paths. */
export const CATALOG_VERSION = '1.0.0'
export const CATALOG_GENERATED_AT = '2026-10-26'
export const CATALOG_SOURCE = 'Static evidence index curated from repository source (not live DB).'

export const STACK = {
  backend: { name: 'Laravel', versionEvidence: 'composer.json → laravel/framework ^11.31 (lock ~11.52)', path: 'composer.json' },
  frontend: { name: 'Vue 3 + Vite', versionEvidence: 'package.json → vite ^5, @vitejs/plugin-vue', path: 'package.json' },
  auth: { name: 'JWT (tymon/jwt-auth) + Spatie Permission', path: 'config/auth.php, bootstrap/app.php' },
  apiPrefix: '/api',
  spaEntry: 'resources/js/main.js',
  router: 'resources/js/router.js',
  apiRoutes: 'routes/api.php',
  webRoutes: 'routes/web.php',
  schedule: 'bootstrap/app.php → withSchedule',
}
