# Local read-only k6 tests

These scripts call the local CRM only (`http://127.0.0.1:8000` or `http://localhost`). They refuse any other base URL. They send GET requests only. They do not log in.

## Provide tokens without committing secrets

1. Copy the example file:

   `cp data/tokens.example.json data/tokens.json`

2. Put existing JWTs in `data/tokens.json`. That file is gitignored.

   You can copy a token from the browser after a normal local sign-in: DevTools → Application → Local Storage → `token`.

   Rebuild the gitignored fixture from existing local users. Each user gets only lead IDs `canViewLead()` allows. This does not call `POST /api/auth/login`.

   ```bash
   php loadtests/k6/scripts/build-local-fixture.php
   ```

   Do not print that file, commit it, or paste tokens into chat.

## Rate limiter

`throttle:300,1` wraps authenticated routes outside `jwt.auth`, so the limit is per client IP: 300 requests per minute. This test does not change or bypass that middleware.

429 responses are reported as `count_429` / `rate_limited`. They are not application errors. Application errors are HTTP 5xx and connection failures (`status 0`) only. HTTP 403 is reported as `count_403` and is not a capacity failure.

One load-generator IP cannot represent 200–300 concurrent users. Those users share a single 300-request/minute bucket, so the test measures the limiter, not the CRM.

## Later production run (not enabled)

Do not point this script at production. `read-only.js` refuses any base URL that is not `127.0.0.1` or `localhost`. A production run needs a separate explicit approval and a host allowlist change. It still must not change the application rate limiter.

When that is approved, run the same GET-only script from several source IPs so each generator stays under 300 requests/minute:

- Use separate machines, or k6 distributed execution where each load zone has its own egress IP.
- Split the virtual users across those IPs. Example: 6 generators × 50 users, each kept under 300 requests/minute, instead of 300 users from one IP.
- Keep login out of the measured scenario. Supply existing JWTs per user, with lead IDs that user is allowed to open.
- Read `count_429` on its own. A high 429 count means that IP exceeded the limiter. It is not a 5xx capacity result.
- Do not raise, disable, or whitelist the limiter in application config for the test.

3. Run from this directory:

   ```bash
   k6 run -e VUS=5 -e DURATION=1m read-only.js
   ```

`BASE_URL` defaults to `http://127.0.0.1:8000`. Override only with another localhost URL:

```bash
k6 run -e BASE_URL=http://127.0.0.1:8000 -e VUS=5 -e DURATION=1m read-only.js
```
