# Lab 6 Setup

This project is split into the LavaLust API in `LavaLust` and the React app in `react-frontend`. The browser talks only to the API; only the API connects to MySQL.

## Aiven Database

1. Create an Aiven for MySQL service. Keep the host, port, database name, username, and password private.
2. Download the service CA certificate from Aiven. The API uses it to verify the encrypted MySQL connection.
3. Run [`database/schema.sql`](database/schema.sql). It creates and selects `lavalust_lab6`, then creates `users`, `refresh_tokens`, and `products`.

The SQL file can also be imported into WAMP/phpMyAdmin without first creating a database. If Aiven does not allow `CREATE DATABASE`, select the database Aiven provides, remove the first `CREATE DATABASE` and `USE` statements before running the remaining schema, and use that selected database name for `DB_NAME`.

### Existing Database: Per-User Products

If the old shared `products` table already exists, do not rerun the full schema. The migration scripts explicitly select `lavalust_lab6`; change that `USE` statement only if your actual Aiven database has a different name. First run [`database/004_add_product_owner.sql`](database/004_add_product_owner.sql), then choose which account owns the existing shared products:

```sql
SELECT id, username, email FROM users ORDER BY id;
UPDATE products SET user_id = <OWNER_USER_ID> WHERE user_id IS NULL;
SELECT COUNT(*) FROM products WHERE user_id IS NULL;
```

Replace `<OWNER_USER_ID>` with the selected account's numeric ID. The count must be `0`; then run [`database/005_finalize_product_owner.sql`](database/005_finalize_product_owner.sql). If existing products belong to different users, assign rows individually by product ID before finalizing. Back up the database first.

## Run Locally

Requirements: PHP with `pdo_mysql`, Node.js, and npm. Composer is not required; this LavaLust checkout has no `composer.json` and includes its API library.

In PowerShell, from `LavaLust`:

```powershell
Copy-Item .env.example .env
```

Set these values in `.env` using the Aiven connection details. Set `DB_SSL_CA` to the local path of the downloaded CA certificate, and set `FRONTEND_ORIGIN=http://localhost:5173`.

```dotenv
APP_ENV=development
DB_DRIVER=mysql
DB_HOST=your-aiven-host
DB_PORT=your-aiven-port
DB_USER=your-aiven-user
DB_PASSWORD=your-aiven-password
DB_NAME=lavalust_lab6
DB_CHARSET=utf8mb4
DB_SSL_CA=C:/path/to/ca.pem
JWT_SECRET=replace-with-a-random-secret-at-least-32-characters
REFRESH_TOKEN_KEY=use-a-different-random-secret-at-least-32-characters
FRONTEND_ORIGIN=http://localhost:5173
```

Generate separate secrets locally with `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`. Never commit `.env` or send database credentials in chat.

Start the API from `LavaLust`:

```powershell
php -S 127.0.0.1:8080 -t public public/index.php
```

In a second terminal, from `react-frontend`:

```powershell
Copy-Item .env.example .env
npm install
npm run dev
```

Open the Vite URL, create an account, then add, edit, and delete products. The access token is held in memory, refresh tokens are rotated by the API, and logout revokes the refresh token.

## API Routes

| Method | Route | Authentication |
| --- | --- | --- |
| POST | `/api/auth/register` | Public |
| POST | `/api/auth/login` | Public |
| POST | `/api/auth/refresh` | Refresh token in JSON body |
| POST | `/api/auth/logout` | Bearer access token |
| GET | `/api/products` | Bearer access token |
| POST | `/api/products` | Bearer access token |
| PUT / PATCH | `/api/products/{id}` | Bearer access token |
| DELETE | `/api/products/{id}` | Bearer access token |

Product requests use JSON fields `product_name`, `description`, `price`, and `quantity`. The API returns LavaLust `Api` JSON responses; unauthenticated product requests receive HTTP 401.

## Deploy to Render

Create the backend as a Render **Web Service** from the backend GitHub repository and select Docker. Set the root directory to the repository root. The Dockerfile serves `public/` and listens on Render's `PORT` (default 10000).

Use [`render.env.example`](render.env.example) as the variable checklist; enter the values individually in the Render dashboard, not as a committed `.env` file. Add the Aiven CA certificate under Render **Secret Files** at `/etc/secrets/aiven-ca.pem`. Set `DB_NAME` to the Aiven database containing the schema, `FRONTEND_ORIGIN` to the deployed frontend's exact HTTPS origin, and use fresh, separate random values for `JWT_SECRET` and `REFRESH_TOKEN_KEY`.

Create the frontend as a separate Render **Static Site** from the `react-frontend` repository. Use build command `npm ci && npm run build`, publish directory `dist`, and build environment variable `VITE_API_URL=https://your-api.onrender.com`. After the first deploy, put its exact URL in the backend's `FRONTEND_ORIGIN` and redeploy the backend.

The backend and frontend repositories can be created separately from their respective folders. Do not put Aiven passwords, `.env`, or production signing secrets in either GitHub repository.