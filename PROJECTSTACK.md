# Project Tech Stack & Architecture

This document describes the technology stack and authorization architecture for the Helpdesk project.

## Backend
* **Framework**: Laravel 12
* **Language**: PHP 8.3+

## Frontend
* **Templating**: Blade
* **Styling**: Tailwind CSS
* **Interactivity**: Alpine.js / ES6+ JavaScript

## Database & Caching
* **Database**: PostgreSQL (Recommended) / MySQL
* **Cache & Queues**: Redis

## Authentication & Authorization
* **Auth Scaffolding**: Laravel Breeze (Database Sessions)
* **Authorization Scheme**: Role-Based Access Control (RBAC)
* **Role Representation**: Supported roles are represented via the `App\Enums\Role` enum (`Admin` and `Agent`).

### Role-Based Route Protection
* **Admin-only Users Page**: A `/users` route is defined and mapped to `UserController::index`. Access is restricted using:
  - `auth` middleware (ensures authentication)
  - `admin` middleware alias mapping to custom middleware `App\Http\Middleware\EnsureUserIsAdmin`.
* **Access Control**:
  - **Admins** have full access to `/users`.
  - **Agents** attempting to access the page are blocked with a `403 Forbidden` response.
  - **Guests** (unauthenticated) are redirected to the login page.

### Navigation Visibility
* The **Users** navigation link (both desktop and mobile responsive menu) is conditionally rendered using the `@if (Auth::user()->isAdmin())` Blade directive, making it completely hidden from Agent users.

## User Provisioning & Seeders
* **Admin Account**: Configured via environment settings (`app.admin_email` and `app.admin_password`).
* **Agent Account**: A default Agent account (`agent@gmail.com` with password `password123`) is seeded using the `DatabaseSeeder`.
* **Idempotency**: All seeders utilize duplicate checking (`firstOrCreate` or `updateOrCreate`) to ensure multiple executions do not create duplicate users.
