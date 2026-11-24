# دخل‌وخرج

برنامه‌ی فارسی مدیریت هزینه‌ی اتاق‌های مشترک با Laravel 13، Vue 3، Sanctum و MySQL. خرید موجودی‌دار، خرج مشترک، سهم هر نفر، مانده‌ی دوبه‌دو، ریزحساب و پرداخت واقعی در یک دفتر رویداد ثبت می‌شوند.

## پیش‌نیازها

- PHP 8.3 یا جدیدتر و Composer
- Node.js 20.19+ یا 22.12+ و npm
- MySQL 8+ یا MariaDB سازگار با migrationهای Laravel

## نصب و اجرا

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
npm install
```

یک دیتابیس با نام `dakhl_kharj` در MySQL بسازید و مقادیر اتصال را در `.env` تنظیم کنید. سپس migrationها را اجرا کنید:

```powershell
php artisan migrate
```

برای اجرای هم‌زمان Laravel، صف و Vite:

```powershell
composer run dev
```

برنامه در `http://localhost:8000` در دسترس است. برای build نهایی فرانت‌اند:

```powershell
npm run build
```

## ساخت اولین اتاق

پس از راه‌اندازی، حساب خود را از صفحه‌ی ثبت‌نام بسازید و اولین اتاق را ایجاد کنید. حساب شما هنگام ساخت اتاق به‌عنوان مالک آن ثبت می‌شود.

## تنظیم احراز هویت SPA

ورود و ثبت‌نام با session cookie و Sanctum انجام می‌شود؛ توکن در `localStorage` ذخیره نمی‌شود. فرانت‌اند پیش از ورود، `/sanctum/csrf-cookie` را می‌خواند، کوکی و XSRF را می‌فرستد و APIها زیر `auth:sanctum` محافظت می‌شوند.

برای اجرای پیش‌فرض Laravel و Vite روی یک دستگاه، تنظیمات `.env.example` آماده است. اگر فرانت‌اند را روی origin جداگانه اجرا می‌کنید، میزبان‌ها را در `.env` هماهنگ کنید:

```dotenv
APP_URL=http://localhost:8000
SANCTUM_STATEFUL_DOMAINS=localhost:8000,localhost:5173,127.0.0.1:8000,127.0.0.1:5173
CORS_ALLOWED_ORIGINS=http://localhost:5173,http://127.0.0.1:5173
SESSION_DOMAIN=localhost
```

برای origin جداگانه، فرانت‌اند و API باید از میزبان‌های stateful و دامنه‌ی session سازگار استفاده کنند؛ CORS هم با credential فعال پیکربندی شده است. پس از تغییر `.env`، در صورت فعال بودن cache تنظیمات، `php artisan config:clear` را اجرا کنید.

## قواعد مالی پیاده‌سازی‌شده

- همه‌ی مبالغ، عدد صحیح تومان و در ستون‌های BIGINT هستند.
- مصرف موجودی با مقدار هزارم ذخیره می‌شود؛ عدد و بسته فقط مقدار کامل می‌پذیرند.
- مبلغ مصرف با فرمول تجمعی صحیح تخصیص داده می‌شود تا مجموع مصرف کامل دقیقاً برابر مبلغ خرید شود.
- خرج مشترک بین افراد انتخاب‌شده به‌صورت مساوی باقیمانده را بر اساس شناسه‌ی عضو تقسیم می‌کند یا سهم‌های دستی با جمع برابر مبلغ کل می‌پذیرد.
- خرید موجودی‌دار پیش از مصرف بدهی نمی‌سازد؛ سهم خریدار هم به خودش بدهی نمی‌شود.
- مانده فقط بین همان دو عضو تهاتر می‌شود. تهاتر پول جابه‌جا نمی‌کند؛ پرداخت واقعی رویداد مستقل است.
- مصرف و پرداخت با برگشت منفی و قابل حسابرسی اصلاح می‌شوند. خرید مشترک باطل‌شده و تخصیص‌هایش برای تاریخچه حفظ می‌شوند.
- درخواست‌های مالی `request_id` دارند و برای اتاق lock و transaction می‌گیرند.

## تست و داده‌ی تست

تست‌ها با SQLite حافظه‌ای اجرا می‌شوند و به MySQL محلی نیاز ندارند:

```powershell
php artisan test --compact
```

مجموعه‌ی Feature سناریوهای مبلغ و موجودی، سهم مشترک، تهاتر، پرداخت، برگشت، idempotency، اعتبارسنجی شناسه‌های اتاق، مجوز و فیلترها را پوشش می‌دهد.

## API اصلی

API نسخه‌ی یک زیر `/api/v1` قرار دارد و همه‌ی مسیرهای اتاق به ورود و مالکیت اتاق نیاز دارند:

- `/rooms` و `/rooms/{room}/members` برای اتاق و اعضا
- `/rooms/{room}/purchases` برای خرید موجودی‌دار و خرج مشترک
- `/rooms/{room}/purchases/{purchase}/consumptions` برای ثبت و فیلتر مصرف
- `/rooms/{room}/balances` و `/members/{member}/balance` و `/ledger` برای حساب‌ها
- `/rooms/{room}/settlements` برای پرداخت واقعی و برگشت آن
- `/rooms/{room}/dashboard` برای موجودی، حساب‌های باز و رویدادهای اخیر

## توسعه

```powershell
php artisan route:list --path=api
vendor/bin/pint --dirty
npm run build
```
