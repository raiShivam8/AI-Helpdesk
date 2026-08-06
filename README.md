# 🤖 AI-Based Helpdesk Ticketing System 🎉

> A full-stack automated customer support platform that handles tickets seamlessly — from IMAP email ingestion to AI-powered ticket classification, smart reply generation with Knowledge Base RAG, AI tone polishing, and agent workflow management. Built to eliminate manual overhead and deliver fast, personalized customer support.

---

## 🔗 Project Links

* 📦 **GitHub Repository:** [https://github.com/raiShivam8/AI-Helpdesk.git](https://github.com/raiShivam8/AI-Helpdesk.git)
* 🌐 **Live Application:** [https://ai-helpdesk-production-3013.up.railway.app](https://ai-helpdesk-production-3013.up.railway.app)

---

## 🔐 Demo Credentials

| Role | Email | Password |
| :--- | :--- | :--- |
| 🛡️ **Admin Account** | `admin@gmail.com` | `password123` |
| 🎧 **Agent Account** | `agent@gmail.com` | `password123` |

---

## ✨ Core Features

* 📥 **IMAP Email Ingestion:** Automatic IMAP mailbox polling (`webklex/laravel-imap`) with HTML sanitization (`stevebauman/purify`) and scheduled background email sync (`tickets:fetch-emails`).
* 🤖 **AI Ticket Classification:** Automatic tagging of category, priority, and sentiment analysis powered by Google Gemini 2.0 Flash.
* 💬 **AI Smart Reply & RAG:** Context-aware reply generation utilizing custom Markdown Knowledge Base (`knowledge-base.md`) injection.
* ✨ **AI Reply Polishing:** One-click AI tone adjustments to format, structure, and refine agent responses before sending.
* 📝 **AI Thread Summaries:** Instant AI summaries of long email chains for quick agent context.
* ⚡ **AI Auto-Resolution:** Automated resolution capability for common/standard user queries.
* 📊 **Agent & Admin Dashboard:** Real-time ticket management, status filtering, priority sorting, and manual email sync triggers.
* 👥 **User Management:** Role-based access control (Admin vs. Agent).
* 📤 **Outbound Email:** Automatic email delivery to customers upon agent reply via Laravel Mail (SMTP).
* 🔄 **Ticket Lifecycle:** Complete status progression (`Open` → `In Progress` → `Pending` → `Resolved` → `Closed`).

---

## 🛠️ Tech Stack

* **Frontend:** Laravel Blade · Tailwind CSS · Alpine.js · Vite · Laravel Breeze UI
* **Backend:** PHP 8.4 · Laravel 12 · Eloquent ORM · WebKlex Laravel IMAP · HTML Purify · Sentry Laravel Error Tracking
* **Database:** PostgreSQL 16 (Production) / SQLite (Local)
* **AI Engine:** Google Gemini API (`gemini-2.0-flash` with response caching, retry logic, and RAG integration)
* **Email Infrastructure:** IMAP Polling (`webklex/laravel-imap`) · SMTP Outbound Delivery
* **Testing & Monitoring:** PHPUnit 12 · Mockery · Laravel Test Suite · Sentry Error Monitoring
* **Infrastructure & Deployment:** Multi-stage Docker (Alpine PHP 8.4 + Nginx + Supervisor) · Deployed on Railway (`railway.json`, `Procfile`, Railway PostgreSQL)

---

## 📧 Email Flow & Testing

1. **Email Ingestion Architecture:** Customer sends an email to the configured support inbox → IMAP Mailbox receives message → Laravel Scheduler triggers `tickets:fetch-emails` artisan command → Ticket is generated and displayed on the Dashboard.
2. **Manual Ingestion Sync:** Click **Sync Emails** on the agent dashboard to instantly trigger an IMAP sync.

---

## 🚀 Local Development Setup

```bash
# 1. Clone the repository
git clone https://github.com/raiShivam8/AI-Helpdesk.git
cd AI-Helpdesk

# 2. Install PHP & Node dependencies
composer install
npm install

# 3. Environment configuration
cp .env.example .env
php artisan key:generate

# 4. Run migrations and seeders
php artisan migrate --seed

# 5. Build assets & run dev server
npm run build
php artisan serve
```

---

## 🧪 Testing & Verification

```bash
# Run unit and feature test suite
php artisan test

# Verify registered routes
php artisan route:list
```
