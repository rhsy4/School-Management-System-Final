# Systemийн Сайжруулалтын Тестийн Гарын Аваас

## 🧪 Хүчин Чадлын Тестүүд

### 1️⃣ Rate Limiting Test (Хүчний хязгаарлалт)

```bash
# Terminal 1: 6 удаа буруу нэвтрэх оролдлого
for i in {1..6}; do
  curl -X POST http://localhost/school_system1/index.php \
    -d "username=admin&password=wrong" \
    -c cookie.txt
  sleep 2
done

# 6-р оролдлого дээр:
# ✅ "Хэт олон буруу оролдлого. 10 минутын дараа дахин оролдоно уу."
```

### 2️⃣ Password Reset Rate Limiting

```bash
# 4 удаа password reset хүсэх
for i in {1..4}; do
  curl -X POST http://localhost/school_system1/forgot.php \
    -d "login=admin@school.mn" \
    -c cookie_reset.txt
  sleep 5
done

# 4-р оролдлого дээр:
# ✅ "Хэт олон оролдлого. 15 минутын дараа..."
```

### 3️⃣ User Enumeration Test

```bash
# A. Оршин буй хэрэглэгч
curl -X POST http://localhost/school_system1/forgot.php \
  -d "login=admin@school.mn"

# B. Байхгүй хэрэглэгч
curl -X POST http://localhost/school_system1/forgot.php \
  -d "login=nonexistent@school.mn"

# ✅ Хоёулаа санамсаргүй мэдэгдэл: "email_sent" (ялгаатай биш)
```

### 4️⃣ Security Headers Test

```bash
# curl -I http://localhost/school_system1/dashboard.php

# Шалгаарай дараахь header-үүдийг харах:
X-Frame-Options: SAMEORIGIN
X-Content-Type-Options: nosniff
X-XSS-Protection: 1; mode=block
Content-Security-Policy: default-src 'self'...
```

### 5️⃣ Session Regeneration Test

```bash
# 1. Нэвтрэх
curl -X POST http://localhost/school_system1/index.php \
  -d "username=admin&password=password123" \
  -c session.txt

# 2. Session ID-г авах
cat session.txt | grep PHPSESSID

# 3. 30+ минутын дараа дахин хандах
sleep 1860 && curl -H "Cookie: $(cat session.txt)" \
  http://localhost/school_system1/dashboard.php

# ✅ Шинэ PHPSESSID байх
```

### 6️⃣ CSRF Token Validation

```bash
# Мэдээлэл илгээх бага CSRF token
curl -X POST http://localhost/school_system1/forgot.php \
  -d "login=admin@school.mn" \
  -d "csrf=invalid_token"

# ✅ CSRF алдаа: "Invalid CSRF token"
```

---

## 🔐 Environment Variable Test

### Test 1: .env загвар дээрээс үүсгэх

```bash
cd /xampp/htdocs/school_v2.1
cp .env.example .env
```

### Test 2: .env ба config-ийг өөрчлөх

```bash
# .env дэхь утгуудыг өөрчлөх:
echo "DB_NAME=school_db_test" >> .env
echo "DEBUG_MODE=true" >> .env
echo "MAIL_ENABLED=false" >> .env
```

### Test 3: PHP скриптээр утгуудыг шалгаа

```php
<?php
require_once 'includes/config.php';

echo "DB_NAME: " . DB_NAME . "\n";      // ← school_db_test
echo "DEBUG_MODE: " . DEBUG_MODE . "\n"; // ← 1 (true)
echo "MAIL_ENABLED: " . MAIL_ENABLED . "\n"; // ← (empty)
?>
```

---

## 📊 Performance Impact

| Функциал | Нөлөө | Хүндэлгээ |
|----------|-------|---------|
| Security headers | +0ms | Minimal |
| Rate limiting | +1-2ms | Session read/write |
| Session regeneration | +2-5ms | Once per 30 min |
| .env loading | +1ms | Only on app start |
| CSRF validation | +1ms | All POST requests |

