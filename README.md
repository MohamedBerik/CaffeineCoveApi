# Caffeine Cove API 🏗️

**Core Backend for ERP & Accounting System**  
A robust REST API powering café management with double-entry accounting logic.

[![Laravel](https://img.shields.io/badge/Laravel-10.x-red)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.1+-purple)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-blue)](https://mysql.com)

## 📋 Overview

This API serves as the **independent backend core** for the Caffeine Cove ecosystem. Built with clean architecture principles, it handles all business logic including order processing, invoice management, payment tracking, and double-entry accounting.

## ✨ Key Features

- **Authentication**: Laravel Sanctum with token-based auth
- **Order Management**: Full CRUD with stock validation
- **Invoice Engine**: Automatic status calculation (paid/partial/unpaid)
- **Payment System**: Support partial & multiple payments
- **Refund Engine**: Smart refund with invoice/credit separation
- **Double-Entry Accounting**: Balanced journal entries
- **Customer Ledger**: Complete transaction history
- **Role-Based Access**: Admin/User separation

## 🛠️ Tech Stack

- **Framework**: Laravel 10+
- **Database**: MySQL 8.0
- **Authentication**: Laravel Sanctum
- **API Style**: RESTful
- **Documentation**: Postman/OpenAPI

## 🚀 Quick Start

### Prerequisites

- PHP ≥ 8.1
- Composer
- MySQL ≥ 8.0
- Node.js & NPM (for Laravel Mix)

### Installation

```bash
# Clone repository
git clone https://github.com/yourusername/caffeine-cove-api.git

# Install dependencies
composer install

# Environment setup
cp .env.example .env
php artisan key:generate

# Database configuration
# Edit .env file with your database credentials
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=caffeine_cove
DB_USERNAME=root
DB_PASSWORD=

# Run migrations & seeders
php artisan migrate --seed

# Start server
php artisan serve
API will be available at: http://localhost:8000

📚 API Documentation
Authentication
text
POST   /api/login          - User login
POST   /api/logout         - User logout
GET    /api/user           - Get authenticated user
Core Endpoints
Orders
text
GET    /api/orders         - List orders (paginated)
POST   /api/orders         - Create order
GET    /api/orders/{id}    - Get order details
PUT    /api/orders/{id}    - Update order
DELETE /api/orders/{id}    - Cancel order
Invoices
text
GET    /api/invoices       - List invoices
GET    /api/invoices/{id}  - Invoice details
POST   /api/invoices/{id}/pay - Process payment
Payments
text
GET    /api/payments       - List payments
POST   /api/payments/refund/{id} - Process refund
Accounting
text
GET    /api/journal-entries - View journal entries
GET    /api/ledger/{customer} - Customer ledger
🗄️ Database Schema
Core Tables
users - System users

orders - Customer orders

invoices - Generated invoices

payments - Payment records

refunds - Refund transactions

journal_entries - Accounting entries

journal_lines - Debit/credit lines

customer_ledger - Customer balance tracking

Relationships
text
Order → Invoice → Payments → Refunds
      ↘ JournalEntries
            ↘ CustomerLedger
🔒 Security Features
Token Authentication: Bearer tokens via Sanctum

CORS: Properly configured for frontend domains

Rate Limiting: API throttle protection

Input Validation: Strict request validation

SQL Injection Prevention: Eloquent ORM protection

🧪 Testing
bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit

# With coverage (requires XDebug)
php artisan test --coverage
📊 Business Logic Highlights
Invoice Status Calculation
php
// Auto-calculated based on payments
- unpaid    (total_paid = 0)
- partial   (0 < total_paid < total)
- paid      (total_paid >= total)
Refund Protection
Prevents over-refunding

Tracks refund per payment

Auto-updates available balances

Maintains ledger consistency

Double-Entry Validation
sql
-- Every transaction maintains balance
SELECT SUM(debit) - SUM(credit)
FROM journal_lines
WHERE journal_entry_id = ?
-- Must equal 0
🚦 Error Handling
Standardized JSON responses:

json
{
  "success": false,
  "message": "Error description",
  "errors": {
    "field": ["Validation error"]
  }
}
📈 Performance Optimizations
Eager Loading: Prevents N+1 queries

Caching: Config caching for routes/config

Pagination: All list endpoints paginated

Indexed Columns: Optimized database indexes

🔧 Configuration
Environment Variables
env
APP_NAME="Caffeine Cove API"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://localhost

SANCTUM_STATEFUL_DOMAINS=localhost:3000
SESSION_DOMAIN=localhost
CORS Setup
php
// config/cors.php
'paths' => ['api/*'],
'allowed_origins' => ['http://localhost:3000'],
🤝 Integration Guide
For Frontend Developers
Base URL: http://localhost:8000/api

Authentication: Bearer token in headers

All requests require Accept: application/json

Pagination metadata in response headers

Example Request (JavaScript)
javascript
const response = await fetch('http://localhost:8000/api/orders', {
  headers: {
    'Authorization': `Bearer ${token}`,
    'Accept': 'application/json'
  }
})
📝 API Response Format
Success Response
json
{
  "success": true,
  "data": {},
  "message": "Operation successful"
}
Paginated Response
json
{
  "data": [],
  "links": {},
  "meta": {
    "current_page": 1,
    "last_page": 10,
    "per_page": 15,
    "total": 150
  }
}
🐛 Known Issues & Limitations
Rate limiting per user not implemented

File export (PDF/Excel) pending

Webhook notifications not included

🗺️ Roadmap
Core CRUD operations

Authentication & Authorization

Payment & Refund Engine

Double-Entry Accounting

Unit & Feature Tests (80%+ coverage)

API Documentation (Swagger/OpenAPI)

OAuth2 Support

Webhook System

GraphQL Support (optional)

🤝 Contributing
Fork the repository

Create feature branch (git checkout -b feature/amazing)

Commit changes (git commit -m 'Add amazing feature')

Push branch (git push origin feature/amazing)

Open Pull Request

Coding Standards
PSR-12 coding style

DocBlocks for all methods

Feature tests for new endpoints

Update API documentation

📄 License
MIT License - feel free to use in your projects

👨‍💻 Author
Mohamed Berik
Full Stack Developer
GitHub | LinkedIn

🙏 Acknowledgments
Laravel community

Double-entry accounting principles

Open source ERP systems inspiration

⭐ Found this helpful? Star the repository!
🐛 Found a bug? Open an issue
```
