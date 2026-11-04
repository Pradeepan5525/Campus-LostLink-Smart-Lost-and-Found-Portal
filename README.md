# Campus LostLink — Smart Lost and Found Portal

Campus LostLink is a modern, responsive campus web application designed to help students, faculty, and administrative staff report, discover, match, and securely reclaim lost property.

---

## 1. Technologies Used
- **Frontend**: HTML5, Vanilla CSS3 (Custom Design System with Inter Font, CSS Variables, and Flexbox/Grid), Vanilla JavaScript (No React/Vue/Tailwind/Bootstrap).
- **Icons**: Font Awesome 6 CDN & Custom SVG illustrations.
- **Backend**: PHP 8.2 (Procedural / Light OOP with PDO prepared statements).
- **Database**: PostgreSQL (via `pdo_pgsql` driver).
- **Environment**: XAMPP / Apache / PHP Built-in Server + pgAdmin 4 / `psql`.

---

## 2. Default Demo Credentials

| Role | Name | Email Address | Password |
|---|---|---|---|
| **System Administrator** | System Administrator | `admin@lostlink.com` | `Admin@123` |
| **Student** | Rahul Sharma | `rahul@lostlink.com` | `Student@123` |
| **Student** | Priya Singh | `priya@lostlink.com` | `Student@123` |
| **Student** | Rohan Patel | `rohan@lostlink.com` | `Student@123` |
| **Student** | Arun Kumar | `arun@lostlink.com` | `Student@123` |

---

## 3. Step-by-Step Setup Guide

### Step 1: Place Project Files in XAMPP htdocs
Copy the project folder into your XAMPP `htdocs` directory:
```
C:\xampp\htdocs\campus-lostlink\
```
*(Or create a junction/symlink from `f:\Web Project` to `C:\xampp\htdocs\campus-lostlink`).*

### Step 2: Enable PostgreSQL Extensions in `php.ini`
> [!IMPORTANT]
> The `pdo_pgsql` and `pgsql` extensions must be enabled in your active `php.ini` file.

1. Open your active PHP configuration file:
   - For XAMPP: `C:\xampp\php\php.ini`
   - Or your standalone PHP: `php.ini`
2. Search for the following lines and ensure they are **uncommented** (remove the leading semicolon `;`):
   ```ini
   extension_dir = "ext"
   extension=pdo_pgsql
   extension=pgsql
   extension=fileinfo
   extension=mbstring
   ```
3. Restart the Apache server from the XAMPP Control Panel.

### Step 3: Create and Seed the PostgreSQL Database
1. Open **pgAdmin 4** (or open command prompt and use `psql`).
2. Create a new database named:
   ```sql
   CREATE DATABASE campus_lostlink;
   ```
3. Open Query Tool on `campus_lostlink` and execute the schema:
   - Run: `database/schema.sql`
4. Populate initial categories, campus locations, users, sample items, and claims:
   - Run: `database/seed.sql`

*Via Command Line:*
```powershell
$env:PGPASSWORD = "your_postgres_password"
psql -U postgres -h localhost -d campus_lostlink -f "database\schema.sql"
psql -U postgres -h localhost -d campus_lostlink -f "database\seed.sql"
```

### Step 4: Configure Database Connection
Open `config/db.php` and verify your PostgreSQL credentials:
```php
define('DB_HOST', 'localhost');
define('DB_PORT', '5432');
define('DB_NAME', 'campus_lostlink');
define('DB_USER', 'postgres');
define('DB_PASS', '1234'); // Set to your postgres password
```

### Step 5: Launch in Your Browser
Visit:
```
http://localhost/campus-lostlink/
```
*(Or run `php -S localhost:8000` from the project root and navigate to `http://localhost:8000`).*

---

## 4. End-to-End Workflow Overview
```
Open Campus LostLink -> Registered user?
  - No  -> Register -> Login
  - Yes -> Login
Login -> Student Dashboard, from which the student can:
  - Report Lost Item   -> Save report in PostgreSQL
  - Report Found Item  -> Save report in PostgreSQL
  - Search Items       (directly from the dashboard, and after saving a report)
Search Items -> Possible Match?
  - No  -> Continue Searching
  - Yes -> Submit Claim Request -> Admin or Finder Verifies Claim -> Claim Approved?
      - Yes -> Return Item and Close Report (item status = "Returned/Closed")
      - No  -> Keep Pending or Reject Claim
```

### Key Modules & Features
- **Smart Matching Engine**: Compares category, campus location, and event date ($\pm 7$ days) between Lost and Found items. Displays match confidence score and highlighted badge.
- **Ownership Verification Modal**: Requires claimants to submit detailed proof before a claim is queued.
- **Finder & Admin Dual Verification**: Either the reporting finder (under *My Reports*) or an administrator (under *Claims Verification Queue*) can approve or reject the claim.
- **Automatic Report Closure**: Approving a claim automatically updates the item's status to `Returned` and closes the case.
- **Security Protections**: PDO prepared statements, session regeneration against fixation, CSRF tokens on all POST forms, HTML escaping, and strict 2MB MIME file validation.
