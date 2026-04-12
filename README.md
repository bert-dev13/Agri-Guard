# AGRIGUARD

Farm monitoring on **Laravel** + **MySQL**. You need **PHP 8.2+**, **Composer**, **Node + npm**, **MySQL**.

---

## **Run the app (follow this order)**

**A — Clone & install**

```bash
git clone <repo-url>
cd agriguard
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Windows: use `Copy-Item .env.example .env` instead of `cp`.  
If your folder is not `agriguard`, `cd` into the name Git created.

**B — Database**

In MySQL:

```sql
CREATE DATABASE agriguard;
```

**C — `.env`**

Set **`DB_*`** to match MySQL. Set **`APP_URL`** to `http://127.0.0.1:8000`.  
For mail locally: **`MAIL_MAILER=log`**.  
API keys (`OPENWEATHERMAP_API_KEY`, `TOGETHER_API_KEY`) are optional.

**D — Finish**

```bash
php artisan migrate --seed
php artisan storage:link
npm run build
php artisan serve
```

Open **http://127.0.0.1:8000**

---

## **Default login** *(only works after `migrate --seed`)*

| | |
|--|--|
| Email | `admin@agriguard.com` |
| Password | `admin123` |

You can also **Register** a new user on the site.

---

## **Quick fixes**

- Key error → `php artisan key:generate`
- No images → `php artisan storage:link`
- Broken CSS/JS → `npm run build` (or `npm run dev` while coding)
- DB errors → MySQL running + `.env` `DB_*` correct
