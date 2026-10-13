<div align="center">
  
  <h1>🔬 SLBS</h1>
  <p><b>Smart Laboratory Billing System for Small to Medium Pathology Labs</b></p>
  
  <a href="https://services.smartjoans.space/projects"><img src="https://img.shields.io/badge/View_Demo_Video-smartjoans.space-blue?style=for-the-badge&logo=youtube" alt="Demo Video"></a>
  <a href="https://services.smartjoans.space/"><img src="https://img.shields.io/badge/Developer-smartJOANS-orange?style=for-the-badge&logo=codeigniter" alt="Developer"></a>
  <img src="https://img.shields.io/badge/Stack-PHP_|_MySQL-success?style=for-the-badge&logo=php" alt="Stack">
</div>

<br>

## 📋 Project Overview
The **Smart Laboratory Billing System (SLBS)** is a comprehensive, web-based management solution designed specifically for small to medium-sized pathology laboratories. It streamlines patient registration, automated billing, manual test result entry, and ensures accurate financial and clinical reporting.

🎥 **[Watch the Demo Video Here!](https://services.smartjoans.space/projects)**

---

## ✨ Key Features

### 💵 Billing and Finance
- **Dynamic Billing:** Quickly generate invoices based on selected master tests.
- **Flexible Payments:** Supports Cash, Card, and UPI with real-time validation ensuring paid amounts match the net bill.
- **Discounts & Reversals:** Apply discounts during billing, and easily cancel bills (automatically logged for financial reconciliation).
- **Print Preview:** Generate instant, print-ready invoices upon successful submission.

### 🧪 Laboratory Management
- **Test Management (CRUD):** Complete control over adding, editing, and deleting master test profiles.
- **Nested Parameters:** Define complex tests with multiple sub-tests (e.g., HB under CBC).
- **Detailed Setup:** Configure specific Specimen, Container, TAT Time, and Units for every test.
- **Report Typing:** Classify parameters as *Numeric* (with customizable decimals) or *Text* to enforce strict data validation.

### 📊 Reporting and Access Control
- **Granular Result Entry:** Dynamic input fields automatically render based on the tests billed to the patient.
- **Abnormal Highlighting:** Automatically bolds and colors results falling outside configured Reference Ranges.
- **Reference Range Management:** Set multiple age- and sex-specific normal ranges for every parameter.
- **Smart Printing:** Print reports with or without company letterheads (adds a 2-inch top margin for pre-printed paper).
- **Patient History:** Quick lookup tool for viewing a patient's entire billing history and report status.

---

## 🛠️ Installation and Setup Guide

Follow these steps to deploy SLBS on your local XAMPP environment.

<details>
<summary><b>1️⃣ System Prerequisites</b></summary>
<br>

- **XAMPP:** Download and install the XAMPP software suite.
- **Start Services:** Open the XAMPP Control Panel and start the **Apache** and **MySQL** services.
</details>

<details>
<summary><b>2️⃣ Database Configuration</b></summary>
<br>

1. Open your browser and navigate to `http://localhost/phpmyadmin`.
2. Create a new database (you can choose any name).
3. Locate the `smartjoa_demo.sql` file provided inside the `database` folder of this repository.
4. Import this `.sql` file into your newly created database.
</details>

<details>
<summary><b>3️⃣ Deploy Application Files</b></summary>
<br>

1. Extract the downloaded SLBS ZIP file.
2. Copy the extracted folder into the `htdocs` directory of your XAMPP installation (e.g., `C:\xampp\htdocs\slbs`). *Ensure the folder name is exactly `slbs` if you want to use default URLs.*
3. Open `db_connect.php` in your code editor and update the database name, username, and password to match your local setup.
4. Open your web browser and navigate to: `http://localhost/slbs/login.php`
</details>

---

## 🔐 Default User Accounts

Use the following credentials to log in for the first time. 

| User Type | Username | Password | Notes |
| :--- | :--- | :--- | :--- |
| **Superadmin** | `superadmin` | `superadmin` | Highest privileges. Can modify application license validity dates and numbers. |
| **Admin** | `admin` | `admin` | Standard administrative user. |

> ⚠️ **SECURITY NOTE:** It is highly recommended that you change the default passwords immediately after your first successful login!

---

## 💻 Technical Stack

SLBS is built on a reliable, standard LAMP stack environment, ensuring compatibility with cPanel and Shared Hosting environments.

| Component | Technology | Role |
| :--- | :--- | :--- |
| **Backend Logic** | PHP 7.4+ (Procedural) | Handles all business logic, data processing, and security. |
| **Database** | MySQL / MariaDB | Stores all patient, billing, test, and results data. |
| **Frontend / UI** | HTML5 / CSS3 / JS / jQuery | User interface, dynamic forms (AJAX search), and input validation. |
| **Security** | Prepared Statements | Uses MySQLi Prepared Statements to prevent SQLi attacks. |

---

## ⚙️ Data Model Structure

The system relies on a tightly linked relational data model using Foreign Key constraints to ensure data integrity.

| Table Name | Purpose | Key Relations |
| :--- | :--- | :--- |
| `tests` | Master definitions for tests (Price, Unit, Report Type). | - |
| `sub_tests` | Specific parameters nested under main tests. | `test_id` (FK) |
| `ref_ranges` | Age/sex-specific normal values for parameters. | `test_id` (FK), `sub_test_id` (FK) |
| `patients` | Patient demographic data. | - |
| `billing` | Master invoice record (amounts, dates, status). | `pt_id` (FK) |
| `bill_tests` | Links tests to a specific invoice (tracks cost). | `bill_id`, `test_id` (FKs) |
| `reports` | Finalized test results and the specific range used. | `bill_id`, `test_id`, `range_id` (FKs) |
| `roles / permissions`| Defines granular access levels (Superadmin, Admin, etc.). | - |
| `licence` | Stores the licence key and expiration date. | Enforced at login. |

---

## 🛡️ Security and Access Control

- **Role-Based Access Control (RBAC):** Access to every page is strictly controlled by the `check_permission()` function, verifying the user's role against required permissions.
- **Superadmin Bypass:** The Superadmin (Role ID 1) retains login access even if the main license key expires, ensuring emergency system administration is always possible.
- **Hierarchy Guardrail:** Only the Superadmin account can create or modify the Superadmin role's permissions.
- **Database Security:** All data submission and retrieval utilize MySQLi Prepared Statements to prevent SQL injection vulnerabilities.

---

## 📞 Support & Contact

If you need any help with installation, custom modifications, or support, feel free to reach out.

🌐 **Website:** [services.smartjoans.space](https://services.smartjoans.space)
