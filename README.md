 # 🎓 Cooperative Training Management System (CTMS)

A web-based platform designed to manage cooperative training programs — connecting students, supervisors, and training institutions through a centralized, easy-to-use system.

![PHP](https://img.shields.io/badge/PHP-777BB4?style=flat&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=flat&logo=mysql&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-1572B6?style=flat&logo=css3&logoColor=white)
![HTML5](https://img.shields.io/badge/HTML5-E34F26?style=flat&logo=html5&logoColor=white)

---

## 📌 Overview

**Cooperative Training Management System (CTMS)** is a full-stack web application built to digitize and streamline the cooperative training process. It provides an organized environment where students can register for training programs, supervisors can monitor progress, and administrators can manage institutions, records, and reports — all in one centralized platform.

The system is developed using **PHP**, **MySQL**, and modern web technologies, following clean coding practices and a modular architecture for maintainability and scalability.

---

## ✨ Key Features

The system provides a complete set of features to manage the entire cooperative training lifecycle:

### 👤 User Management
- **Multi-Role System:** Separate access for Students, Supervisors, and Admins.
- **Secure Authentication:** Login system with hashed passwords and session management.
- **Profile Management:** Each user can manage their own profile and information.

### 🎓 Training Management
- **Training Registration:** Students can register for cooperative training programs.
- **Institution Management:** Admins can add and manage training institutions.
- **Supervisor Assignment:** Assign supervisors to students and institutions.
- **Progress Tracking:** Monitor student progress and training status.

### 📊 Reports & Analytics
- **Student Reports:** Generate detailed reports on student performance.
- **Attendance Tracking:** Track attendance and participation.
- **Export Options:** Export data for administrative use.

### 📁 Content Management
- **File Uploads:** Upload documents, reports, and training materials.
- **Module System:** Modular structure for easy expansion.
- **Database Backup:** SQL dump included for easy restoration.

### 📱 User Experience
- **Responsive Design:** Works on desktop, tablet, and mobile.
- **Clean Interface:** Simple, intuitive UI for all user roles.
- **Fast Performance:** Optimized PHP backend for quick load times.

---

## 🛠️ Technologies Used

The system is built using a modern and widely-supported web technology stack:

### Backend
- **PHP:** Core server-side scripting language.
- **MySQL:** Relational database for structured data storage.
- **Sessions & Cookies:** Secure user authentication and state management.

### Frontend
- **HTML5:** Semantic markup and structure.
- **CSS3:** Modern styling and responsive layout.
- **JavaScript:** Interactive client-side functionality.

### Database
- **MySQL:** Main database engine.
- **SQL Dump:** `ctms_db1.sql` included for easy setup.

### Architecture & Patterns
- **Modular Structure:** Organized `modules/` for separation of concerns.
- **Includes Folder:** Reusable components and functions.
- **Config Folder:** Centralized configuration files.

### Security
- **Password Hashing:** Secure storage of user credentials.
- **Session Management:** Protected user sessions.
- **Input Validation:** Sanitization of user inputs.
- **SQL Injection Prevention:** Prepared statements and safe queries.

---

## 🔐 Security Implementation

Security was a priority throughout the development of this system. The following measures were implemented to protect user data and prevent common vulnerabilities:

### 🛡️ Authentication & Authorization
- **Secure Login:** Passwords hashed using strong algorithms.
- **Role-Based Access:** Different permissions for Students, Supervisors, and Admins.
- **Session Management:** Secure session tokens with expiration.

### 🔒 Data Protection
- **SQL Injection Prevention:** Parameterized queries and safe database access.
- **XSS Protection:** Input sanitization and output encoding.
- **CSRF Protection:** Token-based protection on forms.
- **Secure File Uploads:** Validation of uploaded file types and sizes.

### 🌐 Network & Infrastructure
- **HTTPS Support:** Secure communication.
- **Config Separation:** Sensitive settings stored in `config/`.
- **Error Logging:** Detailed logs for debugging and monitoring.

### 📋 Security Compliance
- Follows **OWASP Top 10** best practices.
- Adheres to **secure coding standards** for PHP applications.
- Implements **least privilege principle** for all users.

---

## 🏗️ Architecture

The system follows a **modular architecture**:

### 📐 System Layers
- **Presentation Layer:** HTML, CSS, and JavaScript frontend.
- **Business Logic Layer:** PHP modules handling core logic.
- **Data Access Layer:** MySQL queries and database operations.
- **Database Layer:** MySQL with structured schema.

### 🔄 Design Patterns
- **Modular Design:** Separate `modules/` for each feature.
- **Reusable Includes:** Shared components in `includes/`.
- **Configuration Separation:** Centralized `config/` files.

### 📊 Data Flow

```
User Request → index.php → Module → Database (MySQL)
                  ↓
User Response ← View ← Module ← Database
```

---

## 📂 Project Structure

The repository is organized for clarity and maintainability:

### 📁 Root Directory
- **assets/**: CSS, images, and static files.
- **config/**: Database and system configuration.
- **includes/**: Reusable components (header, footer, functions).
- **modules/**: Feature-specific modules.
- **uploads/**: User-uploaded files and documents.
- **index.php**: Main entry point.
- **ctms_db1.sql**: Database schema and initial data.
- **README.md**: Project documentation.

---

## 🚀 How to Run

Follow these steps to run the project locally:

### 📋 Prerequisites
- **XAMPP** or **WAMP** (Apache + MySQL + PHP).
- **PHP 7.4** or later.
- **MySQL** database.
- A modern web browser.

### ⚙️ Setup Steps

- **Step 1 — Clone the repository:**
  ```bash
  git clone https://github.com/BasharYousefSaleh/CTMS.git
  ```

- **Step 2 — Move to your server directory:**
  Place the project inside `htdocs/` (XAMPP) or `www/` (WAMP).

- **Step 3 — Create the database:**
  Open **phpMyAdmin** and create a new database named `ctms`.

- **Step 4 — Import the database:**
  Import the file `ctms_db1.sql` into the `ctms` database.

- **Step 5 — Configure the connection:**
  Update database credentials in `config/` files.

- **Step 6 — Run the project:**
  Open your browser and go to:
  ```
  http://localhost/CTMS
  ```

---

## 🎓 Academic Context

This project was developed as part of the academic requirements for the Bachelor of Science in Information Technology.

### 🏫 University
- **Al-Hikma University**, Dhamar, Yemen.

### 📅 Timeline
- **Duration:** 2024 – 2025.

---

## 👤 Author

**Bashar Yousef Saleh Al-Kharbi**

### 🎓 Education
- B.Sc. in Information Technology — Al-Hikma University (2025).

### 📬 Contact
- 📧 Email: bsharalkhrby92@gmail.com
- 🔗 GitHub: [@BasharYousefSaleh](https://github.com/BasharYousefSaleh)
- 📱 Phone: +967 778014486
- 💬 WhatsApp: [Chat on WhatsApp](https://wa.me/967778014486)

### 🎯 Interests
- Full-Stack Web Development
- Mobile Application Development (Flutter)
- Artificial Intelligence & Big Data
- Cloud Computing & Cybersecurity

---

## 📄 License

### ✅ Permitted Use
- Academic and personal use.

### ⚠️ Commercial Use
- For commercial use, please contact the author.

### 📧 Contact for Licensing
- Email: bsharalkhrby92@gmail.com

---

⭐ If you find this project useful, please consider giving it a star!
