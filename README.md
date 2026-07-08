# 🎓 Academic Performance Analysis Dashboard (APAD)

A full-stack academic performance tracking system built with **PHP**, **MySQL**, **HTML/CSS**, and **JavaScript**.

---

## 📁 Folder Structure

```
ajim/
├── admin/
│   ├── all_records.php       ← Admin: view/edit/delete all records
│   └── users.php             ← Admin: manage users, toggle roles
├── assets/
│   └── css/
│       └── style.css         ← Complete dark-mode design system
├── auth/
│   ├── login.php             ← Login page
│   ├── logout.php            ← Logout handler
│   └── register.php          ← Registration with password strength meter
├── config/
│   ├── db.php                ← MySQL connection + sanitize helper
│   ├── schema.sql            ← Full database schema + seed data
│   └── session.php           ← Session helpers (requireLogin, isAdmin, flash)
├── includes/
│   ├── header.php            ← Sidebar + top header (shared)
│   └── footer.php            ← Closing tags + sidebar JS (shared)
├── records/
│   ├── create.php            ← Add performance record (with live preview)
│   ├── delete.php            ← Delete handler
│   ├── edit.php              ← Edit record
│   └── index.php             ← Records list with multi-field filters
├── reports/
│   └── index.php             ← Generate/view/delete performance reports
├── students/
│   ├── create.php            ← Add student
│   ├── delete.php            ← Delete handler
│   ├── edit.php              ← Edit student
│   └── index.php             ← Students list with search/filter
├── dashboard.php             ← Main dashboard (charts + stats)
├── index.php                 ← Root redirect
└── README.md
```

---

## ⚙️ Setup Instructions

### Step 1 — Start XAMPP
Open XAMPP Control Panel and start both **Apache** and **MySQL**.

### Step 2 — Import the Database

**Option A — phpMyAdmin:**
1. Go to `http://localhost/phpmyadmin`
2. Click **New** → create database `ajim_dashboard` (or just run the SQL below)
3. Select `ajim_dashboard` → **Import** → choose `config/schema.sql` → **Go**

**Option B — Command Line:**
```bash
mysql -u root -p < c:\xampp\htdocs\ajim\config\schema.sql
```

### Step 3 — Set Admin Password

After importing, run this PHP snippet **once** to generate a proper password hash:

```php
<?php echo password_hash('Admin@1234', PASSWORD_BCRYPT, ['cost' => 12]); ?>
```

Then update the admin user in phpMyAdmin:
```sql
UPDATE users
SET password = '<paste_hash_here>'
WHERE email = 'admin@ajim.com';
```

> Default credentials: **admin@ajim.com** / **Admin@1234** (after hash is set)

### Step 4 — Open in Browser

```
http://localhost/ajim/
```

---

## 🗄️ Database Schema

| Table | Key Columns |
|---|---|
| `users` | id, name, email, password, role (admin/user) |
| `students` | id, student_code, name, department, semester, enrolled_by → users.id |
| `records` | id, student_id → students.id, subject, marks, max_marks, exam_type, exam_date |
| `reports` | id, student_id, avg_percentage, grade, performance_status, generated_by → users.id |

---

## ✨ Features

| Feature | Description |
|---|---|
| 🔐 Auth | Register, Login, Logout with session management |
| 👑 Roles | Admin (full access) vs User (own data only) |
| 🔒 Validation | Server + client-side, password strength meter |
| 🎓 Students | Full CRUD with department/semester tracking |
| 📝 Records | Full CRUD with marks, exam type, date, remarks |
| 📊 Dashboard | Stat cards + Chart.js bar & doughnut charts |
| 📋 Reports | Auto-generate performance reports with grading |
| 🔍 Search | Multi-field filters on all listing pages |
| 👥 Admin Panel | User management, role toggle, all-records view |
| 🌙 UI | Dark-mode, responsive sidebar, glassmorphism cards |

---

## 🎨 Grade Scale

| Grade | Percentage |
|---|---|
| A+ | ≥ 90% |
| A  | 75–89% |
| B  | 60–74% |
| C  | 45–59% |
| D  | 33–44% |
| F  | < 33% |

---

## 🛠️ Tech Stack

- **Frontend:** HTML5, CSS3 (custom design system), Vanilla JS
- **Charts:** Chart.js v4 (CDN)
- **Icons:** Phosphor Icons (CDN)
- **Backend:** PHP 8+
- **Database:** MySQL 5.7+ / MariaDB 10+
- **Server:** XAMPP (Apache + MySQL)
