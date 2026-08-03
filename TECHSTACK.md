# Tech Stack

This project is built using a modern Laravel ecosystem with AI-powered automation, asynchronous processing, and production-ready development practices.

---

# Backend

### Laravel 12

The core MVC framework responsible for routing, controllers, authentication, authorization, business logic, queues, events, notifications, file storage, and API integrations.

### PHP 8.3+

Server-side programming language used to build the application.

---

# Frontend

### Blade

Laravel's native templating engine for rendering fast, server-side views.

### Tailwind CSS

Utility-first CSS framework for building responsive and modern user interfaces.

### Alpine.js

Lightweight JavaScript framework used for interactive UI components such as modals, dropdowns, tabs, and dynamic forms.

### JavaScript (ES6+)

Handles client-side interactions, AJAX requests, and dynamic UI behavior.

---

# Database

### PostgreSQL *(Recommended)*

Primary relational database for storing application data.

> **Alternative:** MySQL

---

# Authentication & Authorization

### Laravel Breeze

Authentication starter kit providing:

* Login
* Registration
* Password Reset
* Email Verification
* Profile Management

### Database Sessions

* Session driver configured to `database` to store authentication sessions securely in the relational database.

### Laravel Authorization (RBAC)

Role-Based Access Control using custom Middleware and Laravel Gates/Policies.

Supported Roles (Defined via the `App\Enums\Role` enum):

* **Admin**: Has full access, including user management (via `/users` page, protected by `auth` and custom `EnsureUserIsAdmin` middleware).
* **Agent**: Restrained access. Excluded from accessing `/users` (receives a `403 Forbidden` response).

### User Navigation Access

* The **Users** navigation link (both desktop and responsive) is conditionally rendered via Blade directive `@if (Auth::user()->isAdmin())` to ensure visibility only for Admin users and hidden from Agents.

### User Provisioning & Seeders

* The system is seeded with a default **Admin** account (configured via `app.admin_email` / `app.admin_password` config settings).
* The system is seeded with a default **Agent** account (`agent@gmail.com` / `password123`) using the `DatabaseSeeder` with duplicate checking (`firstOrCreate`).

---

# Artificial Intelligence

### Gemini AI API

Provides AI-powered automation including:

* Ticket Classification
* Priority Detection
* Ticket Summarization
* Suggested Reply Generation
* Sentiment Analysis
* Context Understanding

---

# Queue & Background Processing

### Laravel Queue

Handles asynchronous processing for:

* AI Requests
* Email Delivery
* Notifications
* Background Jobs

### Redis

Used as:

* Queue Driver
* Cache Store
* High-speed In-Memory Data Store

### Laravel Horizon

Queue monitoring dashboard providing:

* Worker Monitoring
* Failed Job Tracking
* Queue Metrics
* Performance Monitoring

---

# Email Services

### Laravel Mail

Handles application email delivery including notifications, password resets, and ticket updates.

### Postmark API

Used for:

* Transactional Email Delivery
* Inbound Email Processing
* Email-to-Ticket Conversion

> **Alternative Providers**
>
> * Mailgun
> * SendGrid
> * Amazon SES

---

# File Storage

### Laravel Storage

Used to securely store:

* Ticket Attachments
* Images
* Documents
* Generated Files

---

# Development Tools

### Composer

PHP dependency management.

### NPM

Frontend dependency management.

### Git

Distributed version control.

### GitHub

Source code hosting and collaboration.

### ngrok *(Development Only)*

Secure tunneling for testing inbound webhooks during local development.

---

# Technology Summary

| Layer                   | Technology                       |
| ----------------------- | -------------------------------- |
| Backend                 | Laravel 12                       |
| Language                | PHP 8.3+                         |
| Frontend                | Blade + Tailwind CSS + Alpine.js |
| Database                | PostgreSQL (Recommended) / MySQL |
| Authentication          | Laravel Breeze (Database Sessions) |
| Authorization           | Laravel Policies & Gates (RBAC)  |
| Artificial Intelligence | Gemini AI API                    |
| Queue                   | Laravel Queue                    |
| Queue Driver            | Redis                            |
| Queue Monitoring        | Laravel Horizon                  |
| Email                   | Laravel Mail + Postmark API      |
| File Storage            | Laravel Storage                  |
| Version Control         | Git & GitHub                     |
| Development Tool        | ngrok                            |

---

# Design Principles

* Clean MVC Architecture
* Scalable Application Design
* Modular Code Structure
* AI-Assisted Automation
* Secure Authentication & Authorization
* Asynchronous Background Processing
* Production-Ready Development
* Maintainable Laravel Best Practices
