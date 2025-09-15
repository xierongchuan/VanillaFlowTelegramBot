# VanillaFlowTelegramBot - Project Overview

## Project Description

VanillaFlowTelegramBot is a Laravel-based Telegram bot designed for expense request management in corporate environments. It provides a complete workflow for expense requests, including submission, approval, and processing through Telegram.

## Architecture Overview

### Core Technology Stack
- **Framework**: Laravel 12.x (PHP 8.4+)
- **Bot Framework**: Nutgram/Laravel for Telegram integration
- **Database**: MySQL/MariaDB
- **Cache/Session**: Redis/Valkey
- **Queue**: Redis/Valkey
- **Authentication**: Laravel Sanctum
- **Frontend Assets**: Vite with TailwindCSS

### Project Structure

```
VanillaFlowTelegramBot/
├── app/
│   ├── Bot/                    # Telegram bot handlers
│   │   ├── Commands/           # Bot command handlers
│   │   ├── Conversations/      # Interactive conversations
│   │   ├── Callbacks/          # Callback button handlers
│   │   ├── Middleware/         # Bot middleware (auth, roles)
│   │   └── Abstracts/          # Base classes
│   ├── Controllers/            # API controllers
│   ├── Models/                 # Eloquent models
│   ├── Services/               # Business logic services
│   ├── Enums/                  # Enums (Role, ExpenseStatus)
│   └── DTO/                    # Data Transfer Objects
├── config/                     # Laravel configuration
├── database/
│   └── migrations/            # Database migrations
└── routes/                     # API and web routes
```

## Core Components

### 1. User Management System
- **Model**: `app/Models/User.php`
- **Roles**: USER, CASHIER, DIRECTOR
- **Authentication**: Laravel Sanctum API tokens
- **VCRM Integration**: External user service integration via `app/Services/VCRM/UserService.php`

### 2. Expense Request Workflow
- **Model**: `app/Models/ExpenseRequest.php`
- **Status Flow**: PENDING → APPROVED/DECLINED → ISSUED/CANCELLED
- **Approval Tracking**: `app/Models/ExpenseApproval.php`
- **Audit Logging**: `app/Models/AuditLog.php`

### 3. Telegram Bot Architecture
- **Handler Types**:
  - Commands: `/start`, `/history`, `/pending`
  - Conversations: Interactive forms for expense requests
  - Callbacks: Button click handlers for approvals/rejections
- **Role-Based Access**: Different menus and permissions per role
- **Middleware**: Authentication and role checking

### 4. Service Layer
- **Expense Management**: `app/Services/ExpenseRequestService.php`
- **Approval System**: `app/Services/ExpenseApprovalService.php`
- **Notifications**: `app/Services/TelegramNotificationService.php`
- **User Finder**: `app/Services/UserFinderService.php`
- **Validation**: `app/Services/ValidationService.php`

## Database Schema

### Core Tables
1. **users** - User accounts with role-based access
2. **expense_requests** - Main expense request data
3. **expense_approvals** - Approval workflow tracking
4. **audit_logs** - Comprehensive audit trail
5. **personal_access_tokens** - API authentication

### Key Relationships
- Expense Request → User (requester, director, cashier)
- Expense Request → Multiple Approvals
- Audit Log → User (actor)

## User Roles and Permissions

### USER
- Submit expense requests
- View own request history
- Cancel own pending requests

### DIRECTOR
- View pending expense requests
- Approve/decline requests with comments
- View approval history
- Access to all company requests

### CASHIER
- Submit expense requests (new feature)
- View own request history
- View approved requests
- Issue payments (full or partial)
- Mark requests as issued
- View issuance history

## Key Features

### 1. Multi-Language Support
- Primary language: Russian (UI labels and messages)
- Configurable locale support

### 2. Audit Trail
- Complete action logging
- Actor tracking for all operations
- JSON payload storage for detailed changes

### 3. API Endpoints
- RESTful API for external integration
- Swagger documentation available
- Authentication via Sanctum tokens

### 4. Notification System
- Telegram-based notifications
- Role-specific alerts
- Status change notifications

## Development Commands

### Setup
```bash
composer install         # Install PHP dependencies
npm install              # Install Node.js dependencies
```

### Running
```bash
docker-compose up -d --build  # Start with Docker
php artisan serve             # Development server
php artisan queue:listen      # Process queues
```

### Testing
```bash
composer test               # Run tests
php artisan config:clear     # Clear config cache
```

### Code Quality
```bash
composer fix               # Fix code style
php-cs-fixer fix           # Code formatting
```

## Configuration

### Environment Variables
- `TELEGRAM_TOKEN` - Bot API token
- `DB_*` - Database connection
- `REDIS_*` - Redis/Valkey configuration
- `APP_ENV` - Environment (local/production)

### Key Configuration Files
- `config/nutgram.php` - Telegram bot settings
- `config/database.php` - Database configuration
- `config/services.php` - External service configuration

## Security Features

1. **Authentication**: Laravel Sanctum tokens
2. **Authorization**: Role-based middleware
3. **Input Validation**: Service-level validation
4. **Audit Logging**: Complete action tracking
5. **Environment Protection**: Secure configuration handling

## Integration Points

### VCRM Integration
- External user management system
- Company and department data
- Real-time user synchronization

### Telegram Bot
- Interactive conversations
- Callback button handling
- Rich message formatting
- File upload support
- **Enhanced Cashier Role**: Cashiers can now submit expense requests and view personal history

## API Documentation

Comprehensive Swagger documentation is available:
- `swagger.json` - OpenAPI 3.0 specification
- `API_DOCUMENTATION.md` - Detailed API guide

## Development Notes

### Code Style
- PSR-4 autoloading
- Strict type declarations
- PHP 8.4+ features
- Laravel best practices

### Testing
- Pest PHP framework
- Feature and unit tests
- API endpoint testing

### Deployment
- Docker containerization
- Environment-based configuration
- Queue processing for notifications