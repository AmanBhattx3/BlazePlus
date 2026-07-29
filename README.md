# BlazePlus

Internal employee directory with admin-gated signup, department chat room placeholders, and level-based (employee/manager/senior) contact visibility.

## Setup

1. Import the schema:
   ```
   mysql -u root -p < schema.sql
   ```
2. Open `config.php` and set your DB credentials (default assumes `root` / no password / `localhost`).
3. Point your local PHP server at this folder, e.g.:
   ```
   php -S localhost:8000
   ```
4. Visit `http://localhost:8000/signup.php`

## Flow

1. **signup.php** → collects name, email, phone, password → inserted into `unverified` → redirects to `login.php`
2. **login.php** → checks `banned` (blocks with contact message) → checks `users` (verified, logs straight in) → checks `unverified`:
   - no `emp_no` yet → **verify.php**
   - `emp_no` already submitted → **waiting.php**
3. **verify.php** → collects emp_no, DOB, department, role → stamps `verify_submitted_at` → **waiting.php**
4. **waiting.php** → shows a countdown (`VERIFY_TIMEOUT_MINUTES` in `config.php`, currently 5 — bump to 1440 for 24hr later) and polls `check_status.php` every 4s
5. **Admin dashboard** (`admin/dashboard.php`) → lists everyone with a submitted `emp_no`, shows name/department/emp_no + "View details" modal → Accept / Reject
   - Accept → row copied into `users` + `verify` (verified = verify + users, per spec), removed from `unverified`
   - Reject → row copied into `banned` with a reason (default "unknown identity"), removed from `unverified`
   - Timer expiry (checked on each poll) → row deleted from `unverified`, must sign up again
6. **index.php** → directory grid (level-based visibility), Transfers section (placeholder), 4 chat room cards per department (placeholders — not built yet)

## Admin login

Separate login at `admin/login.php`.
Seeded credentials (in `schema.sql`): username `admin`, password `Admin@123`.

## Not built yet (flagged, not faked)

- Actual chat room functionality (Discord-style rooms) — UI cards exist, marked "Coming soon"
- Transfers module — placeholder only
- Request-a-call feature — future scope, not started
- Manager's own hide/visibility rule — spec never defined whether managers can hide from employees below them, so managers are currently always fully visible

## Notes

- Passwords hashed with PHP `password_hash()` / verified with `password_verify()`.
- Department and role are locked after `verify.php` — not editable from the profile (per spec, since chat rooms depend on them).
- `hide_contact` in `users` table lets employees/seniors hide their phone from same-level peers (per your level rules). Not yet exposed as a profile toggle UI — add a settings page to let users flip this column.
