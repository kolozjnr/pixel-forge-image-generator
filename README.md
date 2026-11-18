# PixelForge Image Studio

PixelForge is an AI image-generation application built with Laravel, Vue 3, Inertia, and TypeScript. It uses Pixelforge for image generation, PostgreSQL as the production system of record, Laravel queues for asynchronous work, and private object storage for generated PNGs.

## Product capabilities

- User registration and sign-in through Laravel Fortify.
- Responsive image workspace with prompts, three image sizes, three quality levels, and up to four reference image URLs.
- Server-fetched Pixelforge pricing and live estimates with a 25% service markup.
- Signed webhooks, duplicate-event protection, polling fallback, and terminal generation-state handling.
- Searchable and filterable generation history and owner-authorized image delivery.
- Simulated credit purchases, reservations, a nano-USD ledger, and basic admin usage reporting.

## Technology and architecture

- Laravel 13, PHP 8.4.1 or newer (match Composer's platform requirement), Vue 3, TypeScript, Inertia v3, Tailwind CSS v4, and Lucide Vue.
- PostgreSQL is recommended for production; SQLite can be used locally.
- Database queues are suitable for development; SQS is configured for production.
- Generated images are stored privately on S3 in production or locally during development.
- Typesense environment settings exist, but history search currently uses PostgreSQL.

```text
Vue/Inertia browser
  ├─ estimate request ─> Laravel pricing service ─> Pixelforge GET /pricing
  └─ generation submit ─> local record + credit reservation ─> Pixelforge POST /generations

Pixelforge webhook ─> raw-body HMAC validation ─> deduplicate event ─> sync generation
                                                           ├─ settle actual usage
                                                           └─ download/store PNG

Laravel scheduler ─> poll active generations + reconcile UTC provider usage
PostgreSQL ─> users, generations, ledger, webhook events, reconciliation snapshots
Private S3/local disk ─> generated images
```

`PixelforgeClient` owns external HTTP communication. Controllers handle HTTP boundaries; services handle pricing, submission, lifecycle synchronization, credit accounting, and settlement. Provider keys and webhook secrets stay server-side and must never be included in frontend props or `VITE_` variables.

## Generation lifecycle

1. A signed-in user submits a prompt (up to 4,000 characters), image size, quality, and optional HTTP/HTTPS reference URLs (up to four).
2. The server fetches current pricing, calculates the maximum estimated customer cost, checks available credits, creates a local generation, and reserves credits.
3. The server sends the request with a unique idempotency key and local generation ID metadata. Creating the local record first allows a webhook to match even if it arrives before the API response.
4. Pixelforge reports `queued`, `processing`, then `succeeded` or `failed`. Terminal states cannot regress. Webhook IDs are unique so retried events do not repeat processing.
5. Webhooks are verified with HMAC-SHA256 over `<timestamp>.<raw body>`, constant-time comparison, and a five-minute timestamp window. Signed `ping` events are acknowledged.
6. Queued polling jobs provide fallback for missed webhooks. Generation detail pages refresh while work is active.
7. Success triggers billing from actual token usage and generation-time rates. Failure releases reservations and is not billed.
8. Provider image URLs expire after 24 hours. A job downloads the PNG into private storage for later authenticated viewing.

## Pricing and billing

Rates and token assumptions come from `GET /v1/pricing` and are cached for 15 minutes. Estimates include approximate Unicode text token counts and provider variance, exactly 765 input tokens per reference image, exact output tokens by quality/size, and the reasoning range. Customer estimates apply a 25% markup.

Vue displays estimates returned by the server; it does not determine charges. Final charges use actual successful-generation usage and the rates captured for that generation. The settlement path represents money as integer nano-USD (`1 USD = 1,000,000,000 nano-USD`), not floats.

Balances start at zero. The Credits page offers $5, $10, $25, and $50 **simulated** purchases. No payment is collected. Generation submission reserves the maximum estimate; settlement charges the actual amount and releases the reservation. This simulation is not a production payment flow.

## Local development

### Requirements

- PHP 8.4.1 or newer, Composer, Node.js, and npm.
- SQLite or PostgreSQL.
- Pixelforge development API key and its webhook signing secret.

### Setup

```powershell
composer install
Copy-Item .env.example .env
```

For SQLite, set `DB_CONNECTION=sqlite` in `.env` and ensure `database/database.sqlite` exists. Alternatively configure PostgreSQL. Set `PIXELFORGE_API_KEY` and `PIXELFORGE_WEBHOOK_SECRET`, then run:

```powershell
php artisan key:generate
php artisan migrate
npm install
npm run build
composer run dev
```

Use separate Pixelforge credentials for each environment. Never commit `.env` or expose secrets in browser code.

## Configuration

| Variable | Purpose |
| --- | --- |
| `PIXELFORGE_BASE_URL` | API base URL; defaults to `https://pixelforge.promptedit.dev/v1`. |
| `PIXELFORGE_API_KEY` | Server-only bearer credential. |
| `PIXELFORGE_WEBHOOK_SECRET` | Signing secret for the webhook on that API key. |
| `PIXELFORGE_TIMEOUT`, `PIXELFORGE_CONNECT_TIMEOUT` | Provider HTTP timeouts in seconds. |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Database connection. |
| `QUEUE_CONNECTION` | Use `database` locally and `sqs` in AWS production. |
| `FILESYSTEM_DISK` | Use `local` locally and `s3` in production. |
| `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET` | AWS credentials, region, and private image bucket. |
| `SQS_PREFIX`, `SQS_QUEUE`, `SQS_SUFFIX` | Queue configuration. |
| `TYPESENSE_HOST`, `TYPESENSE_PORT`, `TYPESENSE_PROTOCOL`, `TYPESENSE_API_KEY` | Reserved settings; Typesense is not yet integrated. |

`.env.example` uses production-oriented PostgreSQL/S3 values. Override them in local `.env` as needed.

### Neon PostgreSQL

Laravel's native `pgsql` PDO driver is used; no Neon-specific Node.js database driver is required for this PHP application. The PostgreSQL connection reads `DATABASE_URL` first and falls back to Laravel's `DB_URL` or the individual `DB_HOST`/`DB_DATABASE` settings. Neon connection strings should use TLS (`sslmode=require`). For server runtimes with concurrent requests or workers, use Neon's pooled connection URL for the app runtime; use the direct connection URL for database administration if needed. Keep the connection string in server-side `.env`/secret configuration only.

To link this repository to an existing Neon project with the Neon CLI, initialize the project/branch configuration, deploy its declared services, and pull the branch environment into `.env`. The app requires the resulting PostgreSQL connection string to be available as `DATABASE_URL`, with `DB_CONNECTION=pgsql`. After provisioning, run migrations and verify with `php artisan migrate:status` and an authenticated app request. Do not commit generated Neon credentials or local environment files.

## Development webhooks

Pixelforge requires a public HTTP/HTTPS URL; localhost and private addresses are rejected. Run Laravel on port 8000 and expose it from another terminal:

```powershell
php artisan serve --port=8000
ngrok http 8000
```

Ngrok requires an account and configured authtoken. Set the Pixelforge dashboard webhook URL to the current HTTPS forwarding hostname plus `/webhooks/pixelforge`:

```text
https://YOUR-TUNNEL-HOST/webhooks/pixelforge
```

Keep the tunnel running while testing. Free tunnel hostnames may change; update the dashboard URL if they do. The local API key and secret must match the key used to configure that webhook.

## Production deployment

The target AWS-compatible setup uses an app host such as ECS/Fargate, managed PostgreSQL, SQS, private S3, CloudWatch, and a secret manager.

1. Configure `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY`, `APP_URL`, database and AWS settings, and Pixelforge secrets.
2. Use a dedicated production Pixelforge key. Set its webhook URL to `https://your-domain/webhooks/pixelforge`.
3. Build frontend assets and deploy the code.
4. Run migrations:

   ```bash
   php artisan migrate --force
   ```

5. Keep queue workers running:

   ```bash
   php artisan queue:work --sleep=3 --tries=5 --timeout=120
   ```

6. Invoke the scheduler every minute:

   ```bash
   php artisan schedule:run
   ```

7. Use `/up` as the health endpoint and send logs to CloudWatch or the hosting platform.

The schedule polls active generations every five minutes and runs daily provider usage reconciliation. Webhook handling should remain quick; longer work runs on queue workers.

## Operations and troubleshooting

**Webhook events are missing:** Confirm the tunnel/app is running, the URL includes `/webhooks/pixelforge`, and the webhook secret matches the API key that created the generation. Inspect provider delivery attempts and application logs.

**Generations remain active:** Check queue workers, `QUEUE_CONNECTION`, scheduler execution, provider IDs, and failed jobs. Polling relies on the queue backend.

**Images are unavailable:** Confirm the generation succeeded, the signed URL has not expired, and workers can reach the configured provider host and storage. Inspect image job logs.

**Run provider usage reconciliation:** The provider usage endpoint is the source of truth for provider costs, uses UTC dates, and accepts at most 92 days per request:

```bash
php artisan pixelforge:reconcile-usage --date=2026-11-17
```

The date is UTC. Customer charges include markup, so they differ from provider costs.

**Admin access:** Admin routes require `users.is_admin = true`. Grant this flag only through a trusted administrative database process; there is no self-service promotion route.

## Security

- Keep Pixelforge and AWS secrets in environment configuration or a secret manager, never frontend assets.
- Verify webhooks against raw request bytes; use constant-time signature comparison and reject timestamps older than five minutes.
- Database uniqueness protects provider IDs, idempotency keys, webhook event IDs, and ledger operations.
- Generation details and private image routes enforce ownership checks.
- Reference URLs are sent to Pixelforge; this app does not fetch user-supplied reference URLs.
- Image download jobs restrict fetches to the configured Pixelforge HTTPS host.
- Keep production S3 objects private.

## Current scope and limitations

- Search is PostgreSQL-backed. Typesense configuration exists, but the client/indexing integration is not implemented.
- Admin reports provide basic provider usage, customer charges, daily totals, and generation counts. Full profit/margin reconciliation remains outstanding.
- Credit purchases are simulated; a payment processor is not integrated.
- PostgreSQL, SQS, S3, secrets, public webhook configuration, workers, and scheduler must be provisioned and verified in the target environment.
- Run migrations, automated tests, and a production frontend build on a PHP runtime meeting Composer's requirement before treating the system as production-ready.
