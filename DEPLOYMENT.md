# Laravel API deployment

Production API base URL: `https://api.trustforevermarriagebureau.com/api`

Set these values in the Laravel VPS `.env` manually. Keep the VPS `.env` and all secrets out of source control:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.trustforevermarriagebureau.com
CORS_ALLOWED_ORIGINS=https://www.trustforevermarriagebureau.com,https://app.trustforevermarriagebureau.com
SANCTUM_STATEFUL_DOMAINS=www.trustforevermarriagebureau.com,app.trustforevermarriagebureau.com
SESSION_DOMAIN=.trustforevermarriagebureau.com
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
```

Keep local development origins in the VPS CORS and Sanctum lists only if that deployed environment genuinely needs them. The comma-separated CORS value must not contain spaces because `config/cors.php` splits it directly.

The admin uses Sanctum stateful session authentication: it requests `/sanctum/csrf-cookie`, sends credentials and `X-XSRF-TOKEN`, then uses `/api/login`, `/api/me`, and `/api/logout`. It does not use bearer tokens. CORS allows credentials, and the shared session cookie domain must cover both the admin and API subdomains.

After changing VPS values, clear/rebuild Laravel's cached configuration using the normal deployment procedure and reload the app. Confirm HTTPS is forwarded correctly by the proxy so secure session cookies are issued. Public profile image URLs are generated from `APP_URL`; ensure the existing `storage/app/public/matrimonial-profiles/avatar.jpg` fallback file is deployed and create/verify the public storage link with `php artisan storage:link`. Confirm `https://api.trustforevermarriagebureau.com/storage/matrimonial-profiles/avatar.jpg` returns the image. Uploaded profile images also need to remain in public storage on the VPS or its configured shared storage.

DNS, TLS, Vercel domain assignment, and VPS updates are manual deployment steps and are not performed by this repository change.
