# Backend API - Laravel

Laravel-based RESTful API backend for Portfolio Website, providing authentication, content management, and data delivery with Laravel Jetstream admin panel.

## 🏗️ Architecture

This backend follows Laravel best practices with:

- **MVC Pattern**: Models, Controllers, and Views (Blade + Livewire)
- **API Resources**: Clean JSON transformations
- **Service Layer**: Business logic abstraction (when needed)
- **Repository Pattern**: Can be implemented for complex queries
- **Middleware**: Authentication, CORS, rate limiting
- **RESTful Design**: Standard HTTP methods and status codes

## 🚀 Tech Stack

- **Runtime**: PHP 8.1+
- **Framework**: Laravel 10.x
- **Authentication**: Laravel Jetstream + Sanctum
- **Admin UI**: Livewire 3.x (reactive components)
- **Database**: MySQL 8.0 (via XAMPP)
- **ORM**: Eloquent (Laravel's built-in ORM)
- **Validation**: Laravel Form Requests
- **File Upload**: Laravel Storage + Intervention Image
- **Email**: Laravel Mail + Mailtrap/SMTP
- **Testing**: Pest PHP / PHPUnit
- **API Documentation**: Laravel API Resources

## 📋 Prerequisites

- PHP 8.1 or higher
- Composer
- MySQL (via XAMPP port 3306)
- Apache (via XAMPP port 80)

## 🛠️ Installation

### 1. Install Dependencies

```bash
composer install
```

### 2. Environment Configuration

Copy the example environment file:

```bash
copy .env.example .env
```

Edit `.env` with your configuration:

```env
APP_NAME="Portfolio V2"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost/Portfolio_v2/backend/public

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=portfolio_v2
DB_USERNAME=ali
DB_PASSWORD=aL1889900@@@

MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_mailtrap_username
MAIL_PASSWORD=your_mailtrap_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@portfolio.com
MAIL_FROM_NAME="${APP_NAME}"
```

### 3. Generate Application Key

```bash
php artisan key:generate
```

### 4. Database Setup

Create database in phpMyAdmin (http://localhost/phpmyadmin):

```sql
CREATE DATABASE portfolio_v2 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Run migrations:

```bash
php artisan migrate
```

Seed database with sample data (optional):

```bash
php artisan db:seed
```

### 5. Storage Link

Create symbolic link for storage:

```bash
php artisan storage:link
```

### 6. Create Admin User

```bash
php artisan make:filament-user
# Or via tinker:
php artisan tinker
>>> \App\Models\User::create(['name' => 'Admin', 'email' => 'admin@portfolio.com', 'password' => bcrypt('admin123')]);
```

## 🚦 Running the Server

### XAMPP Setup (Required)

**IMPORTANT:** This project uses XAMPP Apache (Port 80), **NOT** `php artisan serve`

1. **Start XAMPP Control Panel**
   - Start Apache (Port 80)
   - Start MySQL (Port 3306)

2. **Access the application:**
   - **API**: http://localhost/Portfolio_v2/backend/public/api
   - **Backend Public**: http://localhost/Portfolio_v2/backend/public

3. **Verify setup:**
   - Open http://localhost/Portfolio_v2/backend/public
   - You should see Laravel default page or your app
   - API endpoints accessible at `/api/*`

**Why XAMPP instead of `php artisan serve`?**
- Matches production Apache environment
- Supports proper .htaccess URL rewriting
- Windows 11 development environment compatibility
- Consistent with frontend development workflow

## 📁 Project Structure

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── API/            # API controllers
│   │   │   └── Web/            # Web controllers
│   │   ├── Middleware/         # Custom middleware
│   │   ├── Requests/           # Form request validation
│   │   └── Resources/          # API resources (JSON transformers)
│   ├── Models/                 # Eloquent models
│   ├── Helpers/                # Helper functions
│   │   └── ImageHelper.php    # Image processing utilities
│   └── Livewire/               # Livewire components
├── config/                     # Configuration files
├── database/
│   ├── factories/              # Model factories (testing)
│   ├── migrations/             # Database migrations
│   └── seeders/                # Database seeders
├── public/                     # Public entry point
│   ├── index.php              # Main entry
│   ├── uploads/               # Uploaded files
│   └── .htaccess              # Apache rewrite rules
├── resources/
│   ├── views/                 # Blade templates
│   │   ├── layouts/           # Layout templates
│   │   ├── components/        # Blade components
│   │   └── livewire/          # Livewire views
│   └── js/                    # Frontend assets (if any)
├── routes/
│   ├── api.php                # API routes
│   ├── web.php                # Web routes
│   └── channels.php           # Broadcast channels
├── storage/
│   ├── app/                   # App-specific storage
│   ├── logs/                  # Application logs
│   └── framework/             # Framework files
├── tests/
│   ├── Feature/               # Feature tests
│   └── Unit/                  # Unit tests
├── .env                       # Environment config (not in git)
├── composer.json              # PHP dependencies
├── artisan                    # CLI tool
└── README.md                  # This file
```

## 🔐 Environment Variables

### Required Variables

```env
# Application
APP_NAME=Portfolio_V2
APP_ENV=local|production
APP_KEY=base64:...              # Generated by php artisan key:generate
APP_DEBUG=true|false
APP_URL=http://localhost/Portfolio_v2/backend/public

# Database (MySQL via XAMPP)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=portfolio_v2
DB_USERNAME=ali
DB_PASSWORD=aL1889900@@@

# Session & Cache
SESSION_DRIVER=file
CACHE_DRIVER=file
QUEUE_CONNECTION=sync

# Mail (for contact form)
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io      # Or your SMTP server
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME="${APP_NAME}"

# Sanctum (API Authentication)
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1,localhost:5173

# File Upload
MAX_UPLOAD_SIZE=10240           # KB (10MB)
ALLOWED_IMAGE_TYPES=jpg,jpeg,png,gif,webp
```

### Security Notes

- ✅ Never commit `.env` file
- ✅ Use strong `APP_KEY` (auto-generated)
- ✅ Change default database password in production
- ✅ Use proper MAIL settings for production
- ✅ Enable HTTPS in production
- ✅ Set `APP_DEBUG=false` in production

## 📡 API Endpoints

### Authentication

```http
POST   /api/register           # Register new user
POST   /api/login              # Login user
POST   /api/logout             # Logout user (auth required)
GET    /api/user               # Get authenticated user (auth required)
```

### Projects

```http
GET    /api/projects           # Get all projects (paginated)
GET    /api/projects/{id}      # Get single project
POST   /api/projects           # Create project (auth required)
PUT    /api/projects/{id}      # Update project (auth required)
DELETE /api/projects/{id}      # Delete project (auth required)
```

### Blog Posts

```http
GET    /api/posts              # Get all posts (paginated)
GET    /api/posts/{id}         # Get single post
GET    /api/posts/category/{slug}  # Get posts by category
GET    /api/posts/tag/{slug}   # Get posts by tag
POST   /api/posts              # Create post (auth required)
PUT    /api/posts/{id}         # Update post (auth required)
DELETE /api/posts/{id}         # Delete post (auth required)
```

### Categories

```http
GET    /api/categories         # Get all categories
GET    /api/categories/{slug}  # Get category with posts
```

### Contact

```http
POST   /api/contact            # Submit contact form
GET    /api/contacts           # Get all messages (auth required)
PUT    /api/contacts/{id}/read # Mark as read (auth required)
DELETE /api/contacts/{id}      # Delete message (auth required)
```

### API Response Format

**Success Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "title": "Project Title",
    "slug": "project-title",
    "description": "Description here...",
    "created_at": "2025-10-10T10:00:00.000000Z"
  },
  "message": "Project retrieved successfully"
}
```

**Error Response:**
```json
{
  "success": false,
  "message": "Validation error",
  "errors": {
    "email": ["The email field is required."]
  }
}
```

## 🧪 Testing

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/ProjectTest.php

# Run with coverage
php artisan test --coverage

# Using Pest (if installed)
./vendor/bin/pest

# With coverage
./vendor/bin/pest --coverage
```

### Test Structure

```php
// tests/Feature/ProjectTest.php
test('can create project', function () {
    $user = User::factory()->create();
    
    $response = $this->actingAs($user)
        ->postJson('/api/projects', [
            'title' => 'Test Project',
            'description' => 'Test Description',
        ]);
    
    $response->assertStatus(201)
             ->assertJsonStructure([
                 'success',
                 'data' => ['id', 'title', 'slug'],
                 'message'
             ]);
             
    $this->assertDatabaseHas('projects', [
        'title' => 'Test Project'
    ]);
});
```

## 🔒 Security

### Implemented Security Features

- ✅ **CSRF Protection**: Laravel's built-in CSRF middleware
- ✅ **XSS Protection**: Blade escaping by default
- ✅ **SQL Injection**: Eloquent ORM with parameter binding
- ✅ **Authentication**: Laravel Sanctum for API
- ✅ **Authorization**: Laravel Gates & Policies
- ✅ **Rate Limiting**: Throttle middleware
- ✅ **Input Validation**: Form Request validation
- ✅ **Password Hashing**: Bcrypt (auto by Laravel)
- ✅ **CORS**: Laravel CORS middleware

### Security Checklist

- [ ] Change default database credentials
- [ ] Use environment variables for secrets
- [ ] Enable HTTPS in production
- [ ] Set proper file permissions (755 for dirs, 644 for files)
- [ ] Configure CSP headers
- [ ] Regular dependency updates: `composer update`
- [ ] Security audit: `composer audit`
- [ ] Disable debug mode in production
- [ ] Configure proper CORS origins

## 📊 Database

### Migrations

```bash
# Create new migration
php artisan make:migration create_projects_table

# Run migrations
php artisan migrate

# Rollback last migration
php artisan migrate:rollback

# Rollback all migrations
php artisan migrate:reset

# Refresh database (rollback all & re-run)
php artisan migrate:refresh

# Refresh with seed
php artisan migrate:refresh --seed
```

### Seeders

```bash
# Create seeder
php artisan make:seeder ProjectSeeder

# Run all seeders
php artisan db:seed

# Run specific seeder
php artisan db:seed --class=ProjectSeeder
```

### Database Schema

Main tables:
- `users` - User accounts
- `projects` - Portfolio projects
- `posts` - Blog posts
- `categories` - Post categories
- `tags` - Post tags  
- `contacts` - Contact form submissions
- `services` - Services offered
- `awards` - Awards & achievements
- `galleries` - Image galleries

## 🐛 Debugging

### Laravel Telescope (Optional)

```bash
# Install Telescope
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate

# Access Telescope
http://localhost/Portfolio_v2/backend/public/telescope
```

### Logging

Logs are stored in `storage/logs/`:
- `laravel.log` - General application logs

```php
// Using the logger
use Illuminate\Support\Facades\Log;

Log::info('User logged in', ['user_id' => $user->id]);
Log::error('Failed to process payment', ['error' => $e->getMessage()]);
Log::debug('Debug information', ['data' => $data]);
```

### Common Artisan Commands

```bash
# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Optimize for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize

# Database
php artisan db:show              # Show database info
php artisan db:table users       # Show table structure
php artisan db:monitor           # Monitor connections

# Queue (if using)
php artisan queue:work
php artisan queue:listen
php artisan queue:retry all
```

## 🚀 Deployment

### Preparation

```bash
# 1. Optimize for production
composer install --optimize-autoloader --no-dev
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 2. Set permissions
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# 3. Update .env
APP_ENV=production
APP_DEBUG=false
```

### Apache Configuration

**`.htaccess` in public folder:**

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
```

### Production Environment

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_CONNECTION=mysql
DB_HOST=your_production_host
DB_PORT=3306
DB_DATABASE=production_db
DB_USERNAME=production_user
DB_PASSWORD=strong_password_here

MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
# ... production mail settings
```

## 📈 Performance

### Optimization Tips

```bash
# 1. Enable OPcache in php.ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000

# 2. Use Redis for cache (optional)
composer require predis/predis
# Update .env: CACHE_DRIVER=redis

# 3. Queue jobs for heavy tasks
php artisan queue:table
php artisan migrate
# Update .env: QUEUE_CONNECTION=database
```

### Database Optimization

- Add indexes to frequently queried columns
- Use eager loading to prevent N+1 queries
- Implement database query caching
- Use pagination for large datasets

## 🔧 Artisan Commands

```bash
# Development
php artisan serve                      # Start dev server
php artisan tinker                     # Interactive shell
php artisan make:controller ProjectController  # Create controller
php artisan make:model Project -m      # Create model + migration
php artisan make:request ProjectRequest  # Create form request

# Database
php artisan migrate                    # Run migrations
php artisan db:seed                    # Seed database
php artisan migrate:fresh --seed       # Fresh database with seed

# Cache
php artisan cache:clear                # Clear application cache
php artisan config:cache               # Cache configuration

# Routes
php artisan route:list                 # List all routes
```

## 📝 Common Issues

### Storage Permission Denied

```bash
# Fix storage permissions
sudo chmod -R 775 storage bootstrap/cache
sudo chown -R www-data:www-data storage bootstrap/cache
```

### Database Connection Failed

- Check MySQL is running in XAMPP
- Verify database credentials in `.env`
- Ensure database exists in phpMyAdmin
- Check port 3306 is not blocked

### 404 on All Routes

- Ensure Apache `mod_rewrite` is enabled
- Check `.htaccess` exists in public folder
- Verify `DocumentRoot` points to `public` folder

### Token Mismatch Error

```bash
# Clear session and cache
php artisan cache:clear
php artisan config:clear
php artisan session:table
php artisan migrate
```

## 📚 Additional Resources

- [Laravel Documentation](https://laravel.com/docs/10.x)
- [Laravel Jetstream](https://jetstream.laravel.com/)
- [Laravel Sanctum](https://laravel.com/docs/10.x/sanctum)
- [Livewire Documentation](https://livewire.laravel.com/)
- [Eloquent ORM](https://laravel.com/docs/10.x/eloquent)
- [Laravel Best Practices](https://github.com/alexeymezenin/laravel-best-practices)

## 📞 Support

For backend-specific issues:

1. Check logs in `storage/logs/laravel.log`
2. Review error messages and stack traces
3. Consult Laravel documentation
4. Check database connections
5. Verify environment variables

---

## 📈 Recent Updates

### Phase 2.1 - Production Ready (October 13, 2025)

**Completed Security & Architecture Improvements:**
- ✅ All 9 controllers using FormRequests for validation
- ✅ All controllers using API Resources for responses
- ✅ Database transactions for bulk operations
- ✅ Input sanitization (XSS protection)
- ✅ Rate limiting on contact form (5 req/min)
- ✅ Error handling with proper logging
- ✅ Authentication middleware on all admin routes
- ✅ SEO fields support in Post, Project, Category models

**API Endpoints Status:**
- ✅ Authentication (Login, Register, Logout)
- ✅ Posts (CRUD with FormRequests & Resources)
- ✅ Projects (CRUD with FormRequests & Resources)
- ✅ Categories (CRUD with Resources)
- ✅ Awards (CRUD with Gallery relationships)
- ✅ Services (CRUD with active/inactive status)
- ✅ Gallery (CRUD with image management)
- ✅ Testimonials (CRUD)
- ✅ Contact (Submit with rate limiting)
- ✅ Newsletter (Subscribe/Unsubscribe)
- ✅ Settings (Site-wide settings)

**Backend Status:** In Development (65% complete - see PROJECT_STATUS.md)

**Completed:**
- ✅ Database schema (17 migrations)
- ✅ All 8 models with traits (HasSeoFields, HasSlug, SoftDeletes)
- ✅ 1/9 controllers complete (AwardController)
- ✅ Form Requests and API Resources for Posts
- ✅ Authentication with Laravel Sanctum

**Next Steps:**
- Complete remaining 8 controllers (Post, Project, Category, etc.)
- Write comprehensive feature tests
- Add automated backup system

---

**Framework**: Laravel 10.x
**PHP Version**: 8.2+
**Database**: MySQL 8.0 via XAMPP
**Last Updated**: October 15, 2025
