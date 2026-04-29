# 🦷 Caffeine Cove API (Dental Clinic ERP)

**Core Backend for Dental Clinic Management & Accounting System**  
A robust REST API powering clinic operations with double-entry accounting logic and enterprise-grade real-time notifications.

[![Laravel](https://img.shields.io/badge/Laravel-10.x-red)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.1+-purple)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-blue)](https://mysql.com)
[![Pusher](https://img.shields.io/badge/Pusher-Channels-0F0F0F)](https://pusher.com)

## 📋 Overview

This API serves as the **independent backend core** for the Caffeine Cove ecosystem. Built with clean architecture principles, it handles business logic for dental clinic management, financial accounting, and a secure real-time notification layer.

## ✨ Key Features

- **Multi-Tenant Authentication:** Laravel Sanctum with strict `company_id` scoping.
- **Clinic Operations:** Appointment scheduling, patient records, treatment plans, and dental procedures.
- **Financial Engine:** Double-entry accounting, partial payments, and smart refund logic.
- **Realtime Notifications:** Private WebSocket channels for live alerts and activity logs.
- **Audit Trail:** Complete activity logging for financial and clinical actions.

## 🛠️ Tech Stack

- **Framework:** Laravel 10+
- **Database:** MySQL 8.0
- **Authentication:** Laravel Sanctum (Multi-Tenant)
- **Realtime:** Pusher Channels (Private)
- **Queue:** Database Driver (Production Ready)
- **API Style:** RESTful

## 🚀 Quick Start

### Prerequisites

- PHP ≥ 8.1
- Composer
- MySQL ≥ 8.0
- Pusher Account (Free Tier works)

### Installation

```bash
# Clone repository
git clone https://github.com/yourusername/caffeine-cove-api.git

# Install dependencies
composer install

# Environment setup
cp .env.example .env
php artisan key:generate

# Configure Database & Pusher in .env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=caffeine_cove
DB_USERNAME=root
DB_PASSWORD=

PUSHER_APP_ID=your_id
PUSHER_APP_KEY=your_key
PUSHER_APP_SECRET=your_secret
PUSHER_APP_CLUSTER=mt1

# Run migrations & seeders
php artisan migrate --seed

# Start server & Queue Worker
php artisan serve
php artisan queue:work
```

📚 API Documentation
🔐 Authentication
Method Endpoint Description
POST /api/login User login
POST /api/logout User logout
GET /api/me Get authenticated user with permissions
🦷 Clinic Management
Method Endpoint Description
GET /api/erp/appointments List appointments
POST /api/erp/appointments/book Book new appointment
PUT /api/erp/appointments/{id} Update appointment
GET /api/erp/customers List patients
GET /api/erp/treatment-plans List treatment plans
POST /api/erp/treatment-plans Create treatment plan
📊 Finance & Accounting
Method Endpoint Description
GET /api/erp/invoices List invoices
POST /api/erp/invoices/{id}/payments Process payment
POST /api/erp/payments/{id}/refund Process refund
GET /api/erp/customers/{id}/statement Customer ledger
🔔 Realtime Notifications
Method Endpoint Description
GET /api/erp/alerts Get user alerts (Paginated)
GET /api/erp/alerts/unread-count Get unread count
POST /api/erp/alerts/{id}/ack Mark as read
POST /api/broadcasting/auth WebSocket authentication
🗄️ Database Schema (Core Tables)
Table Description
companies Multi-tenant clinics/organizations
appointments Patient appointments with status tracking
treatment_plans Multi-phase dental procedures
system_alerts Persistent real-time notifications
journal_entries Double-entry accounting records
customer_ledger Patient financial history
🔒 Security & Multi-Tenancy
Company Scoping: All Eloquent queries are automatically scoped to company_id.

Private Channels: Real-time events are broadcast to private-company.{id} ensuring data isolation.

Broadcast Auth: Secure WebSocket authentication via Sanctum tokens.

🔔 Realtime Architecture
text
SystemAlert::create()
↓
AlertCreated Event (ShouldBroadcast)
↓
Private Channel: company.{id}
↓
Pusher WebSocket
↓
React Frontend (Laravel Echo)
Supported Alert Types:

LOW_STOCK (Inventory warning)

PAYMENT_FAILED (Financial error)

NEW_ORDER (Sales)

APPOINTMENT_BOOKED (Scheduling)

🧪 Testing
bash

# Run all tests

php artisan test

# Run specific suite

php artisan test --testsuite=Feature
📈 Performance Optimizations
Eager Loading: Prevents N+1 query issues.

Pagination: Standardized 15-20 items per page.

Queue Worker: Database queue for async broadcasting.

Indexed Columns: Optimized for multi-tenant queries.

🛣️ Roadmap
Core Accounting & Clinic Modules

Multi-Tenant Architecture

Private Channel Broadcasting

System Alerts & Activity Logs

Automated Appointment Reminders (Cron Jobs)

PDF Invoice Generation

Swagger/OpenAPI Documentation

💼 Why This Project Matters
This API demonstrates production-level backend engineering:

Strict Financial Consistency: Balanced journal entries and ledger tracking.

Secure Real-Time Communication: Authenticated private WebSockets.

Scalable Multi-Tenancy: Ready for hundreds of clinics on a single server.

👨‍💻 Author
Mohamed Berik
Full Stack Developer
Laravel | React | REST APIs | ERP Systems | Real-Time Applications

echo "" >> README.md
