# KaloAI 🥗 | AI-Powered Nutrition & Inventory Engine

[![Live Demo](https://img.shields.io/badge/demo-live_now-brightgreen?style=for-the-badge)](https://kaloai.infinityfreeapp.com/?i=1)
*Note: The application interface is in Spanish.*

**KaloAI** is an advanced nutritional management platform designed to eliminate food waste through intelligent automation. The system leverages Artificial Intelligence to analyze a user's real-time inventory and generate optimized meal plans and recipes.

---

## 🔗 Live Production Link
Explore the platform here: [kaloai.infinityfreeapp.com](https://kaloai.infinityfreeapp.com/?i=1)

---

## 🚀 Engineering Highlights

* **Autonomous Recipe Generation**: Deep integration with the **Google Gemini API (Flash)**. The engine interprets available stock to provide creative, culinary-accurate recipes without requiring external ingredients.
* **Pro-Level MVC Architecture**: Built with a custom Model-View-Controller framework in native PHP, ensuring high performance, scalability, and strict separation of concerns.
* **Smart Inventory Logic**: Implementation of complex business rules for stock tracking, expiration alerts, and food priority processing.
* **Security & Reliability**: 
    * **Database Integrity**: Full **PDO** implementation with prepared statements for top-tier SQL Injection prevention.
    * **High Availability**: Custom **API Key Rotation** logic to manage rate limits across multiple keys, ensuring 24/7 service uptime.
    * **Optimized Backend**: Refined error handling and logging for production-grade stability.
* **Modern Interface**: A sleek, high-fidelity responsive UI crafted with **Tailwind CSS** for a seamless cross-device experience.

## 🛠️ Tech Stack

* **Backend**: PHP 8.x (Custom MVC Framework)
* **Database**: MySQL (Relational Schema)
* **Intelligence**: Google Gemini AI
* **Frontend**: Tailwind CSS, Vanilla JavaScript
* **Infrastructure**: LAMP Stack Deployment

## 📋 Local Deployment

1.  Clone the repository: `git clone https://github.com/felixjbk/kaloAI.git`
2.  Import `database.sql` into your local MySQL instance.
3.  Rename `configuracion.example.php` to `configuracion.php`.
4.  Configure your DB credentials and insert your **Gemini API Keys**.
5.  Run via a local PHP server environment.

---
Developed by **Félix Corcoran** — Full Stack & AI Automation Developer.
