# InternTrack Database Commands

## Quick Commands Reference

### 1. List All Users in Database
```bash
cd backend
php artisan users:list
```

This will show you a table of all users with:
- ID
- Username
- Role
- Name
- Email
- Active status

---

### 2. Reset Database (Clean Slate)
```bash
cd backend
php artisan db:reset-fresh
```

This will:
1. Drop all existing tables
2. Recreate fresh tables
3. Seed official accounts (including both student logins)
4. Show you a summary of created accounts

**⚠️ WARNING:** This deletes ALL data!

---

### 3. Manual Reset (Alternative)
```bash
cd backend
php artisan migrate:fresh --seed
```

Standard Laravel command to reset and seed. This is the command groupmates should run after `git pull` so seeded accounts exist locally.

Student rows come from `database/seeders/StudentAccountsSeeder.php`.

---

## Created Accounts

After running the reset command, you'll have these accounts:

| Role        | Username   | Password        | Name                           | Section  |
|-------------|------------|-----------------|--------------------------------|----------|
| STUDENT     | 2300600    | interntrack123  | Christian Hero Aboy Valinado   | 4ITD     |
| STUDENT     | 2300592    | interntrack123  | Clarence Montealegre           | 4ITD     |
| FACULTY     | FAC-1001   | interntrack123  | Prof. Marvin M. Bicua         | CCS      |
| COORDINATOR | COR-1001   | interntrack123  | Arcelito C. Quiatchon         | CCS      |
| DIRECTOR    | DIR-1001   | interntrack123  | Prof. Gina M. Oloresisimo     | Director |

---

## Student Account Details

Both student accounts (`2300600` Valinado, `2300592` Montealegre) start with:
- ✅ **pending_placement** internship (AY 2025-2026, Sem 2) — company & coordinator null
- ✅ **0 Progress** (no attendance / journals / documents yet)
- ✅ **Fresh start** — same seed path for teammates after `migrate:fresh --seed`

---

## Troubleshooting

### "Invalid credentials" error on login?

1. **Run the list command first:**
   ```bash
   php artisan users:list
   ```

2. **If no users found, run reset:**
   ```bash
   php artisan db:reset-fresh
   ```

3. **Check your `.env` file:**
   Make sure your database connection is configured:
   ```
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=interntrack
   DB_USERNAME=root
   DB_PASSWORD=your_password
   ```

4. **Try logging in again:**
   - Username: `2300600`
   - Password: `interntrack123`

---

## Tips

- Run `users:list` anytime to verify accounts exist
- Run `db:reset-fresh` if you want a clean slate
- All passwords are `interntrack123` (hashed with bcrypt)
- The student account is intentionally blank (0 progress) for testing

---

**Created:** July 18, 2026  
**Database Version:** Fresh 0-Progress Setup
