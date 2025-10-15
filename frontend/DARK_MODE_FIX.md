# Dark Mode Toggle Fix - October 15, 2025

## Problem

Dark mode toggle switch tidak berfungsi di aplikasi Portfolio v2.

## Root Cause

Project menggunakan **Tailwind CSS v4** yang memiliki sintaks berbeda dari v3:
- File `tailwind.config.js` tidak lagi digunakan di v4
- Dark mode perlu dikonfigurasi di dalam CSS menggunakan `@theme` directive
- Syntax `darkMode: 'class'` di config file diabaikan oleh v4

## Solution

### 1. Update `style.css` (CRITICAL)

Menambahkan konfigurasi dark mode di dalam `@theme` directive:

```css
@theme {
  /* Dark Mode Configuration - CRITICAL FIX */
  --dark-mode: class;
  
  /* ... rest of theme config */
}
```

Menambahkan color-scheme untuk HTML element:

```css
@layer base {
  /* Ensure dark mode class works on HTML element */
  html.dark {
    color-scheme: dark;
  }
}
```

### 2. Perbaiki Theme Store (`stores/theme.js`)

**Changes:**
- Menghapus class sebelumnya sebelum menambahkan class baru
- Menambahkan attribute `data-theme` untuk debugging
- Menambahkan class `light` saat light mode (bukan hanya menghapus `dark`)
- Menambahkan lebih banyak console logs untuk debugging
- Export `applyTheme()` untuk manual use

**Key improvements:**
```javascript
const applyTheme = () => {
  // Remove any existing class first
  document.documentElement.classList.remove('dark', 'light')
  
  // Add the appropriate class
  if (isDark.value) {
    document.documentElement.classList.add('dark')
    document.documentElement.setAttribute('data-theme', 'dark')
  } else {
    document.documentElement.classList.add('light')
    document.documentElement.setAttribute('data-theme', 'light')
  }
}
```

### 3. Update App.vue

**Changes:**
- Menginisialisasi theme di `onBeforeMount` (sebelum component render) untuk mencegah flash
- Memanggil `applyTheme()` lagi setelah `onMounted` untuk memastikan
- Menambahkan console logs untuk debugging

```javascript
onBeforeMount(() => {
  themeStore.initTheme() // Early init prevents flash
})

onMounted(() => {
  themeStore.listenToSystemTheme()
  themeStore.applyTheme() // Double-check
})
```

## Testing

### 1. Start Development Server

```bash
cd C:\xampp\htdocs\Portfolio_v2\frontend
npm run dev
```

### 2. Open Browser

Navigate to: http://localhost:5173

### 3. Open DevTools Console

Press F12 and check Console tab

### 4. Test Toggle

Click the sun/moon icon in navigation:

**Expected console output:**
```
🔄 Toggle dark mode clicked!
Before toggle - isDark: false
After toggle - isDark: true
Color scheme: dark
🎨 Applying theme: dark
✅ Dark mode enabled
📋 Current HTML classes: dark
```

### 5. Verify HTML Element

In DevTools Elements tab, check `<html>` element:

**Light mode:**
```html
<html class="light" data-theme="light">
```

**Dark mode:**
```html
<html class="dark" data-theme="dark">
```

### 6. Visual Verification

- **Background:** Should change from white to dark gray
- **Text:** Should change from dark to light
- **Navigation:** Should change colors appropriately
- **All components:** Should respect dark mode classes

## Debugging

If toggle still doesn't work:

### 1. Check Console Logs

Look for any errors or unexpected behavior in console logs

### 2. Inspect HTML Element

```javascript
// In browser console
document.documentElement.className
document.documentElement.getAttribute('data-theme')
```

### 3. Check LocalStorage

```javascript
// In browser console
localStorage.getItem('theme')
```

### 4. Manual Toggle Test

```javascript
// In browser console
document.documentElement.classList.add('dark')
// Should immediately change to dark mode
```

### 5. Force Theme

```javascript
// In browser console (with Vue DevTools)
const themeStore = useThemeStore()
themeStore.setTheme('dark')
```

## Important Notes

### Tailwind CSS v4 Changes

**DO NOT use `tailwind.config.js` for dark mode config** - It's ignored!

**DO use `@theme` in CSS:**
```css
@theme {
  --dark-mode: class;
}
```

### PostCSS Configuration

Ensure `postcss.config.js` uses correct plugin:

```javascript
export default {
  plugins: {
    '@tailwindcss/postcss': {},  // v4 plugin
    autoprefixer: {},
  },
}
```

### Package Versions

```json
{
  "tailwindcss": "^4.1.14",
  "@tailwindcss/postcss": "^4.1.14"
}
```

## Files Modified

1. ✅ `frontend/src/style.css` - Added dark mode config
2. ✅ `frontend/src/stores/theme.js` - Improved toggle logic
3. ✅ `frontend/src/App.vue` - Better initialization

## Files NOT Modified (Intentional)

- `tailwind.config.js` - Not used in v4, kept for reference only
- `TheNavigation.vue` - Already correct
- `postcss.config.js` - Already correct

## Verification Checklist

- [ ] Dark mode toggle button exists in navigation
- [ ] Console logs appear when clicking toggle
- [ ] HTML element class changes from `light` to `dark`
- [ ] Visual changes occur (background, text, components)
- [ ] Theme persists after page reload
- [ ] System theme detection works (if set to "system")
- [ ] Mobile menu toggle also works

## Common Issues

### Issue: Toggle button clicks but nothing happens

**Solution:** Check console for JavaScript errors

### Issue: Class is added but styles don't change

**Solution:** Ensure all components use `dark:` prefix properly

### Issue: Flash of wrong theme on page load

**Solution:** Theme init moved to `onBeforeMount` (already fixed)

### Issue: Theme doesn't persist after reload

**Solution:** Check localStorage is working and not blocked

---

**Fixed by:** Claude (October 15, 2025)
**Tested:** ⏳ Pending user verification
**Status:** Ready for testing
