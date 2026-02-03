# Vite Setup Guide

## What Was Fixed

### 1. **Tailwind CSS CDN Warning**
- **Before:** Using `cdn.tailwindcss.com` (not production-ready)
- **After:** Using compiled Tailwind CSS via Vite
- **Result:** ✅ No more CDN warning, production-ready CSS

### 2. **WebSocket Connection Error**
- **Before:** Vite HMR trying to connect to `ws://localhost:8081/`
- **After:** Vite configured for Docker/Sail environment
- **Result:** ✅ No more WebSocket errors (when running dev server)

## Files Modified

### 1. `vite.config.js`
- Added Docker/Sail compatibility:
  - `host: '0.0.0.0'` - Listen on all interfaces
  - `port: 5173` - Standard Vite port
  - `hmr: { host: 'localhost' }` - HMR for Docker
  - `usePolling: true` - File watching in Docker

### 2. `resources/css/app.css`
- Added custom theme variables:
  - Primary color, background colors
  - Alert colors (red, amber, green)
  - Custom fonts (Cairo, Inter)

### 3. `resources/views/layouts/app.blade.php`
- Removed Tailwind CDN script
- Removed manual `tailwind.config` script
- Added `@vite(['resources/css/app.css', 'resources/js/app.js'])`

## Running Vite

### Production Build (Current)
```bash
./vendor/bin/sail npm run build
```
- Compiles assets to `public/build/`
- Optimized and minified
- Ready for production
- **Currently active** ✅

### Development Mode (Hot Reload)
If you want live CSS/JS reloading while developing:

1. **Start Vite dev server:**
   ```bash
   ./vendor/bin/sail npm run dev
   ```

2. **Keep it running** in a separate terminal

3. **Benefits:**
   - Instant CSS/JS updates without page refresh
   - Better development experience

4. **Note:** The WebSocket error is fixed, but you need to expose port 5173 in Sail:
   ```bash
   # Add to docker-compose.yml ports section if needed:
   # - "${VITE_PORT:-5173}:5173"
   ```

### When to Use Each

| Mode | Use Case | Command |
|------|----------|---------|
| **Production Build** | Deployment, testing final version | `npm run build` |
| **Development** | Active development with hot reload | `npm run dev` |

## Verifying the Fix

### Before (Console Warnings):
```
⚠️ cdn.tailwindcss.com should not be used in production
❌ WebSocket connection to 'ws://localhost:8081/' failed
```

### After (No Warnings):
```
✅ No Tailwind CDN warning
✅ No WebSocket errors (compiled assets loaded from public/build/)
```

## Production Deployment Checklist

- [x] Remove Tailwind CDN
- [x] Configure Vite for production
- [x] Build assets: `npm run build`
- [x] Commit `public/build/` manifest to Git
- [ ] Set `APP_ENV=production` in `.env`
- [ ] Run `php artisan config:cache`
- [ ] Run `php artisan route:cache`
- [ ] Run `php artisan view:cache`

## Troubleshooting

### "Vite manifest not found"
```bash
./vendor/bin/sail npm run build
```

### CSS not updating
```bash
./vendor/bin/sail artisan view:clear
./vendor/bin/sail npm run build
```

### Want hot reload but getting WebSocket errors
1. Make sure Vite dev server is running: `./vendor/bin/sail npm run dev`
2. Check if port 5173 is exposed in Docker
3. Access app via `http://localhost` (not `127.0.0.1`)

---

**Status:** ✅ Production build complete and working
**Last Build:** Check `public/build/manifest.json` for latest build timestamp
