# CLAUDE.md - Portfolio_v2

This file provides guidance to Claude Code when working with the Portfolio_v2 project.

## Project Overview

Portfolio_v2 is a modern full-stack application built with **Laravel 10** (backend) and **Vue 3** (frontend). This is a Windows 11 development environment using XAMPP for local server.

**IMPORTANT**: Always read `README.md` in root, `backend/README.md`, and `frontend/README.md` at the start of EVERY new conversation for complete context.

---

## 🚨 CRITICAL RULES

### BEFORE Making ANY Changes:
1. **READ** all three README.md files (root, backend, frontend)
2. **UNDERSTAND** the existing code patterns by checking sibling files
3. **VERIFY** your changes won't break existing conventions

### AFTER Making ANY Changes:
1. **UPDATE** relevant README.md files with new features/changes
2. **UPDATE** this CLAUDE.md with architectural decisions/patterns
3. **DOCUMENT** breaking changes or new dependencies
4. **RUN TESTS** to ensure nothing broke

---

## Environment Setup

### Development Environment
- **OS**: Windows 11
- **Web Server**: XAMPP (Apache on port 80, MySQL on port 3306)
- **PHP**: 8.2
- **Node.js**: v18+
- **Database**: MySQL (via phpMyAdmin)

### File Paths (Windows)
```
Root: C:\xampp\htdocs\Portfolio_v2
Backend: C:\xampp\htdocs\Portfolio_v2\backend
Frontend: C:\xampp\htdocs\Portfolio_v2\frontend
```

**IMPORTANT**: Use Windows-style paths with backslashes when working with files.

---

## Commands & Workflows

### Laravel (Backend)

**CRITICAL**: DO NOT use `php artisan serve` - XAMPP Apache already runs on port 80!

```bash
# Navigate to backend
cd C:\xampp\htdocs\Portfolio_v2\backend

# Composer commands
composer install
composer update
composer dump-autoload

# Artisan commands
php artisan migrate
php artisan migrate:fresh --seed
php artisan db:seed
php artisan make:model ModelName -mcr
php artisan make:controller ControllerName
php artisan make:request RequestName
php artisan make:resource ResourceName
php artisan route:list
php artisan tinker

# Testing
php artisan test
./vendor/bin/pest

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Storage link
php artisan storage:link
```

### Vue (Frontend)

```bash
# Navigate to frontend
cd C:\xampp\htdocs\Portfolio_v2\frontend

# Install dependencies
npm install

# Development server (runs on port 5173)
npm run dev

# Production build
npm run build

# Testing
npm test
npm run test:e2e

# Linting
npm run lint
npm run lint:fix
```

### Access URLs

```
Backend API: http://localhost/Portfolio_v2/backend/public/api
Admin Dashboard: http://localhost/Portfolio_v2/backend/public/admin
Frontend Dev: http://localhost:5173
phpMyAdmin: http://localhost/phpmyadmin
```

---

## Code Style & Conventions

### Laravel Backend

#### File Naming
- **Controllers**: Singular PascalCase - `UserController.php`
- **Models**: Singular PascalCase - `User.php`
- **Migrations**: snake_case with timestamp - `2024_01_01_000000_create_users_table.php`
- **Requests**: PascalCase + Request - `StoreUserRequest.php`
- **Resources**: PascalCase + Resource - `UserResource.php`

#### Route Naming
- Use **plural kebab-case** for resource routes: `/api/blog-posts`
- Group related routes with `Route::prefix()` and `Route::group()`

#### Controllers
- Use **Resource Controllers** for CRUD operations
- Return `JsonResponse` with appropriate HTTP status codes
- Use **Form Request** classes for validation
- Use **API Resources** for response transformation

**Example:**
```php
public function index()
{
    $users = User::paginate(15);
    return UserResource::collection($users);
}

public function store(StoreUserRequest $request)
{
    $user = User::create($request->validated());
    return new UserResource($user);
}
```

#### Models
- Use `$fillable` or `$guarded` for mass assignment
- Define `$casts` for attribute casting
- Use **Eloquent relationships** (hasMany, belongsTo, etc.)
- Use **Accessors & Mutators** for attribute manipulation

**Example:**
```php
class User extends Model
{
    protected $fillable = ['name', 'email', 'password'];
    
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
    ];
    
    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}
```

#### Database
- Use **migrations** for all schema changes
- Use **seeders** for sample data
- Use **factories** for testing data
- Always add indexes for foreign keys and frequently queried columns

### Vue Frontend

