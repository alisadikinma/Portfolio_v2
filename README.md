# Portfolio Website v2

Modern full-stack portfolio website built with Laravel and Vue.js, featuring a responsive design, content management system, and optimized performance.

## 🌟 Features

- **Responsive Design**: Mobile-first approach with seamless experience across all devices
- **Modern UI/UX**: Clean, professional interface with smooth animations
- **Project Showcase**: Dynamic project gallery with filtering and detailed case studies
- **Blog System**: Full-featured blog with categories, tags, and rich content
- **Awards & Recognition**: Showcase achievements with featured awards section
- **Client Testimonials**: Auto-rotating carousel with star ratings and client feedback
- **Dynamic Settings**: Flexible settings system for site-wide configuration
- **Contact Form**: Integrated contact system with email notifications
- **Admin Panel**: FilamentPHP admin dashboard for content management
- **API-First**: RESTful API with Laravel Sanctum authentication
- **SEO Optimized**: Proper meta tags, semantic HTML, and sitemap generation
- **Performance**: Optimized images, lazy loading, and efficient caching
- **Dark Mode**: Full dark mode support across all sections

## 🚀 Tech Stack

### Backend
- **Laravel 10.x** - PHP framework
- **FilamentPHP 4.x** - Modern admin panel framework
- **Laravel Sanctum** - API authentication
- **Livewire 3.x** - Dynamic components
- **MySQL** - Database (via XAMPP)

### Frontend
- **Vue 3** - Progressive JavaScript framework
- **Vite** - Next generation frontend tooling
- **Vue Router** - Official router for Vue.js
- **Pinia** - Vue store (state management)
- **Tailwind CSS 4** - Utility-first CSS framework
- **Headless UI** - Unstyled accessible UI components
- **Axios** - HTTP client for API calls

### Development Tools
- **XAMPP** - Local development environment
- **Composer** - PHP dependency manager
- **NPM** - Node package manager
- **Playwright** - End-to-end testing

## 📋 Prerequisites

Ensure you have the following installed:

- **XAMPP** (includes Apache & MySQL)
  - Apache running on port 80
  - MySQL running on port 3306
- **PHP** 8.1 or higher
- **Composer** (PHP dependency manager)
- **Node.js** v18 or higher
- **NPM** or **Yarn** or **PNPM**
- **Git**

## 🛠️ Installation

### 1. Clone the Repository

```bash
cd C:\xampp\htdocs
git clone [your-repo-url] Portfolio_v2
cd Portfolio_v2
```

### 2. Backend Setup (Laravel)

```bash
cd backend

# Install PHP dependencies
composer install

# Copy environment file
copy .env.example .env

# Generate application key
php artisan key:generate

# Configure database in .env (see below)

# Run migrations
php artisan migrate

# Seed database (optional)
php artisan db:seed

# Link storage
php artisan storage:link
```

#### Configure Database (.env)

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=portfolio_v2
DB_USERNAME=root
DB_PASSWORD=
```

### 3. Frontend Setup (Vue.js)

```bash
cd frontend

# Install Node dependencies
npm install

# Copy environment file
copy .env.example .env

# Configure API endpoint in .env
VITE_API_URL=http://localhost/Portfolio_v2/backend/public/api
```

### 4. Create MySQL Database

Open phpMyAdmin (http://localhost/phpmyadmin) and create database:
```sql
CREATE DATABASE portfolio_v2 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## 🚦 Running the Application

### Development Mode

#### Backend (Laravel - via XAMPP Apache)

1. Ensure XAMPP Apache is running
2. Access Laravel backend at:
   - **Backend API**: http://localhost/Portfolio_v2/backend/public
   - **Admin Dashboard**: http://localhost/Portfolio_v2/backend/public/admin

#### Frontend (Vue.js - via Vite Dev Server)

```bash
cd frontend
npm run dev
```

Frontend will be available at:
- **Frontend Dev Server**: http://localhost:5173

### Production Build

#### Build Frontend

```bash
cd frontend
npm run build
```

The `dist` folder will be created with optimized production files.

#### Deploy to Production

1. Copy `frontend/dist/*` to your web server
2. Configure Apache `.htaccess` for Vue Router
3. Set production environment variables
4. Run Laravel optimization:

```bash
cd backend
php artisan config:cache
php artisan route:cache
php artisan view:cache
composer install --optimize-autoloader --no-dev
```

## 📁 Project Structure

