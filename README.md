# Secure Notes Backend

Production-ready backend for the Secure Notes interview task. The public HTTP boundary is written in object-oriented PHP 8.5. A private Node.js/Mongoose service owns MongoDB access and is never exposed directly to browsers.

- Live API: https://note-api.51-75-162-34.sslip.io/api/ready
- Live frontend: https://secure-notes-zia.netlify.app
- Frontend source: https://github.com/zia11004/secure-notes-frontend

## Capabilities

- Registration and login by numeric username or email
- Immutable usernames beginning at `110001`
- Argon2id password hashing and JWT authentication
- Private note CRUD with ownership enforcement
- Public posts and per-author post aggregation
- Built-in user and administrator roles
- Custom role creation, permission assignment, and user-role assignment
- User and interest-group administration
- Strict request validation, centralized JSON errors, rate limiting, and exact-origin CORS
- Transactional user deletion across users, notes, and posts
- Paginated lists and indexed MongoDB queries

There are no seeded users, passwords, or administrator credentials.

## Architecture

```text
api/                  Public PHP 8.5 API
  routes/             Route declarations and guards
  src/Controller/     HTTP translation only
  src/Services/       Business and authorization rules
  src/Infrastructure/ Private service client and rate limiter
  src/Exception/      Central error handling
  bin/                Administrator promotion command
data-service/         Private Express/Mongoose service
  src/models/         MongoDB schemas and indexes
  src/repositories/   Queries and aggregation pipelines
  src/services/       Transactional operations
docker/               Local MongoDB replica-set initialization
deploy/native/        Rocky Linux production templates
tests/                End-to-end feature test
```

The browser calls only the PHP API. PHP authenticates and authorizes the request before using a separate internal token to call the loopback/private data service. MongoDB runs as an authenticated one-member replica set because account deletion uses a transaction.

## Local Docker setup

Requirements: Docker with Compose and Python 3.9 or newer.

```bash
python3 scripts/init-env.py
docker compose up --build -d
curl http://127.0.0.1:8000/api/ready
```

The initialization script creates independent random MongoDB credentials, a JWT secret, and a private service token. It never prints or overwrites existing secrets. Filled `.env` files are ignored by Git.

Useful commands:

```bash
docker compose ps
docker compose logs --tail=100 api data-service gateway mongo
docker compose exec -T data-service npm run indexes
docker compose down
```

`docker compose down` retains the MongoDB volume. Do not remove volumes unless permanent local data deletion is intended.

## Environment

Start from `.env.example`, `api/.env.example`, and `data-service/.env.example`.

| File | Important variables |
| --- | --- |
| `.env` | `MONGO_USERNAME`, `MONGO_PASSWORD`, `MONGO_APP_USERNAME`, `MONGO_APP_PASSWORD` |
| `api/.env` | `APP_ENV`, `APP_DEBUG`, `APP_URL`, `JWT_SECRET`, `DATA_SERVICE_URL`, `DATA_SERVICE_TOKEN`, `FRONTEND_ORIGINS`, `RATE_LIMIT_PER_MINUTE` |
| `data-service/.env` | `NODE_ENV`, `HOST`, `PORT`, `MONGODB_URI`, `INTERNAL_SERVICE_TOKEN` |

The API and data service tokens must match. Production mode requires HTTPS for the public API and every allowed frontend origin.

## API overview

All routes use the `/api` prefix.

| Method and path | Access | Purpose |
| --- | --- | --- |
| `GET /ready` | Public | API, private service, and database readiness |
| `POST /auth/register` | Public | Create a regular account |
| `POST /auth/login` | Public | Login by email or username |
| `GET /auth/me` | User | Current profile and permissions |
| `GET, POST /notes` | Permission based | List or create notes |
| `GET, PATCH, DELETE /notes/{id}` | Owner/permission based | Read or change a note |
| `GET, POST /posts` | Public read/authenticated write | List or create posts |
| `GET /users/{username}/posts` | Public | Aggregated author post list |
| `PATCH, DELETE /posts/{id}` | Author | Change a post |
| `GET, POST /admin/users` | `users.manage` | Manage users |
| `GET /admin/interests` | `interests.read` | Interest reports |
| `GET, POST /admin/roles` | Built-in admin | Manage custom roles |
| `GET /admin/permissions` | Built-in admin | Available permission keys |

JSON writes require `Content-Type: application/json`. Protected routes require `Authorization: Bearer <token>`. List routes accept validated `page` and `per_page` parameters. Errors use one consistent shape:

```json
{"error":{"code":"VALIDATION_FAILED","message":"Invalid title."}}
```

Passwords require 6–128 bytes and confirmation for registration or password changes. Email addresses are validated by both frontend and API.

## Administrator setup

Register an account first. A fresh database assigns `110001` to the first successful registration. Promote it locally with:

```bash
docker compose exec -T api php bin/promote-admin.php 110001
```

On the deployed Rocky Linux server:

```bash
sudo /opt/remi/php85/root/usr/bin/php /var/www/note-taking-app/api/bin/promote-admin.php 110001
```

Sign out and back in after promotion. The command also accepts an email address.

## Tests

```bash
docker compose run --rm --no-deps -v "$PWD/api/tests:/app/tests:ro" api composer test
docker compose run --rm --no-deps -v "$PWD/data-service/test:/app/test:ro" data-service npm test
python3 tests/e2e.py
```

The end-to-end test covers authentication, ownership, permissions, custom roles, pagination, public posts, interest groups, and transactional cleanup.

## Production deployment

The current backend is installed natively at `/var/www/note-taking-app` on Rocky Linux; production does not use Docker. The service map is:

| Component | Binding |
| --- | --- |
| Nginx and PHP 8.5 FPM | Public HTTPS API |
| `note-taking-data.service` | `127.0.0.1:3001` |
| MongoDB replica set `rs0` | `127.0.0.1:27017` |
| `note-taking-backup.timer` | Daily compressed backup, seven-day retention |

Native service, PHP pool, Nginx, MongoDB, and backup templates are under `deploy/native`. Production secrets must remain in protected server environment files and must never be committed.
