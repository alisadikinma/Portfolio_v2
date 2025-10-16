# Portfolio v2 - Personal Portfolio & Blog Platform

[![Laravel](https://img.shields.io/badge/Laravel-10.x-FF2D20?logo=laravel)](https://laravel.com)
[![Vue.js](https://img.shields.io/badge/Vue.js-3.5-4FC08D?logo=vue.js)](https://vuejs.org)
[![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?logo=php)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.x-4479A1?logo=mysql)](https://mysql.com)

A modern, full-stack portfolio website with integrated blog, project showcase, and admin CMS. Built with Laravel 10 REST API backend and Vue 3 SPA frontend.

---

## 📋 Table of Contents

- [Features](#-features)
- [Tech Stack](#-tech-stack)
- [Prerequisites](#-prerequisites)
- [Installation](#-installation)
- [Development Workflow](#-development-workflow)
- [Project Structure](#-project-structure)
- [Environment Configuration](#-environment-configuration)
- [Available Commands](#-available-commands)
- [Testing](#-testing)
- [API Documentation](#-api-documentation)
- [Deployment](#-deployment)
- [Documentation](#-documentation)
- [Contributing](#-contributing)
- [License](#-license)

---

## ✨ Features

### Public Features
- 🏠 **Homepage** - Hero section, featured projects, recent blog posts
- 📝 **Blog System** - Full-featured blog with categories, search, and pagination
- 💼 **Project Showcase** - Portfolio projects with detailed case studies
- 🏆 **Awards & Recognition** - Display achievements and certifications
- 🎨 **Gallery** - Image gallery with lightbox viewer
- 📧 **Contact Form** - Contact form with email notifications
- 📰 **Newsletter** - Email subscription system
- 🔍 **SEO Optimized** - Meta tags, Open Graph, Schema.org structured data
- 📱 **Responsive Design** - Mobile-first, works on all devices
- ⚡ **Fast Performance** - Optimized images, lazy loading, caching

### Admin Features (CMS)
- 🔐 **Authentication** - Secure login with JWT tokens (Laravel Sanctum)
- 📊 **Dashboard** - Analytics and statistics overview
- ✍️ **Content Management** - CRUD for posts, projects, categories, services
- 📝 **Rich Text Editor** - CKEditor 5 with full formatting, code blocks, media embed
- 🖼️ **Media Management** - Drag & drop image upload with preview
- 📂 **Category Management** - Accessible category selector with Headless UI
- 📈 **SEO Tools** - Meta tags, Open Graph, focus keywords, canonical URLs
- 👥 **User Management** - Multi-user support with role-based access
- 📧 **Newsletter Management** - View subscribers, send campaigns
- 💬 **Contact Inquiries** - View and respond to contact form submissions

---

## 🛠️ Tech Stack

### Backend
- **Framework:** Laravel 10.x
- **Language:** PHP 8.2
- **Database:** MySQL 8.x
- **Authentication:** Laravel Sanctum (JWT)
- **API:** RESTful API with JSON responses
- **Storage:** Local filesystem (images, uploads)
- **Validation:** Laravel Form Requests
- **Testing:** PHPUnit, Pest

### Frontend
- **Framework:** Vue 3.5 (Composition API)
- **Build Tool:** Vite 7.1 (Rolldown)
- **State Management:** Pinia 3.0
- **Routing:** Vue Router 4.5
- **HTTP Client:** Axios 1.12
- **Styling:** Tailwind CSS 4.1
- **UI Components:** Headless UI 1.7, Heroicons 2.2
- **Rich Text Editor:** CKEditor 5 (CDN)
- **Testing:** Playwright (browser automation)

### Development Environment
- **Server:** XAMPP (Apache + MySQL)
- **OS:** Windows 11
- **Package Manager:** Composer (backend), npm (frontend)

---

## 📦 Prerequisites

Before you begin, ensure you have the following installed:

### Required Software
- **PHP 8.2+** (Check: `php -v`)
- **Composer 2.x** (Check: `composer -V`)
- **Node.js 18+** (Check: `node -v`)
- **npm 9+** (Check: `npm -v`)
- **MySQL 8.x** (Check: `mysql --version`)
- **XAMPP** (or any Apache + MySQL stack)

### Recommended Tools
- **Git** for version control
- **VSCode** with extensions: Vue Language Features (Volar), PHP Intelephense
- **Postman** or **Insomnia** for API testing

---

## 🚀 Installation

### 1. Clone Repository
```bash
git clone <repository-url>
cd Portfolio_v2
```

### 2. Backend Setup

```bash
# Navigate to backend directory
cd backend

# Install PHP dependencies
composer install

# Copy environment file
copy .env.example .env

# Generate application key
php artisan key:generate

# Configure database in .env file
# DB_DATABASE=portfolio_v2
# DB_USERNAME=ali
# DB_PASSWORD=aL1889900@@@

# Run migrations
php artisan migrate

# Seed database with sample data (optional)
php artisan db:seed

# Create storage link for images
php artisan storage:link

# Note: Backend runs on XAMPP Apache (Port 80)
# Do NOT use 'php artisan serve' - XAMPP handles the backend
# Backend API URL: http://localhost/Portfolio_v2/backend/public/api
```

### 3. Frontend Setup

```bash
# Navigate to frontend directory (from root)
cd frontend

# Install JavaScript dependencies
npm install

# Copy environment file
copy .env.example .env

# Configure API URL in .env file
# VITE_API_URL=http://localhost/Portfolio_v2/backend/public/api

# Start development server
npm run dev
# Frontend running at: http://localhost:5173 (Vite default port)
```

### 4. Create Admin User

```bash
cd backend
php artisan tinker

# In tinker console:
User::create([
    'name' => 'Admin',
    'email' => 'admin@example.com',
    'password' => bcrypt('password')
]);
```

---

## 💻 Development Workflow

### Starting Development Servers

**Prerequisites:**
- Ensure XAMPP Control Panel is running
- Apache (Port 80) must be started
- MySQL (Port 3306) must be started

**Start Frontend Only:**

```bash
# Frontend terminal
cd C:\xampp\htdocs\Portfolio_v2\frontend
npm run dev
```

### Accessing the Application

- **Frontend (Public):** http://localhost:5173
- **Frontend (Admin):** http://localhost:5173/admin
- **Backend API:** http://localhost/Portfolio_v2/backend/public/api

### Development URLs

| Service | URL | Purpose |
|---------|-----|---------|
| Frontend Dev | `http://localhost:5173` | Vite development server |
| Backend API | `http://localhost/Portfolio_v2/backend/public/api` | Laravel API (XAMPP) |
| Backend Public | `http://localhost/Portfolio_v2/backend/public` | Laravel public folder |
| Database | `localhost:3306` | MySQL database |
| PHPMyAdmin | `http://localhost/phpmyadmin` | Database management |

---

## 📁 Project Structure

```
Portfolio_v2/
├── backend/                    # Laravel 10 API Backend
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/    # API Controllers
│   │   │   ├── Requests/       # Form Validation
│   │   │   └── Resources/      # API Response Transformers
│   │   ├── Models/             # Eloquent Models
│   │   └── Traits/             # Reusable Traits (SEO, etc.)
│   ├── database/
│   │   ├── migrations/         # Database Schema
│   │   └── seeders/            # Sample Data
│   ├── routes/
│   │   └── api.php             # API Routes
│   ├── storage/                # File Storage
│   ├── tests/                  # Backend Tests
│   └── .env                    # Backend Configuration
│
├── frontend/                   # Vue 3 SPA Frontend
│   ├── src/
│   │   ├── api/                # API Service Layer
│   │   ├── assets/             # Static Assets
│   │   ├── components/         # Vue Components
│   │   │   ├── base/           # Base UI Components
│   │   │   ├── blog/           # Blog Components (NEW)
│   │   │   │   ├── RichTextEditor.vue   # CKEditor 5 integration
│   │   │   │   ├── ImageUploader.vue    # Drag & drop upload
│   │   │   │   ├── CategorySelect.vue   # Category selector
│   │   │   │   └── BlogPostForm.vue     # Post form
│   │   │   └── project/        # Project Components
│   │   ├── composables/        # Vue Composables
│   │   ├── router/             # Vue Router Config
│   │   ├── stores/             # Pinia State Management
│   │   ├── views/              # Page Components
│   │   │   ├── auth/           # Auth Pages
│   │   │   └── admin/          # Admin Pages
│   │   ├── App.vue             # Root Component
│   │   └── main.js             # Entry Point
│   ├── public/                 # Public Static Files
│   └── .env                    # Frontend Configuration
│
├── .claude/                    # Claude Code Configuration
│   ├── agents/                 # Subagent System
│   │   ├── orchestrator.md     # Multi-agent coordinator
│   │   ├── laravel-specialist.md
│   │   ├── vue-expert.md
│   │   ├── database-administrator.md
│   │   ├── qa-expert.md
│   │   └── documentation-engineer.md
│   └── prompts/                # Development Prompts
│       ├── phase-1_frontend-init_20251013-1244.md
│       ├── phase-2_backend-controllers_20251013-1244.md
│       ├── phase-3_blog-system_20251013-1244.md
│       └── phase-4_qa-testing_20251013-1244.md
│
├── PROJECT_STATUS.md           # Current Development Status
├── PROJECT_INSTRUCTIONS.md     # Development Guidelines
├── README.md                   # This File
└── portfolio_v2.sql            # Database Backup
```

---

## ⚙️ Environment Configuration

### Backend (.env)

```env
# Application
APP_NAME="Portfolio v2"
APP_ENV=local
APP_KEY=base64:... # Generated by php artisan key:generate
APP_DEBUG=true
APP_URL=http://localhost/Portfolio_v2/backend/public

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=portfolio_v2
DB_USERNAME=ali
DB_PASSWORD=aL1889900@@@

# Mail (for contact form, newsletter)
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@portfolio.test
MAIL_FROM_NAME="${APP_NAME}"

# Sanctum (Authentication)
SANCTUM_STATEFUL_DOMAINS=localhost:3000
SESSION_DOMAIN=localhost

# File Storage
FILESYSTEM_DISK=public
```

### Frontend (.env)

```env
# API Configuration
VITE_API_URL=http://localhost/Portfolio_v2/backend/public/api
VITE_APP_NAME="Portfolio v2"
VITE_API_TIMEOUT=30000

# Development
VITE_APP_ENV=development

# Features
VITE_ENABLE_DARK_MODE=true
VITE_ENABLE_ANALYTICS=false
```

---

## 🎮 Available Commands

### Backend Commands

**IMPORTANT:** Backend runs on XAMPP Apache (Port 80)
- **DO NOT** use `php artisan serve`
- Ensure XAMPP Apache is started before running commands
- API accessible at: http://localhost/Portfolio_v2/backend/public/api

```bash
# Database Management
php artisan migrate              # Run migrations
php artisan migrate:fresh        # Drop all tables and re-run migrations
php artisan db:seed              # Seed database
php artisan migrate:fresh --seed # Fresh install with data

# Cache Management
php artisan cache:clear          # Clear application cache
php artisan config:clear         # Clear config cache
php artisan route:clear          # Clear route cache
php artisan view:clear           # Clear view cache
php artisan optimize:clear       # Clear all caches

# Database
php artisan migrate:status       # Show migration status
php artisan migrate:rollback     # Rollback last migration
php artisan db:wipe              # Drop all tables

# Code Generation
php artisan make:controller      # Create controller
php artisan make:model           # Create model
php artisan make:migration       # Create migration
php artisan make:seeder          # Create seeder
php artisan make:request         # Create form request
php artisan make:resource        # Create API resource

# Testing
php artisan test                 # Run all tests
php artisan test --filter=PostTest  # Run specific test
```

### Frontend Commands

```bash
# Development
npm run dev                      # Start dev server (Vite)
npm run build                    # Build for production
npm run preview                  # Preview production build

# Code Quality
npm run lint                     # Run ESLint
npm run format                   # Format with Prettier

# Dependencies
npm install                      # Install dependencies
npm update                       # Update dependencies
npm outdated                     # Check for outdated packages
```

---

## 🧪 Testing

### Backend Testing (Laravel)

```bash
cd backend

# Run all tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit

# Run specific test file
php artisan test tests/Feature/PostTest.php

# Run with coverage
php artisan test --coverage

# Run in parallel
php artisan test --parallel
```

### Frontend Testing (Playwright)

Frontend testing uses Playwright MCP (Model Context Protocol) for browser automation testing. Tests are run through Claude Code's Playwright integration.

**Test Coverage:**
- ✅ Page load tests
- ✅ CRUD operation tests
- ✅ Form validation tests
- ✅ Authentication flow tests
- ✅ Responsive design tests

---

## 📖 API Documentation

### Base URL
```
http://localhost/Portfolio_v2/backend/public/api
```

### Authentication
All admin endpoints require authentication using Laravel Sanctum tokens.

**Header:**
```
Authorization: Bearer {token}
```

### Public Endpoints

#### Posts (Blog)
```
GET    /api/posts                # List all posts
GET    /api/posts/{slug}         # Get single post
GET    /api/posts?category={id}  # Filter by category
GET    /api/posts?q={search}     # Search posts
```

#### Projects
```
GET    /api/projects             # List all projects
GET    /api/projects/{slug}      # Get single project
GET    /api/projects?featured=1  # Get featured projects
```

#### Categories
```
GET    /api/categories           # List all categories
GET    /api/categories/{id}      # Get single category with posts
```

#### Awards
```
GET    /api/awards               # List all awards
GET    /api/awards/{id}          # Get single award
```

#### Services
```
GET    /api/services             # List all services
GET    /api/services?active=1    # Get active services only
```

#### Contact
```
POST   /api/contact              # Submit contact form
```

#### Newsletter
```
POST   /api/newsletter/subscribe # Subscribe to newsletter
```

### Admin Endpoints (Authentication Required)

#### Posts Management
```
POST   /api/admin/posts          # Create post
PUT    /api/admin/posts/{id}     # Update post
DELETE /api/admin/posts/{id}     # Delete post
```

#### Projects Management
```
POST   /api/admin/projects       # Create project
PUT    /api/admin/projects/{id}  # Update project
DELETE /api/admin/projects/{id}  # Delete project
```

For complete API documentation, see: [API_ENDPOINTS.md](./API_ENDPOINTS.md) *(coming soon)*

---

## 🚢 Deployment

### Prerequisites for Production
- VPS or Cloud Server (DigitalOcean, AWS, etc.)
- Nginx or Apache web server
- PHP 8.2+ with required extensions
- MySQL 8.x
- SSL Certificate (Let's Encrypt recommended)
- Domain name

### Backend Deployment

```bash
# Clone repository
git clone <repository-url>
cd Portfolio_v2/backend

# Install dependencies
composer install --optimize-autoloader --no-dev

# Set environment to production
cp .env.example .env
# Edit .env: APP_ENV=production, APP_DEBUG=false

# Generate key
php artisan key:generate

# Run migrations
php artisan migrate --force

# Cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Set permissions
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### Frontend Deployment

```bash
cd Portfolio_v2/frontend

# Install dependencies
npm ci

# Build for production
npm run build

# Deploy dist/ folder to web server
# Or use services like Netlify, Vercel, Cloudflare Pages
```

### Web Server Configuration

**Nginx Example:**
```nginx
server {
    listen 80;
    server_name example.com;
    root /var/www/portfolio/backend/public;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
    }
}
```

For detailed deployment guide, see: [DEPLOYMENT.md](./DEPLOYMENT.md) *(coming soon)*

---

## 📚 Documentation

### Project Documentation
- [README.md](./README.md) - This file (project overview)
- [PROJECT_STATUS.md](./PROJECT_STATUS.md) - Current development status
- [PROJECT_INSTRUCTIONS.md](./PROJECT_INSTRUCTIONS.md) - Development guidelines
- [backend/README.md](./backend/README.md) - Backend-specific documentation
- [frontend/README.md](./frontend/README.md) - Frontend-specific documentation
- [backend/SEO_IMPLEMENTATION.md](./backend/SEO_IMPLEMENTATION.md) - SEO features guide

### Claude Code System
- [.claude/agents/README.md](./.claude/agents/README.md) - Subagent system overview
- [.claude/prompts/README.md](./.claude/prompts/README.md) - Development prompts guide

### External Documentation
- [Laravel Documentation](https://laravel.com/docs/10.x)
- [Vue 3 Documentation](https://vuejs.org)
- [Tailwind CSS Documentation](https://tailwindcss.com/docs)
- [Pinia Documentation](https://pinia.vuejs.org)
- [Laravel Sanctum](https://laravel.com/docs/10.x/sanctum)

---

## 🤝 Contributing

Contributions are welcome! Please follow these guidelines:

### Development Workflow
1. Read [PROJECT_INSTRUCTIONS.md](./PROJECT_INSTRUCTIONS.md) for conventions
2. Create a feature branch: `git checkout -b feature/your-feature`
3. Make your changes following project conventions
4. Write tests for new features
5. Run tests: `php artisan test` (backend), Playwright (frontend)
6. Update documentation (README, PROJECT_STATUS)
7. Commit changes: `git commit -m "feat: add your feature"`
8. Push to branch: `git push origin feature/your-feature`
9. Open a Pull Request

### Code Style
- **Backend:** Follow PSR-12 coding standards
- **Frontend:** Follow Vue 3 Style Guide
- **Commits:** Use Conventional Commits format
- **Testing:** Maintain 80%+ code coverage

### Commit Message Format
```
type(scope): subject

body (optional)

footer (optional)
```

**Types:**
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code formatting (no logic changes)
- `refactor`: Code restructuring (no behavior changes)
- `test`: Adding or updating tests
- `chore`: Maintenance tasks

---

## 📄 License

This project is proprietary and confidential.

**Copyright © 2025 Ali Sadikin**

All rights reserved. Unauthorized copying, distribution, or modification of this project, via any medium, is strictly prohibited.

---

## 👤 Contact

**Ali Sadikin**
- Email: ali.sadikincom85@gmail.com
- GitHub: [@alisadikinma](https://github.com/alisadikinma)
- Location: Batam, Indonesia

---

## 🙏 Acknowledgments

- [Laravel](https://laravel.com) - The PHP framework for web artisans
- [Vue.js](https://vuejs.org) - The progressive JavaScript framework
- [Tailwind CSS](https://tailwindcss.com) - A utility-first CSS framework
- [Heroicons](https://heroicons.com) - Beautiful hand-crafted SVG icons

---

---

## 📈 Recent Updates

### Phase 6 - Production Ready Version (8/12 Sprints Complete) ✅

**Sprints Completed (67% Progress):**

1. ✅ **Sprint 1: Projects Management** (Oct 15, 2025)
   - Full CRUD with image upload, technologies array
   - Pagination, search, filters
   - SEO fields management

2. ✅ **Sprint 2: Awards Management** (Oct 15, 2025)
   - Award CRUD with gallery relationships
   - Award image upload
   - Gallery linking/unlinking UI

3. ✅ **Sprint 3: Gallery Management** (Oct 15, 2025)
   - Bulk image upload (up to 20 at once)
   - Bulk delete, search, filters
   - Image optimization and storage

4. ✅ **Sprint 4: Testimonials Management** (Oct 15, 2025)
   - Interactive 5-star rating selector
   - Client photo upload
   - Rich text editor for testimonials
   - Rating and status filters

5. ✅ **Sprint 5: Contact Messages** (Oct 15, 2025)
   - Read-only message management
   - View detail modal with reply button
   - Mark as read/unread
   - **Export to CSV** functionality
   - Unread count display

6. ✅ **Sprint 6: About Settings** (Oct 15, 2025)
   - Profile photo upload
   - **Dynamic Skills array**
   - **Dynamic Experience array** (7 fields each)
   - **Dynamic Education array** (6 fields each)
   - **Social Links array** (platform, url, icon)
   - JSON + FormData handling for complex arrays

7. ✅ **Sprint 7: Site Settings** (Oct 15, 2025)
   - **Site Information** (name, description, logo)
   - **Contact Information** (email, phone, address)
   - **Dynamic Social Media Links** (platform dropdown, urls)
   - **SEO Settings** (meta keywords, author, analytics ID)
   - Logo upload with preview and removal

8. ✅ **Sprint 8: Blog Management** (Oct 15, 2025)
   - **PostController** - Admin CRUD (indexForAdmin, showById, store, update, destroy)
   - **CategoryController** - Full CRUD
   - **PostsList.vue** - Search, category filter, status filter, pagination
   - Category color badges and slug auto-generation
   - Status badges (Published/Draft color coding)
   - Delete confirmation modals

**Admin Features Status:** 8/8 Complete (100%) ✅

**Pending Sprints (Next Priority):**
- 🔲 Sprint 9: Automation API (n8n Integration) - 90-120 min
- 🔲 Sprint 10: Home Hero Section - 30-45 min
- 🔲 Sprint 11: About Page - 45-60 min
- 🔲 Sprint 12: Contact Page - 45-60 min

**Project Statistics:**
- **Backend API:** 78% (6/9 controllers complete)
- **Frontend Admin:** 80% (8/10 pages complete)
- **Frontend Public:** 35% (5/9 pages with partial content)
- **Overall:** 67% (8/12 sprints complete)

**Key Metrics:**
- Database: ✅ 17 migrations, 13 seeders (100% complete)
- API Routes: ✅ 40+ endpoints configured
- Vue Components: ✅ 50+ components created
- Code Coverage: Manual testing 100%, Automated tests 0%

---

**Last Updated:** October 16, 2025
**Version:** 2.0.0 (Beta)
**Status:** In Development (67% Complete - See PROJECT_STATUS.md for details)

For detailed progress tracking, see [PROJECT_STATUS.md](./PROJECT_STATUS.md) and [Phase 6 Sprint Guide](./.claude/prompts/phase-6_production_ready_version_20251015-0938.md)

For questions or issues, please open an issue on GitHub or contact the maintainer.
