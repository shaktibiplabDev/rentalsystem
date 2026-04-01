# 🚗 Vehicle Rental Management System

A comprehensive vehicle rental management system with automated document verification, wallet integration, and real-time analytics. Built for shop owners to manage their rental fleet efficiently.

## 📋 Table of Contents
- [Overview](#overview)
- [Features](#features)
- [Tech Stack](#tech-stack)
- [System Architecture](#system-architecture)
- [Installation](#installation)
- [Configuration](#configuration)
- [Database Schema](#database-schema)
- [API Documentation](#api-documentation)
- [Key Workflows](#key-workflows)
- [Security](#security)
- [Testing](#testing)
- [Deployment](#deployment)
- [Contributing](#contributing)
- [License](#license)

---

## 📖 Overview

This is a complete vehicle rental management system designed for shop owners to manage their rental operations. The system handles vehicle inventory, customer management, rental processing, automated document verification via Cashfree OCR, wallet-based payments, and comprehensive reporting.

### 🎯 Target Users
- **Shop Owners**: Manage vehicles, track rentals, view earnings
- **Admins**: System-wide configuration, user management, analytics
- **Customers**: Rent vehicles, make payments, view history

---

## ✨ Features

### 🔐 Authentication & User Management
- JWT-based authentication with Laravel Sanctum
- Role-based access control (Admin, Shop Owner)
- Profile management and password reset
- Email verification (optional)

### 🚗 Vehicle Management
- CRUD operations for vehicles
- Real-time availability tracking
- Status management (Available, On Rent, Maintenance)
- Vehicle statistics and performance metrics
- Image upload and management

### 📦 Rental Management
- Start and end rentals with time tracking
- Automatic rental agreement generation (PDF)
- Receipt generation on completion
- Rental history with filters
- Duration calculation and pricing

### 📄 Document Verification
- Automated document verification via **Cashfree OCR**
- Aadhaar and Driving License verification
- Document upload and storage
- Verification status tracking
- OCR confidence scoring

### 💰 Wallet & Payments
- Digital wallet system for seamless payments
- Cashfree payment gateway integration
- Add money via UPI/Card/NetBanking
- Peer-to-peer wallet transfers
- Transaction history with filters
- Auto-deduction for rentals
- Wallet statements export (CSV)

### 📊 Reports & Analytics
- Real-time dashboard with key metrics
- Earnings reports (daily, monthly, yearly)
- Top vehicles and customers analytics
- Utilization rate calculation
- Export reports as CSV
- Document verification metrics

### ⚙️ Settings Management
- Business rules configuration
- Notification preferences
- Rental rate management
- Business hours setup
- Payment method toggles
- Multi-language support

### 📱 Mobile App Ready
- API-first architecture
- Optimized for mobile responses
- Push notification support
- Offline-capable with cached data

---

## 🛠 Tech Stack

### Backend
- **Framework**: Laravel 13.1.1
- **PHP**: 8.4.18
- **Database**: MySQL 8.0
- **Authentication**: Laravel Sanctum
- **File Storage**: Local/S3 compatible

### External Services
- **Payment Gateway**: Cashfree Payments
- **Document OCR**: Cashfree OCR
- **PDF Generation**: DomPDF / Laravel PDF
- **Email**: SMTP / Mailgun
- **SMS**: Twilio / SMS API

### Frontend (Mobile/Web)
- **Mobile**: Flutter / React Native (Recommended)
- **Web Admin**: React.js / Vue.js
- **API Client**: Axios / Dio

### Development Tools
- **Version Control**: Git
- **API Testing**: Postman
- **Database Management**: MySQL Workbench
- **Code Quality**: PHP CS Fixer, Laravel Pint

---

## 🏗 System Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    Vehicle Rental System                     │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌──────────┐     ┌──────────┐     ┌──────────┐            │
│  │ Mobile   │     │ Web      │     │ API      │            │
│  │ App      │────▶│ Admin    │────▶│ Gateway  │            │
│  └──────────┘     └──────────┘     └──────────┘            │
│         │               │                │                   │
│         └───────────────┴────────────────┘                   │
│                         │                                   │
│                         ▼                                   │
│  ┌──────────────────────────────────────────────┐          │
│  │           Laravel Application                 │          │
│  │  ┌────────────┐  ┌────────────┐              │          │
│  │  │ Controllers│  │ Services   │              │          │
│  │  ├────────────┤  ├────────────┤              │          │
│  │  │ Models     │  │ Middleware │              │          │
│  │  └────────────┘  └────────────┘              │          │
│  └──────────────────────────────────────────────┘          │
│                         │                                   │
│         ┌───────────────┼───────────────┐                  │
│         ▼               ▼               ▼                   │
│  ┌────────────┐  ┌────────────┐  ┌────────────┐          │
│  │  MySQL     │  │  Cashfree  │  │  File      │          │
│  │  Database  │  │  Services  │  │  Storage   │          │
│  └────────────┘  └────────────┘  └────────────┘          │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## 📥 Installation

### Prerequisites
- PHP >= 8.1
- Composer
- MySQL >= 5.7
- Node.js & NPM (for frontend assets)
- Git

### Step 1: Clone the Repository
```bash
git clone https://github.com/shaktibiplabDev/rentalsystem
cd vehicle-rental-system
```

### Step 2: Install Dependencies
```bash
composer install
npm install
```

### Step 3: Environment Configuration
```bash
cp .env.example .env
php artisan key:generate
```

### Step 4: Configure Database
Edit `.env` file:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=vehiclerental
DB_USERNAME=root
DB_PASSWORD=your_password
```

### Step 5: Run Migrations & Seeders
```bash
php artisan migrate
php artisan db:seed
```

### Step 6: Configure Cashfree Services
```env
CASHFREE_APP_ID=your_app_id
CASHFREE_SECRET_KEY=your_secret_key
CASHFREE_API_VERSION=2022-09-01
CASHFREE_ENV=TEST  # or PRODUCTION
```

### Step 7: Storage Link
```bash
php artisan storage:link
```

### Step 8: Start Development Server
```bash
php artisan serve
npm run dev
```

---

## ⚙️ Configuration

### Cashfree Payment Gateway
1. Register at [Cashfree](https://www.cashfree.com/)
2. Get API credentials from dashboard
3. Configure webhook URL: `https://yourdomain.com/api/webhooks/cashfree/payment`

### Document OCR Settings
```env
OCR_CONFIDENCE_THRESHOLD=80
AUTO_VERIFY_DOCUMENTS=true
```

### Email Configuration
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your_email@gmail.com
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
```

### File Storage
```env
FILESYSTEM_DISK=public  # or s3 for production
```

---

## 📊 Database Schema

### Core Tables

#### `users`
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| name | varchar | User name |
| email | varchar | Email address |
| phone | varchar | Phone number |
| password | varchar | Hashed password |
| role | enum | admin/user |
| wallet_balance | decimal | Current balance |

#### `vehicles`
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| user_id | bigint | Owner reference |
| name | varchar | Vehicle name |
| number_plate | varchar | Registration number |
| type | varchar | Bike/Scooter/Car |
| status | enum | available/on_rent/maintenance |
| hourly_rate | decimal | Rate per hour |
| daily_rate | decimal | Rate per day |

#### `rentals`
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| user_id | bigint | Shop owner |
| vehicle_id | bigint | Vehicle reference |
| customer_id | bigint | Customer reference |
| start_time | datetime | Rental start |
| end_time | datetime | Rental end |
| total_price | decimal | Total amount |
| status | enum | active/completed/cancelled |
| agreement_path | varchar | PDF agreement path |
| receipt_path | varchar | PDF receipt path |

#### `wallet_transactions`
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| user_id | bigint | User reference |
| amount | decimal | Transaction amount |
| type | enum | credit/debit |
| reason | varchar | Transaction reason |
| status | enum | pending/completed/failed |
| reference_id | varchar | Unique reference |

#### `customers`
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| name | varchar | Customer name |
| phone | varchar | Phone number |
| aadhaar_number | varchar | Aadhaar ID |
| license_number | varchar | DL number |
| address | text | Address |

---

## 📡 API Documentation

### Base URL
```
http://localhost:8000/api
```

### Authentication Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/register` | Register new user |
| POST | `/login` | Login user |
| POST | `/auth/logout` | Logout user |
| GET | `/auth/me` | Get profile |
| POST | `/auth/change-password` | Change password |

### Vehicle Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/vehicles` | List all vehicles |
| POST | `/vehicles` | Add new vehicle |
| GET | `/vehicles/available` | Get available vehicles |
| GET | `/vehicles/{id}` | Get vehicle details |
| PUT | `/vehicles/{id}` | Update vehicle |
| PATCH | `/vehicles/{id}/status` | Update status |
| DELETE | `/vehicles/{id}` | Delete vehicle |

### Rental Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/rentals/start` | Start new rental |
| POST | `/rentals/{id}/end` | End rental |
| GET | `/rentals/active` | Get active rentals |
| GET | `/rentals/history` | Rental history |
| GET | `/rentals/{id}/agreement` | Download agreement |
| GET | `/rentals/{id}/receipt` | Download receipt |

### Wallet Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/wallet` | Get balance |
| GET | `/wallet/transactions` | Transaction history |
| POST | `/wallet/add` | Add money (manual) |
| POST | `/wallet/deduct` | Deduct money |
| POST | `/wallet/transfer` | Transfer to user |
| POST | `/wallet/recharge/initiate` | Cashfree payment |
| GET | `/wallet/payment-status` | Check payment status |

### Report Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/reports/earnings` | Earnings report |
| GET | `/reports/rentals` | Rental report |
| GET | `/reports/summary` | Monthly summary |
| GET | `/reports/top-vehicles` | Top vehicles |
| GET | `/reports/top-customers` | Top customers |
| GET | `/reports/export/{type}` | Export CSV |

### Settings Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/settings` | Get all settings |
| PUT | `/settings` | Update settings |
| GET | `/settings/defaults` | Default templates |
| PUT | `/settings/{key}` | Update single setting |
| POST | `/settings/reset` | Reset to defaults |

---

## 🔄 Key Workflows

### 1. Complete Rental Process
```
1. Shop owner logs in
2. Select vehicle → Check availability
3. Add customer details
4. Verify documents (Aadhaar/License) via OCR
5. Set rental duration
6. Calculate total amount
7. Process payment (Wallet/Cashfree)
8. Generate rental agreement (PDF)
9. Start rental → Update vehicle status
10. On return → End rental → Generate receipt
```

### 2. Wallet Recharge Flow
```
1. Shop owner opens wallet
2. Clicks "Add Money"
3. Enters amount
4. Selects payment method (UPI/Card/NetBanking)
5. Cashfree payment gateway opens
6. Completes payment
7. Webhook confirms payment
8. Wallet balance updated
9. Transaction recorded
```

### 3. Document Verification Flow
```
1. Customer uploads document (Aadhaar/License)
2. Cashfree OCR processes document
3. Extracts text data
4. Validates against user input
5. Confidence score calculated
6. Auto-verified if above threshold
7. Manual review if below threshold
8. Verification status updated
```

---

## 🔒 Security

### Implemented Security Features
- **Authentication**: Laravel Sanctum with token expiration
- **Authorization**: Role-based middleware (admin/user)
- **Input Validation**: Form requests and validation rules
- **SQL Injection**: Eloquent ORM with parameter binding
- **XSS Protection**: Automatic escaping of output
- **CSRF**: API tokens prevent CSRF attacks
- **Rate Limiting**: Configurable per route/endpoint
- **Audit Logs**: All critical actions logged
- **Password Hashing**: Bcrypt algorithm
- **HTTPS**: Forced in production

### Environment Security
- Sensitive data in `.env` file (not committed)
- Database credentials encrypted
- API keys rotated regularly
- Regular security patches

---

## 🧪 Testing

### Run Tests
```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Feature

# Run with coverage report
php artisan test --coverage
```

### Test Coverage
- **Unit Tests**: Models, Services, Helpers
- **Feature Tests**: API endpoints, Controllers
- **Integration Tests**: Database operations, External services

---

## 🚀 Deployment

### Production Checklist

- [ ] Set `APP_ENV=production` in `.env`
- [ ] Set `APP_DEBUG=false`
- [ ] Configure production database
- [ ] Set up SSL certificate
- [ ] Configure queue worker (if using jobs)
- [ ] Set up monitoring (Sentry, Bugsnag)
- [ ] Configure backup strategy
- [ ] Set up CDN for assets
- [ ] Configure caching (Redis/Memcached)
- [ ] Set up cron jobs for scheduled tasks

### Deployment Steps

```bash
# 1. Pull latest code
git pull origin main

# 2. Install dependencies
composer install --optimize-autoloader --no-dev

# 3. Run migrations
php artisan migrate --force

# 4. Clear caches
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 5. Restart queue workers (if using)
php artisan queue:restart

# 6. Restart supervisor (if using)
sudo supervisorctl restart all
```

---

## 📁 Project Structure

```
vehicle-rental-system/
├── app/
│   ├── Http/
│   │   └── Controllers/
│   │       └── Api/
│   │           ├── AuthController.php
│   │           ├── VehicleController.php
│   │           ├── RentalController.php
│   │           ├── WalletController.php
│   │           ├── ReportController.php
│   │           ├── SettingController.php
│   │           └── DocumentController.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── Vehicle.php
│   │   ├── Rental.php
│   │   ├── Customer.php
│   │   ├── WalletTransaction.php
│   │   └── UserSetting.php
│   └── Services/
│       └── CashfreeService.php
├── database/
│   ├── migrations/
│   └── seeders/
├── routes/
│   └── api.php
├── storage/
│   ├── app/
│   │   ├── agreements/
│   │   ├── receipts/
│   │   └── documents/
├── tests/
├── .env.example
├── composer.json
└── README.md
```

---

## 🤝 Contributing

1. Fork the repository
2. Create feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit changes (`git commit -m 'Add AmazingFeature'`)
4. Push to branch (`git push origin feature/AmazingFeature`)
5. Open Pull Request

### Coding Standards
- Follow PSR-12 coding standards
- Write tests for new features
- Update documentation
- Keep commits atomic and descriptive

---

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

## 🙏 Acknowledgments

- [Laravel](https://laravel.com/) - The PHP framework
- [Cashfree](https://www.cashfree.com/) - Payment gateway & OCR services
- [DomPDF](https://github.com/dompdf/dompdf) - PDF generation
- All contributors and supporters

---

## 📞 Support

For support, email support@yourdomain.com or create an issue in the repository.

---

## 📊 System Metrics

### Performance Goals
- API Response Time: < 200ms
- Concurrent Users: 1000+
- Database Query Optimization: < 50ms
- Uptime: 99.9%

### Scalability
- Horizontal scaling with load balancer
- Database read replicas
- Redis caching layer
- Queue workers for async tasks

---

## 🎯 Future Roadmap

- [ ] Mobile app (Flutter/React Native)
- [ ] Real-time notifications (WebSockets)
- [ ] Advanced analytics dashboard
- [ ] Multi-language support (i18n)
- [ ] GPS tracking integration
- [ ] Insurance management
- [ ] Automated maintenance scheduling
- [ ] Loyalty program for customers
- [ ] QR code-based vehicle pickup
- [ ] Integration with accounting software

---

**Built with ❤️ using Laravel**
```

This comprehensive README covers everything from installation to deployment, making it easy for anyone to understand and set up your vehicle rental system. You can customize the sections based on your actual implementation details!