```
Portfolio_v2/
├── backend/                    # Laravel backend
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/   # API controllers
│   │   │   └── Resources/     # API resources
│   │   ├── Models/            # Eloquent models
│   │   └── Helpers/           # Helper functions
│   ├── config/                # Configuration files
│   ├── database/
│   │   ├── migrations/        # Database migrations
│   │   └── seeders/           # Database seeders
│   ├── public/                # Public assets & entry point
│   ├── resources/
│   │   ├── views/             # Blade templates
│   │   └── js/                # Livewire components
│   ├── routes/
│   │   ├── api.php            # API routes
│   │   └── web.php            # Web routes
│   ├── storage/               # Uploaded files & logs
│   ├── .env                   # Environment config (not in git)
│   ├── composer.json          # PHP dependencies
│   └── README.md
│
├── frontend/                   # Vue.js frontend
│   ├── src/
│   │   ├── assets/            # Images, fonts
│   │   ├── components/        # Vue components
│   │   ├── composables/       # Vue composables
│   │   ├── router/            # Vue Router config
│   │   ├── stores/            # Pinia stores
│   │   ├── views/             # Page components
│   │   ├── App.vue            # Root component
│   │   └── main.js            # Entry point
│   ├── public/                # Static assets
│   ├── .env                   # Environment config (not in git)
│   ├── package.json           # Node dependencies
│   ├── tailwind.config.js     # Tailwind configuration
│   ├── vite.config.js         # Vite configuration
│   └── README.md
│
├── .gitignore                 # Git ignore rules
├── portfolio_v2.sql           # Database backup
└── README.md                  # This file
```

## 🔧 Configuration

### Environment Variables

All sensitive configuration is managed through `.env` files:

**Backend `.env`** - Database, app settings, API keys
**Frontend `.env`** - API endpoint, feature flags

**⚠️ SECURITY WARNING:**
- Never commit `.env` files to version control
- Never expose database credentials in code
- Use environment variables for all secrets
- Rotate secrets regularly in production

## 🧪 Testing

```bash
# Backend tests (Laravel)
cd backend
php artisan test
# or
./vendor/bin/pest

# Frontend tests (Vue + Playwright)
cd frontend
npm test

# E2E tests
npm run test:e2e
```

## 📚 API Documentation

API endpoints are defined in `backend/routes/api.php`:

### Authentication
```
POST   /api/login              # User login
POST   /api/logout             # User logout
POST   /api/register           # User registration
GET    /api/user               # Get current user
```

### Projects
```
GET    /api/projects           # Get all projects
GET    /api/projects/{id}      # Get single project
POST   /api/projects           # Create project (auth required)
PUT    /api/projects/{id}      # Update project (auth required)
DELETE /api/projects/{id}      # Delete project (auth required)
```

### Awards
```
GET    /api/awards             # Get all awards
GET    /api/awards/{id}        # Get single award
GET    /api/awards/{id}/galleries  # Get award galleries
```

### Testimonials
```
GET    /api/testimonials       # Get all testimonials
GET    /api/testimonials/{id}  # Get single testimonial
```

### Settings
```
GET    /api/settings           # Get all settings
GET    /api/settings/{group}   # Get settings by group (profile, general, about, hero)
```

For complete API documentation, see `backend/README.md` or `API_TESTING_GUIDE.md`

## 📖 Documentation

- **[Backend Documentation](./backend/README.md)** - Laravel API guide
- **[Frontend Documentation](./frontend/README.md)** - Vue.js development guide
- **[Implementation Summary](./IMPLEMENTATION_SUMMARY.md)** - Recent features and changes
- **[Quick Start Guide](./QUICK_START.md)** - Get up and running quickly
- **[API Testing Guide](./API_TESTING_GUIDE.md)** - Test API endpoints
- **[Verification Checklist](./frontend/VERIFICATION_CHECKLIST.md)** - QA testing checklist

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/amazing-feature`
3. Commit changes: `git commit -m 'feat: add amazing feature'`
4. Push to branch: `git push origin feature/amazing-feature`
5. Open a Pull Request

Please follow [Conventional Commits](https://www.conventionalcommits.org/) for commit messages.

## 📝 License

This project is licensed under the MIT License - see [LICENSE](LICENSE) file for details.

## 👤 Author

**Ali Sadikin**
- Email: ali.sadikincom85@gmail.com
- Location: Batam, Indonesia
- GitHub: [@alisadikin](https://github.com/alisadikin)

## 🙏 Acknowledgments

- Laravel Framework by Taylor Otwell
- Vue.js by Evan You
- Tailwind CSS by Adam Wathan
- Design inspiration from modern portfolio websites

## 📞 Support

For issues or questions:

1. Check the [documentation](./docs)
2. Review [existing issues](https://github.com/yourusername/Portfolio_v2/issues)
3. Create a [new issue](https://github.com/yourusername/Portfolio_v2/issues/new)

---

**Development Status**: Active Development  
**Last Updated**: October 2025

⭐ If this project helped you, please consider giving it a star!