#### File Naming
- **Components**: PascalCase - `UserCard.vue`, `BlogPost.vue`
- **Views/Pages**: PascalCase - `HomePage.vue`, `AboutPage.vue`
- **Composables**: camelCase with `use` prefix - `useAuth.js`, `useApi.js`
- **Stores**: camelCase - `userStore.js`, `blogStore.js`

#### Component Structure
```vue
<script setup>
// Imports first
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'

// Props & Emits
const props = defineProps({
  title: String,
  data: Object
})

const emit = defineEmits(['update', 'delete'])

// Composables
const router = useRouter()

// Reactive state
const loading = ref(false)

// Computed properties
const formattedTitle = computed(() => props.title.toUpperCase())

// Methods
const handleSubmit = () => {
  emit('update', data)
}

// Lifecycle
onMounted(() => {
  // Initialization
})
</script>

<template>
  <!-- Template here -->
</template>

<style scoped>
/* Prefer Tailwind classes, minimal custom CSS */
</style>
```

#### Pinia Stores
- Use **setup syntax** for stores
- Define clear `state`, `getters`, `actions`
- Use `$reset()` for state cleanup

**Example:**
```javascript
export const useUserStore = defineStore('user', () => {
  const user = ref(null)
  const token = ref(localStorage.getItem('token'))
  
  const isAuthenticated = computed(() => !!token.value)
  
  async function login(credentials) {
    const response = await api.post('/login', credentials)
    token.value = response.data.token
    user.value = response.data.user
  }
  
  function logout() {
    user.value = null
    token.value = null
    localStorage.removeItem('token')
  }
  
  return { user, token, isAuthenticated, login, logout }
})
```

#### Tailwind CSS
- Use **utility-first** approach
- Prefer Tailwind classes over custom CSS
- Use `@apply` sparingly for repeated patterns
- Mobile-first responsive design: `sm:`, `md:`, `lg:`, `xl:`

---

## Testing Requirements

### Test-Driven Development (TDD)

**YOU MUST** follow TDD workflow for ALL features:

1. **Write tests FIRST** before implementation
2. **Run tests** to confirm they fail
3. **Implement feature** to make tests pass
4. **Refactor** while keeping tests green

### Backend Testing (Laravel)

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test --filter=UserControllerTest

# Run with coverage
php artisan test --coverage
```

**Test Structure:**
```php
// tests/Feature/UserControllerTest.php
public function test_user_can_be_created()
{
    $data = [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123'
    ];
    
    $response = $this->postJson('/api/users', $data);
    
    $response->assertStatus(201)
             ->assertJson(['name' => 'John Doe']);
             
    $this->assertDatabaseHas('users', [
        'email' => 'john@example.com'
    ]);
}
```

### Frontend Testing (Vue + Playwright)

```bash
# Unit tests
npm test

