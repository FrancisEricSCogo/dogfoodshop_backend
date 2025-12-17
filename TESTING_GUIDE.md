# Laravel API Testing Guide

## 🚀 Quick Test

### 1. Test Products (No Auth Required)
Open in browser or use curl:
```
http://localhost:8000/api/products/get_products.php
http://localhost:8000/api/products/get_product.php?id=1
```

### 2. Test Login
Use Postman, curl, or browser console:

```javascript
// Test Login
fetch('http://localhost:8000/api/auth/login.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        username: 'your_username',
        password: 'your_password'
    })
})
.then(r => r.json())
.then(data => {
    console.log('Login Response:', data);
    if (data.success) {
        localStorage.setItem('token', data.user.token);
        localStorage.setItem('user', JSON.stringify(data.user));
    }
});
```

### 3. Test Protected Endpoint (After Login)
```javascript
const token = localStorage.getItem('token');

fetch('http://localhost:8000/api/orders/get_orders.php', {
    headers: {
        'Authorization': `Bearer ${token}`
    }
})
.then(r => r.json())
.then(data => console.log('Orders:', data));
```

## 📋 All Endpoints Status

✅ **All 25+ endpoints are registered and ready!**

### Public Endpoints (No Auth):
- ✅ GET `/api/products/get_products.php`
- ✅ GET `/api/products/get_product.php`
- ✅ POST `/api/auth/login.php`
- ✅ POST `/api/auth/register.php`
- ✅ POST `/api/auth/verify_otp.php`
- ✅ POST `/api/auth/request_password_reset.php`
- ✅ POST `/api/auth/reset_password.php`

### Protected Endpoints (Require Token):
- ✅ POST `/api/auth/logout`
- ✅ POST `/api/products/add_product.php`
- ✅ PUT `/api/products/update_product.php`
- ✅ DELETE `/api/products/delete_product.php`
- ✅ POST `/api/products/upload_image.php`
- ✅ GET `/api/orders/get_orders.php`
- ✅ GET `/api/orders/get_order.php`
- ✅ POST `/api/orders/create_order_bulk.php`
- ✅ PUT `/api/orders/update_order_status.php`
- ✅ GET `/api/users/profile.php`
- ✅ PUT `/api/users/profile.php`
- ✅ POST `/api/users/upload_avatar.php`
- ✅ GET `/api/admin/get_users.php`
- ✅ POST `/api/admin/create_user.php`
- ✅ PUT `/api/admin/update_user.php`
- ✅ DELETE `/api/admin/delete_user.php`
- ✅ GET `/api/notifications/get_notifications.php`
- ✅ PUT `/api/notifications/mark_read.php`

## 🧪 Testing Checklist

- [ ] Products load correctly
- [ ] Login works with existing credentials
- [ ] Registration sends OTP email
- [ ] OTP verification creates account
- [ ] Password reset flow works
- [ ] Protected endpoints require token
- [ ] Orders can be created
- [ ] Profile can be updated
- [ ] Admin functions work (if admin user)

## 🔧 Troubleshooting

### If endpoints return 404:
- Make sure Laravel server is running: `php artisan serve`
- Check server is on port 8000

### If authentication fails:
- Check token is in Authorization header: `Bearer <token>`
- Verify token exists in database `users` table

### If database errors:
- Verify MySQL is running in XAMPP
- Check `.env` database settings

### Check Laravel logs:
```
storage/logs/laravel.log
```

## ✅ Current Status

- ✅ Laravel server: Running on port 8000
- ✅ Database: Connected
- ✅ Routes: All registered
- ✅ Middleware: Working
- ✅ Mail: Configured
- ✅ Storage: Linked

**Ready for frontend integration!**

