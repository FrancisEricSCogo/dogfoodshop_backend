# Deployment Guide for Hostinger

## Pre-Deployment Checklist ✅

- [x] CORS configured for localhost and GitHub Pages
- [x] Frontend API URLs updated to Laravel API
- [x] All endpoints migrated and tested
- [ ] Production .env configured
- [ ] Database created on Hostinger
- [ ] Files uploaded to Hostinger
- [ ] Migrations run on Hostinger
- [ ] File permissions set
- [ ] Test production API

## Step-by-Step Deployment

### 1. Prepare Production Environment

1. **Create `.env` file on Hostinger:**
   - Copy `.env.production.example` to `.env`
   - Fill in your Hostinger database credentials
   - Update `APP_URL` to your Hostinger domain
   - Generate app key: `php artisan key:generate`

2. **Update CORS Configuration:**
   - Edit `config/cors.php`
   - Add your GitHub Pages URL to `allowed_origins`:
   ```php
   'allowed_origins' => [
       'https://yourusername.github.io',
       // Add other domains as needed
   ],
   ```

### 2. Upload Files to Hostinger

1. **Upload via FTP/SFTP or File Manager:**
   - Upload entire `backend/api` folder to your Hostinger hosting
   - Recommended path: `public_html/api` or `public_html/backend/api`

2. **Important Files to Upload:**
   - All files in `backend/api/` directory
   - Make sure `.env` is uploaded (but keep it secure!)

3. **Files to Exclude (if using Git):**
   - `node_modules/`
   - `vendor/` (or run `composer install` on server)
   - `.git/`

### 3. Set Up Database on Hostinger

1. **Create Database:**
   - Log into Hostinger control panel
   - Go to Databases → MySQL Databases
   - Create new database
   - Create database user
   - Grant all privileges to user

2. **Import Database:**
   - Option A: Import your `dogfoodshop.sql` file via phpMyAdmin
   - Option B: Run migrations: `php artisan migrate`

### 4. Configure Server

1. **Set Document Root:**
   - Point to `backend/api/public` directory
   - Or configure virtual host to point to `public` folder

2. **Set File Permissions:**
   ```bash
   chmod -R 755 storage
   chmod -R 755 bootstrap/cache
   ```

3. **Install Dependencies:**
   ```bash
   composer install --no-dev --optimize-autoloader
   ```

### 5. Run Migrations

```bash
php artisan migrate
# Or if tables already exist:
php artisan migrate --pretend
```

### 6. Test Production API

1. **Test Endpoint:**
   ```
   https://yourdomain.com/api/products/get_products.php
   ```

2. **Check CORS:**
   - Open browser console on your GitHub Pages site
   - Try making an API call
   - Check for CORS errors

### 7. Update Frontend for Production

1. **Update API_BASE in frontend:**
   - Change from `http://localhost:8000/api`
   - To: `https://yourdomain.com/api`

2. **Update CORS in Laravel:**
   - Add your GitHub Pages URL to `config/cors.php`

3. **Deploy Frontend to GitHub Pages**

## Troubleshooting

### 500 Internal Server Error
- Check Laravel logs: `storage/logs/laravel.log`
- Verify `.env` file exists and is configured
- Check file permissions

### CORS Errors
- Verify `config/cors.php` has your GitHub Pages URL
- Check `allowed_origins` array
- Ensure `supports_credentials` is set correctly

### Database Connection Errors
- Verify database credentials in `.env`
- Check database exists on Hostinger
- Verify user has proper permissions

### File Upload Errors
- Check `storage/app/public` permissions
- Verify storage link: `php artisan storage:link`
- Check upload_max_filesize in PHP settings

## Security Checklist

- [ ] `APP_DEBUG=false` in production
- [ ] `.env` file is not publicly accessible
- [ ] Strong database passwords
- [ ] CORS only allows your frontend domain
- [ ] File permissions set correctly
- [ ] HTTPS enabled (SSL certificate)

## Post-Deployment

1. Test all endpoints
2. Test login/registration
3. Test file uploads
4. Monitor error logs
5. Set up backups

## Support

If you encounter issues:
1. Check `storage/logs/laravel.log`
2. Check Hostinger error logs
3. Verify all configuration files
4. Test endpoints individually

