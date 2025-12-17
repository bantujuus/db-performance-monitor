# Database Performance Monitor

A full‑stack **Database Performance Monitoring Tool** designed to help database administrators detect performance issues early by collecting, storing, and visualizing key database metrics.

This project demonstrates real‑world skills in **frontend development, backend API design, and database monitoring concepts**.

---

     Features

* Collects database performance metrics (connections, load simulation, history)
* Displays metrics on a dashboard interface
* Backend REST‑style API built with PHP
* Frontend built with React
* Secure configuration handling (sensitive files ignored)

---

   Tech Stack

  Frontend

* React (JavaScript)
* Tailwind CSS
* Node.js & npm

  Backend

* PHP (REST API)
* MySQL / MariaDB
* XAMPP (Apache + MySQL)

---

   Project Structure

```text
database-monitor/
│
├── src/                 # React frontend source code
├── public/              # Frontend public assets
│
├── api/                 # PHP backend API
│   ├── collect_metrics.php
│   ├── dashboard_api.php
│   ├── get_history.php
│   ├── populate_data.php
│   ├── simulate_load.php
│   ├── quick_test.php
│   ├── config.example.php
│
├── README.md
├── package.json
└── .gitignore
```

---

 Backend Setup (PHP API)

1. Install **XAMPP** and start **Apache** and **MySQL**
2. Create a MySQL database (example):

   ```sql
   CREATE DATABASE db_monitor;
   ```
3. Copy the example config file:

   ```bash
   cp api/config.example.php api/config.php
   ```
4. Update `config.php` with your local database credentials

**Important:** `config.php` is ignored by Git and should never be committed.

---

Frontend Setup (React)

From the project root:

```bash
npm install
npm start
```

The application will run at:

```
http://localhost:3000
```

---

 API Endpoints (Examples)

* `collect_metrics.php` – Collects database metrics
* `dashboard_api.php` – Returns dashboard data
* `get_history.php` – Fetches historical performance data
* `simulate_load.php` – Simulates database load for testing

---

 Project Purpose

Database administrators often detect performance problems **after** users are affected.

This project aims to:

* Monitor database behavior
* Detect abnormal usage early
* Provide visibility into database performance

---

Future Improvements

* Authentication (JWT / API keys)
* Alerting system (email / notifications)
* Role‑based access (Admin / Viewer)
* Charts and analytics enhancements
* Deployment to a live server


 AuthorNkonde Given


