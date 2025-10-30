# Production Deployment Checklist

**Project:** Portfolio_v2
**Version:** 2.0
**Date:** October 25, 2025

---

## Pre-Deployment Checklist

### 1. Environment Configuration

#### Backend (.env)
```bash
- [ ] APP_NAME="Portfolio v2"
- [ ] APP_ENV=production
- [ ] APP_DEBUG=false ⚠️ CRITICAL
- [ ] APP_URL=https://yourdomain.com
- [ ] APP_KEY=<generated> # php artisan key:generate

- [ ] DB_CONNECTION=mysql
- [ ] DB_HOST=<production-host>
- [ ] DB_PORT=3306
- [ ] DB_DATABASE=<production-db>
- [ ] DB_USERNAME=<production-user>
- [ ] DB_PASSWORD=<strong-password>

- [ ] SANCTUM_STATEFUL_DOMAINS=yourdomain.com
- [ ] SESSION_DOMAIN=.yourdomain.com
- [ ] SANCTUM_GUARD=web

- [ ] MAIL_MAILER=smtp
- [ ] MAIL_HOST=<smtp-host>
- [ ] MAIL_PORT=587
- [ ] MAIL_USERNAME=<email>
- [ ] MAIL_PASSWORD=<password>
- [ ] MAIL_ENCRYPTION=tls
- [ ] MAIL_FROM_ADDRESS=noreply@yourdomain.com
- [ ] MAIL_FROM_NAME="${APP_NAME}"

- [ ] QUEUE_CONNECTION=database
- [ ] CACHE_DRIVER=redis
- [ ] SESSION_DRIVER=redis
- [ ] REDIS_HOST=127.0.0.1
- [ ] REDIS_PASSWORD=<redis-password>
- [ ] REDIS_PORT=6379

- [ ] FILESYSTEM_DISK=public
- [ ] AWS_ACCESS_KEY_ID=<if-using-s3>
- [ ] AWS_SECRET_ACCESS_KEY=<if-using-s3>
- [ ] AWS_DEFAULT_REGION=<region>
- [ ] AWS_BUCKET=<bucket-name>

- [ ] LOG_CHANNEL=daily
- [ ] LOG_LEVEL=error
```

#### Frontend (.env)
```bash
- [ ] VITE_API_BASE_URL=https://api.yourdomain.com
- [ ] VITE_APP_NAME="Portfolio v2"
- [ ] VITE_APP_ENV=production
```

---

### 2. Code Quality & Testing

```bash
- [ ] All tests passing: php artisan test
- [ ] Code coverage > 70%
- [ ] ESLint passed (frontend)
- [ ] No console.log() in production code
- [ ] No TODO/FIXME comments for critical issues
- [ ] All debug code removed
- [ ] Version control clean (no uncommitted changes)
```

---

### 3. Database

```bash
- [ ] Backup current production database (if updating)
- [ ] Run migrations on staging first
- [ ] Test rollback procedure
- [ ] Seed essential data (categories, settings)
- [ ] Database indexes optimized
- [ ] Foreign key constraints verified
- [ ] Check database encoding (UTF8MB4)
```

**Migration Command:**
```bash
php artisan migrate --force
php artisan db:seed --class=ProductionSeeder
```

---

### 4. Security

```bash
- [ ] APP_DEBUG=false ⚠️ CRITICAL
- [ ] All .env files in .gitignore
- [ ] Strong database passwords
- [ ] HTTPS certificate installed
- [ ] Force HTTPS in code (AppServiceProvider)
- [ ] CORS configured properly (not *)
- [ ] Rate limiting enabled
- [ ] File upload validation
- [ ] Sanctum token expiration set
- [ ] Security headers configured
- [ ] SQL injection protection verified
- [ ] XSS protection verified
- [ ] CSRF protection enabled
- [ ] Remove default/test users
- [ ] Change default admin password
```

**Security Headers (Add to middleware):**
```php
X-Frame-Options: SAMEORIGIN
X-Content-Type-Options: nosniff
X-XSS-Protection: 1; mode=block
Strict-Transport-Security: max-age=31536000; includeSubDomains
```

---

### 5. Performance Optimization

