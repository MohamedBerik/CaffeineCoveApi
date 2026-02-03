Caffeine Cove API – Laravel Backend & ERP

RESTful API built with Laravel to manage Caffeine Cove café system. Supports Orders, Invoices, Payments, Refunds, and Accounting (Journal Entries). Integrated with React frontend.

🚀 Features
Authentication

Laravel Sanctum token-based authentication

Role-based access (Admin, Finance, User)

Protected routes for ERP operations

Orders Management

Create / Update / Delete / Confirm / Cancel orders

Track status: pending, confirmed, cancelled

Validate stock availability before confirming orders

Automatic stock movements logged

Invoices & Payments

Generate invoices from orders

Record partial or full payments

Automatic invoice status update: partial, paid

Refund management for overpayments or cancellations

Linked journal entries for accounting

Accounting / Journal Entries

Double-entry accounting

Create Journal Entries automatically for payments and refunds

Each entry has lines for debit/credit

Prevent unbalanced entries

Customers & Products

CRUD for products

Track stock quantities

Link orders and invoices to customers

API Structure

RESTful routes with middleware protection

Resource controllers with validation

Transaction-safe operations using DB::transaction

📂 Project Structure (Backend)
app/
├── Http/Controllers/API/
│ ├── OrderController.php
│ ├── InvoiceController.php
│ ├── InvoicePaymentController.php
│ └── PaymentRefundController.php
├── Models/
│ ├── Order.php
│ ├── OrderItem.php
│ ├── Invoice.php
│ ├── Payment.php
│ ├── Refund.php
│ ├── JournalEntry.php
│ ├── JournalLine.php
│ └── Product.php
├── Services/
│ └── AccountingService.php

🔧 Environment Setup
DB_CONNECTION=mysql
DB_HOST=<host>
DB_PORT=3306
DB_DATABASE=<database>
DB_USERNAME=<user>
DB_PASSWORD=<password>

Run Migrations
php artisan migrate

Start Server
php artisan serve

🔒 Permissions & Roles

Use Laravel permissions to restrict access to ERP features

Example: permission:finance.view for finance routes

📌 Testing API

Use Postman to test endpoints

Ensure you pass Authorization: Bearer <token> header

⚙️ Future Improvements

Export reports (Excel / PDF)

Advanced filters on Orders/Invoices

Real-time notifications for payments/refunds

Multi-currency support

👨‍💻 Author

Mohamed Berik – Junior Full Stack Developer (Laravel | React | REST API | ERP)