# E2E tests
npm run test:e2e
```

---

## QA Verification Protocol

**MANDATORY**: Every development task MUST conclude with QA verification.

### QA Checklist:
- [ ] All CRUD operations tested in browser
- [ ] Form validation works correctly
- [ ] Error handling displays properly
- [ ] Responsive design on mobile/tablet/desktop
- [ ] API responses are correct
- [ ] Database changes verified
- [ ] No console errors

### QA Testing with Playwright

Use Playwright MCP browser testing for automated QA:

```javascript
// Example Playwright test
test('user can create a post', async ({ page }) => {
  await page.goto('http://localhost:5173/posts/create')
  
  await page.fill('[name="title"]', 'Test Post')
  await page.fill('[name="content"]', 'Test content')
  await page.click('button[type="submit"]')
  
  await expect(page.locator('.success-message')).toBeVisible()
})
```

**Screenshot evidence required** for complex features.

---

## Git Workflow

### Commit Messages

Use **Conventional Commits**:

```
feat: add user authentication
fix: resolve login validation bug
docs: update API documentation
style: format code with prettier
refactor: simplify user controller
test: add user creation tests
chore: update dependencies
```

### Branch Strategy

```
main          → production-ready code
develop       → development branch
feature/*     → new features
bugfix/*      → bug fixes
hotfix/*      → urgent production fixes
```

---

## Security & Best Practices

### Security Checklist
- [ ] Use **prepared statements** (Eloquent does this automatically)
- [ ] Validate ALL user inputs with **Form Requests**
- [ ] Sanitize output to prevent XSS
- [ ] Use **CSRF protection** (Laravel default)
- [ ] Use **Laravel Sanctum** for API authentication
- [ ] Never commit `.env` files
- [ ] Use **bcrypt** for password hashing (Laravel default)

### Performance Optimization
- [ ] Use **eager loading** to avoid N+1 queries
- [ ] Add **database indexes** for foreign keys
- [ ] Use **pagination** for large datasets
- [ ] Implement **caching** for frequently accessed data
- [ ] Optimize images (lazy loading, compression)
- [ ] Use **CDN** for static assets in production

---

## Common Patterns & Examples

### API Response Format

**Success:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "John Doe"
  },
  "message": "User created successfully"
}
```

**Error:**
```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid.",
    "details": {
      "email": ["The email field is required."]
    }
  }
}
```

### Eager Loading (Avoid N+1)

**❌ Bad (N+1 Query):**
```php
$posts = Post::all(); // 1 query
foreach ($posts as $post) {
    echo $post->author->name; // N queries
}
```

**✅ Good:**
```php
$posts = Post::with('author')->get(); // 2 queries total
foreach ($posts as $post) {
    echo $post->author->name;
}
```

### Vue Composables Pattern

```javascript
// composables/useApi.js
export function useApi() {
  const loading = ref(false)
  const error = ref(null)
  
  const get = async (url) => {
    loading.value = true
    error.value = null
    try {
      const response = await axios.get(url)
      return response.data
    } catch (err) {
      error.value = err.message
      throw err
    } finally {
      loading.value = false
    }
  }
  
  return { loading, error, get }
}
```

---

## Troubleshooting

### Common Issues

**"Class not found" error:**
```bash
composer dump-autoload
```

**Migration fails:**
```bash
php artisan migrate:fresh --seed
```

**Frontend not updating:**
```bash
# Clear Vite cache
npm run dev -- --force
```

**CORS errors:**
Check `backend/config/cors.php` and ensure frontend URL is allowed.

---

## Documentation Standards

### When to Update Documentation

**ALWAYS update documentation when:**
- Adding new API endpoints
- Changing existing API behavior
- Adding new components or features
- Modifying environment variables
- Changing deployment process
- Adding new dependencies

### Documentation Files
- `README.md` → General project overview
- `backend/README.md` → Laravel API documentation
- `frontend/README.md` → Vue component documentation
- `CLAUDE.md` → This file (for Claude Code context)
- `API.md` → Detailed API endpoint documentation

---

## Important Notes

### What NOT to Do

❌ **NEVER** use `php artisan serve` (XAMPP Apache already running!)
❌ **NEVER** commit `.env` files to version control
❌ **NEVER** skip writing tests for new features
❌ **NEVER** use raw SQL queries (use Eloquent)
❌ **NEVER** store passwords in plain text
❌ **NEVER** trust user input without validation
❌ **NEVER** make breaking changes without updating docs

### What to ALWAYS Do

✅ **ALWAYS** read README.md files before starting
✅ **ALWAYS** write tests BEFORE implementation (TDD)
✅ **ALWAYS** run tests after changes
✅ **ALWAYS** use Form Requests for validation
✅ **ALWAYS** use API Resources for responses
✅ **ALWAYS** eager load relationships to avoid N+1
✅ **ALWAYS** update documentation after changes
✅ **ALWAYS** follow existing code conventions
✅ **ALWAYS** commit with meaningful messages

---

## Quick Reference

### Laravel Artisan Shortcuts
```bash
# Make everything for a model at once
php artisan make:model Post -mcr
# -m: migration, -c: controller, -r: resource controller

# Make form request
php artisan make:request StorePostRequest

# Make API resource
php artisan make:resource PostResource
```

### Vue Component Quick Start
```bash
# Component in components/
touch src/components/BlogCard.vue

# Page in views/
touch src/views/BlogPage.vue

# Composable in composables/
touch src/composables/useBlog.js

# Store in stores/
touch src/stores/blogStore.js
```

---

## Additional Resources

- Laravel Documentation: https://laravel.com/docs/10.x
- Vue 3 Documentation: https://vuejs.org/guide/
- Tailwind CSS: https://tailwindcss.com/docs
- Pinia: https://pinia.vuejs.org/
- Laravel Sanctum: https://laravel.com/docs/10.x/sanctum

---

**Last Updated**: October 11, 2025  
**Project Owner**: Ali Sadikin (ali.sadikincom85@gmail.com)

---

## For Claude Code

When working on this project:
1. Start by reading all README files
2. Check sibling files for patterns before creating new code
3. Follow TDD: write tests first, then implement
4. Use the command reference above
5. Update documentation after every feature
6. Ask for clarification if requirements are unclear
7. Verify all changes work before considering task complete
8. Run QA verification with Playwright when applicable

**REMEMBER**: You are working with an experienced developer (16+ years). Focus on implementation quality, not explaining basics. Ali knows the architecture - just execute the tasks efficiently and follow the established patterns.