#### Backend
```bash
- [ ] Config cache: php artisan config:cache
- [ ] Route cache: php artisan route:cache
- [ ] View cache: php artisan view:cache
- [ ] Event cache: php artisan event:cache
- [ ] Composer optimize: composer install --optimize-autoloader --no-dev
- [ ] OPcache enabled
- [ ] Query optimization (N+1 fixes)
- [ ] Database indexing
- [ ] Redis caching configured
- [ ] Queue workers running
```

#### Frontend
```bash
- [ ] Production build: npm run build
- [ ] Assets minified
- [ ] Tree shaking enabled
- [ ] Code splitting implemented
- [ ] Images optimized (WebP, lazy loading)
- [ ] CDN for static assets
- [ ] Gzip/Brotli compression
- [ ] Browser caching headers
```

---

### 6. Server Configuration

#### Requirements
```bash
- [ ] PHP 8.1 or higher
- [ ] MySQL 8.0 or higher
- [ ] Node.js 18 or higher
- [ ] Composer 2.x
- [ ] Redis (optional but recommended)
- [ ] SSL certificate
```

#### PHP Extensions
```bash
- [ ] BCMath PHP Extension
- [ ] Ctype PHP Extension
- [ ] Fileinfo PHP Extension
- [ ] JSON PHP Extension
- [ ] Mbstring PHP Extension
- [ ] OpenSSL PHP Extension
- [ ] PDO PHP Extension
- [ ] Tokenizer PHP Extension
- [ ] XML PHP Extension
- [ ] GD or Imagick Extension
```

#### Apache/Nginx Configuration
```bash
- [ ] Document root points to /public
- [ ] .htaccess working (Apache) or nginx config
- [ ] PHP-FPM configured
- [ ] File upload limits: 20MB
- [ ] Execution time: 60s
- [ ] Memory limit: 256MB
```

**Nginx Example:**
```nginx
server {
    listen 80;
    server_name yourdomain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name yourdomain.com;
    root /var/www/portfolio/backend/public;

    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

---

### 7. File Permissions

```bash
- [ ] storage/ writable (775)
- [ ] bootstrap/cache/ writable (775)
- [ ] public/storage linked: php artisan storage:link
- [ ] .env not publicly accessible
- [ ] Logs writable
```

**Commands:**
```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
php artisan storage:link
```

---

### 8. Monitoring & Logging

```bash
- [ ] Error logging configured
- [ ] Log rotation enabled
- [ ] Monitoring tool installed (Sentry, Bugsnag, New Relic)
- [ ] Uptime monitoring (Pingdom, UptimeRobot)
- [ ] Performance monitoring
- [ ] Database query logging (temporarily for optimization)
- [ ] Failed job monitoring
- [ ] Email notifications for critical errors
```

**Recommended Services:**
- **Error Tracking:** Sentry
- **Uptime Monitoring:** UptimeRobot
- **Performance:** New Relic or Blackfire
- **Log Management:** Papertrail or Loggly

---

### 9. Backup Strategy

```bash
- [ ] Automated daily database backups
- [ ] Automated file storage backups
- [ ] Offsite backup storage
- [ ] Backup retention policy (30 days)
- [ ] Test restore procedure
- [ ] Document recovery process
```

**Backup Script Example:**
```bash
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
DB_NAME="portfolio_v2"
BACKUP_DIR="/backups/database"

mysqldump -u username -p'password' $DB_NAME > $BACKUP_DIR/backup_$DATE.sql
gzip $BACKUP_DIR/backup_$DATE.sql

# Upload to S3 or other cloud storage
aws s3 cp $BACKUP_DIR/backup_$DATE.sql.gz s3://your-bucket/backups/