---

## ✅ Before & After Comparison

### Password Reset User Enumeration

**ӨМНӨ (Алдаатай)**
```
User exists: "Success! Reset link: http://...../reset.php?token=xyz"
User not exists: "Success! notfound"
```
↑ Attacker can enumerate users

**ОДОО (Сайн)**
```
User exists: "Сэргээх мэдэгдэл имэйлээр явуулсан"
User not exists: "Сэргээх мэдэгдэл имэйлээр явуулсан"
```
↑ Same message -> Can't enumerate

---

### Login Security

**ӨМНӨ (Алдаатай)**
```
Brute force: ✓ No limit
10 failed attempts: Allowed
1000 failed attempts/hour: Allowed
```

**ОДОО (Сайн)**
```
Brute force: ✗ Rate limited
5 failed attempts: Blocked for 10 min
10 failed attempts: Blocked for 10 min
```

---

### Configuration Security

**ӨМНӨ (Алдаатай)**
```php
define('DB_USER', 'root');           // Hardcoded
define('DB_PASS', '');               // Hardcoded
```

**ОДОО (Сайн)**
```php
define('DB_USER', getEnv('DB_USER', 'root'));  // From .env
define('DB_PASS', getEnv('DB_PASS', ''));      // From .env
```

---

## 🐛 Debugging

### Check if environment variables loaded

```php
<?php
require_once 'includes/env.php';

// Should print loaded variables
print_r(getenv());
?>
```

### Check security headers

```bash
# Linux/Mac
curl -I http://localhost/school_system1/dashboard.php | grep -i "Content-Security"

# Windows PowerShell
$response = Invoke-WebRequest -Uri "http://localhost/school_system1/dashboard.php" -Method Head
$response.Headers["Content-Security-Policy"]
```

### Check session configuration

```php
<?php
echo session_get_cookie_params()['httponly'] . "\n";  // 1 = good
echo session_get_cookie_params()['samesite'] . "\n";  // "Strict" = good
?>
```

---

## 🚀 Deployment Checklist

- [ ] Create `.env` file (copy from `.env.example`)
- [ ] Update database credentials in `.env`
- [ ] Set `DEBUG_MODE=false` in `.env`
- [ ] Set `HTTPS_REDIRECT=true` if using HTTPS
- [ ] Set `SESSION_SECURE_COOKIE=true` if using HTTPS
- [ ] Configure email (if needed)
- [ ] Test rate limiting
- [ ] Test security headers with curl
- [ ] Run manual authentication flow
- [ ] Verify audit logs for login/logout events

---

## 🔧 Troubleshooting

### "Function getEnv() not found"
**Fix**: Make sure `includes/config.php` is loaded first
```php
require_once 'includes/config.php';  // ← Must be first
```

### "X-Frame-Options header not showing"
**Fix**: Check if headers already sent
```php
// This must be BEFORE any output
require_once 'includes/config.php';  // Sets headers

echo "Hello"; // ← Now output is OK
```

### Rate limiting not working
**Fix**: Check session is started
```php
session_start();  // ← Must call this first
if (!checkRateLimit(...)) { ... }
```

### .env not being read
**Fix**: Check file exists and is readable
```bash
ls -l /xampp/htdocs/school_system1/.env
# Should show: -rw-r--r--  (or similar)

# Make readable
chmod 644 /xampp/htdocs/school_system1/.env
```

---

## 📞 Support

- **Rate Limiting Issue**: Check `$_SESSION` keys, look for `rate_limit_*`
- **Email Not Sending**: Verify `MAIL_ENABLED=true` and SMTP settings
- **Security Headers Missing**: Check response headers with `curl -I`
- **Session Expiring**: Check `SESSION_TIMEOUT` in `.env`

---

**Created by**: Security Hardening Module  
**For**: Unified School Management System  
**Version**: 2.2
