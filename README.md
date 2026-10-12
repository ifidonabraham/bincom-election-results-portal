# INEC Election Results Portal (Bincom Developer Assessment)

A responsive, user-friendly PHP & MySQL web application built to fulfill the **Bincom Dev Center Online Preliminary Test**.

---

## 🌟 Key Features

1. **Question 1: Individual Polling Unit Results** (`index.php`)
   - Chained dynamic dropdowns: Select **LGA** &rarr; updates **Ward** dropdown &rarr; updates **Polling Unit** dropdown via asynchronous JSON endpoints.
   - Accurately joins `polling_unit.uniqueid = announced_pu_results.polling_unit_uniqueid` (respecting the critical `uniqueid` join key specification).
   - Visual breakdown displaying party badges, votes, vote share percentages with progress bars, and polling unit metadata.

2. **Question 2: Summed LGA Total Results** (`lga_results.php`)
   - Select any Local Government Area (LGA) from Delta State (State ID 25).
   - Dynamically calculates total votes by querying `SUM(party_score)` from `announced_pu_results` for all polling units in the selected LGA.
   - Displays the officially announced collation total (`announced_lga_results`) side-by-side with an automated variance check for auditing.

3. **Question 3: Record New Polling Unit & Results** (`new_polling_unit.php`)
   - User-friendly form featuring cascading LGA and Ward pickers.
   - Dynamically fetches all contesting political parties from the `party` table.
   - Provides live JavaScript vote calculation.
   - Atomic database transaction: Inserts the new polling unit, acquires the new `uniqueid`, and writes corresponding score rows for **all parties** in `announced_pu_results` using prepared statements.

---

## 🛠️ Stack & Architecture

- **Backend**: PHP 8.x (Native PDO, Prepared Statements, Secure Input Sanitization)
- **Database**: MySQL / MariaDB (`bincom_test.sql`)
- **Frontend**: Bootstrap 5.3, Bootstrap Icons, Vanilla JS (Fetch API)
- **Design**: Clean responsive UI, mobile-ready layout, custom party color themes

---

## 🚀 Local Installation (XAMPP / Local Server)

1. Clone or copy this repository into your web root (e.g. `C:/xampp/htdocs/bincom-election-app`).
2. Start **Apache** and **MySQL** in XAMPP.
3. Import the database:
   - Create a database named `bincom_test`.
   - Import the included `bincom_test.sql` file via phpMyAdmin or MySQL CLI:
     ```bash
     mysql -u root -p bincom_test < bincom_test.sql
     ```
4. Access the portal in your browser:
   ```
   http://localhost/bincom-election-app/
   ```

---

## ☁️ Cloud & Free Hosting Deployment Guide

This application is built with environment-variable fallback support (`DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`, `DB_NAME` in `includes/db.php`).

### Option A: InfinityFree (Free PHP + MySQL)
1. Sign up at [InfinityFree](https://www.infinityfree.com/).
2. Create an account and note your MySQL database credentials (Host, Database Name, Username, Password).
3. Open phpMyAdmin on InfinityFree and import `bincom_test.sql`.
4. Upload all files into the `htdocs` directory via FileZilla / FTP.
5. In `includes/db.php`, update the fallback credentials or set environment variables.

### Option B: Render / Railway / Aiven
1. Create a free hosted MySQL instance (e.g., Aiven or Railway MySQL).
2. Import `bincom_test.sql`.
3. Deploy the PHP service and set the environment variables:
   - `DB_HOST`: Your remote MySQL hostname
   - `DB_PORT`: `3306`
   - `DB_USER`: Your remote MySQL user
   - `DB_PASS`: Your remote MySQL password
   - `DB_NAME`: Your remote database name

---

## 📂 Project Structure

```
bincom-election-app/
│
├── api/
│   ├── get_wards.php             # Chained Ward lookup API
│   └── get_polling_units.php     # Chained Polling Unit lookup API
│
├── assets/
│   └── css/
│       └── style.css             # Custom styles and party badge styling
│
├── includes/
│   ├── db.php                    # Database PDO connection wrapper
│   ├── header.php                # Reusable navigation header
│   └── footer.php                # Reusable footer
│
├── bincom_test.sql               # Original database dump
├── index.php                     # Q1: Polling Unit Results page
├── lga_results.php               # Q2: LGA Summed Total page
├── new_polling_unit.php          # Q3: Add Polling Unit & Party Scores
└── README.md                     # Documentation and setup instructions
```

---

## 👤 Author & Submission
- Created for: **Bincom Dev Center Preliminary Online Interview Test**
- Submission Form: [Bincom Recruitment Online Submission](https://blog.bincom.net/bincomforms/bincom-recruitment-online-interview-submission-form/)