# Delete backups older than 30 days
find $BACKUP_DIR -name "backup_*.sql.gz" -mtime +30 -delete
```

---

### 10. Queue & Scheduler

```bash
- [ ] Queue workers configured
- [ ] Supervisor installed for queue workers
- [ ] Cron job for scheduler
- [ ] Failed jobs table created
- [ ] Queue monitoring
```

**Supervisor Config (`/etc/supervisor/conf.d/portfolio-worker.conf`):**
```ini
[program:portfolio-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/portfolio/backend/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/portfolio/backend/storage/logs/worker.log
stopwaitsecs=3600
```

**Cron Job:**
```bash
* * * * * cd /var/www/portfolio/backend && php artisan schedule:run >> /dev/null 2>&1
```

---

### 11. API & Services

```bash
- [ ] CORS configured for production domain
- [ ] API rate limiting active
- [ ] Contact form rate limiting (5/min)
- [ ] Automation API rate limiting (60/min)
- [ ] Email service configured
- [ ] Webhook endpoints tested
- [ ] Third-party APIs credentials updated
```

---

### 12. Frontend Deployment

```bash
- [ ] Build production assets: npm run build
- [ ] Test production build: npm run preview
- [ ] Upload dist/ to hosting
- [ ] CDN configured (Cloudflare, etc.)
- [ ] Cache headers configured
- [ ] Robots.txt configured
- [ ] Sitemap.xml accessible
- [ ] Favicon included
- [ ] Open Graph meta tags
- [ ] Twitter Card meta tags
- [ ] Google Analytics configured
```

---

### 13. SEO & Analytics

```bash
- [ ] Meta titles and descriptions
- [ ] Structured data (JSON-LD)
- [ ] XML sitemap generated
- [ ] Robots.txt configured
- [ ] Google Analytics installed
- [ ] Google Search Console verified
- [ ] Social media meta tags (OG, Twitter)
- [ ] Canonical URLs configured
- [ ] 404 page created
- [ ] 500 error page created
```

---

### 14. Testing on Production

```bash
- [ ] Homepage loads correctly
- [ ] All public pages accessible
- [ ] Login/logout working
- [ ] Admin panel accessible
- [ ] CRUD operations working
- [ ] File uploads working
- [ ] Email sending working
- [ ] Contact form working
- [ ] Search functionality working
- [ ] API endpoints responding
- [ ] Mobile responsive
- [ ] Cross-browser testing (Chrome, Firefox, Safari)
- [ ] SSL certificate valid
- [ ] No console errors
- [ ] No mixed content warnings
- [ ] Performance acceptable (< 3s load time)
```

---

### 15. Documentation

```bash
- [ ] API documentation updated
- [ ] Deployment documentation
- [ ] Administrator guide
- [ ] Troubleshooting guide
- [ ] Changelog updated
- [ ] README updated
```

---

### 16. Post-Deployment

```bash
- [ ] Monitor error logs for 24 hours
- [ ] Check performance metrics
- [ ] Verify backups running
- [ ] Test critical user flows
- [ ] Update DNS records (if needed)
- [ ] Announce to stakeholders
- [ ] Create rollback plan
- [ ] Document any issues encountered
```

---

## Rollback Plan

In case deployment fails:

1. **Immediate Actions:**
   ```bash
   # Switch to maintenance mode
   php artisan down --retry=60

   # Restore database from backup
   mysql -u username -p database_name < backup_file.sql

   # Revert code to previous version
   git checkout <previous-tag>
   composer install --optimize-autoloader --no-dev
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache

   # Bring application back online
   php artisan up
   ```

2. **Verify Rollback:**
   - Test critical functionality
   - Check database integrity
   - Monitor error logs

3. **Post-Mortem:**
   - Document what went wrong
   - Update deployment checklist
   - Plan fix for next deployment

---

## Emergency Contacts

```
Developer: Ali Sadikin
Email: ali.sadikincom85@gmail.com
Phone: [Your Phone]

Hosting Provider: [Provider Name]
Support: [Support Contact]

Database Admin: [DBA Contact]
```

---

## Deployment Timeline

**Recommended Time:** Off-peak hours (2 AM - 6 AM)

**Estimated Duration:** 2-4 hours

**Stages:**
1. Pre-deployment checks (30 min)
2. Code deployment (15 min)
3. Database migration (15 min)
4. Cache clearing & optimization (10 min)
5. Testing (60 min)
6. Monitoring (60 min)

---

## Version Control

**Tag Release:**
```bash
git tag -a v2.0.0 -m "Production Release v2.0.0"
git push origin v2.0.0
```

**Create Release Branch:**
```bash
git checkout -b release/v2.0.0
git push origin release/v2.0.0
```

---

**Status:** ✅ READY FOR DEPLOYMENT

**Last Updated:** October 25, 2025
**Prepared By:** Ali Sadikin
**Approved By:** [Approver Name]
