# Security Design Document - TransactiWar

## Overview
TransactiWar is a secure web application built with core PHP (no frameworks), designed to withstand "War Game" attacks. This document reflects all security measures currently implemented.

---

## Security Controls

### 1. Authentication & Session Management
- **Password Hashing**: `password_hash()` with Bcrypt (cost factor 10). Salt is auto-generated and embedded in the hash — unique per user, rainbow tables are useless.
- **Session Hardening**:
  - `session.cookie_httponly = 1` — prevents JavaScript from accessing the session cookie (XSS session theft mitigated).
  - `session.use_only_cookies = 1` — session ID never passed in URL.
  - `session_regenerate_id(true)` called on login and every 15 minutes to prevent session fixation.
  - **Idle Timeout**: Session destroyed after 30 minutes of inactivity.
  - **Absolute Timeout**: Session destroyed after 2 hours regardless of activity.
- **Username Immutability**: Username cannot be changed after registration (prevents impersonation).

### 2. Rate Limiting & Account Lockout
- **Login Attempts Tracked**: Failed login attempts are recorded in the `login_attempts` table with IP address, username, and timestamp.
- **Lockout Policy**: After **5 failed attempts within 15 minutes** (by IP or username), further login attempts are blocked.
- **Auto-Unlock**: Lockout expires automatically after 15 minutes — no manual admin action needed.
- **Failed Login Logging**: All failed and blocked attempts are logged to the `logs` table.

### 3. Input Validation & Output Encoding
- **XSS Prevention**: All user-generated output escaped via `htmlspecialchars($data, ENT_QUOTES, 'UTF-8')` through the `sanitize_input()` helper.
- **SQL Injection**: All queries use PDO Prepared Statements. Emulated prepares are disabled.
- **Username Validation**: 4–18 characters, alphanumeric and underscores only (`/^[a-zA-Z0-9_]{4,18}$/`).
- **Password Complexity**: 6–14 characters, must include at least 1 uppercase, 1 lowercase, and 1 digit.
- **Email Validation**: `filter_var($email, FILTER_VALIDATE_EMAIL)` on registration.
- **Field Length Limits**: Full name ≤ 100 chars, bio ≤ 500 chars, transfer comment ≤ 200 chars.

### 4. CSRF Protection
- **Token Based**: Cryptographically secure random token (`bin2hex(random_bytes(32))`) generated per session.
- **Validation**: All state-changing POST forms verify the `csrf_token` before processing.

### 5. File Upload Security
- **MIME Validation**: Uses `finfo_file()` to detect actual MIME type — not relying on user-supplied extension or `$_FILES['type']`. Whitelist: `image/jpeg`, `image/png`, `image/gif`.
- **GD Re-processing**: Uploaded images are decoded and re-saved through PHP's GD library — strips EXIF metadata, embedded PHP code, and other payloads.
- **Dimension Check**: Images exceeding 2000×2000 pixels are rejected.
- **Secure Filenames**: Renamed to `bin2hex(random_bytes(16))` — no guessable names.
- **Extension from MIME**: File extension is derived from the validated MIME type, not user input.
- **Execution Disabled**: Apache config blocks PHP execution in `uploads/` directory (`php_admin_flag engine off`).
- **Size Limit**: Max 2MB per upload.

### 6. Transaction Safety
- **ACID Transactions**: Money transfers use `BEGIN TRANSACTION`, `COMMIT`, and `ROLLBACK`.
- **Race Condition Protection**: `SELECT ... FOR UPDATE` locks user rows during transfer to prevent double-spending or negative balances under concurrent requests.
- **Transfer via Dropdown**: Recipient selected from a dropdown of real users — prevents typos targeting non-existent accounts and reduces user enumeration risk from error messages.
- **Validation**: Checks for self-transfer, insufficient funds, and valid recipient.

### 7. Password Reset (OTP)
- **6-digit OTP**: Generated with `random_int()` (cryptographically secure), stored hashed in DB.
- **Expiry**: OTP expires after 5 minutes.
- **No Email Enumeration**: Response is identical whether email exists or not.
- **One-time Use**: OTP and expiry fields cleared from DB immediately after successful reset.

### 8. Security Headers
Every response includes:
- `X-Frame-Options: DENY` — prevents clickjacking.
- `X-Content-Type-Options: nosniff` — prevents MIME-sniffing attacks.
- `Content-Security-Policy` — restricts resource loading to known trusted origins.
- `Referrer-Policy: no-referrer-when-downgrade`

### 9. Logging & Monitoring
All key actions logged to the `logs` table with user ID, IP address, and timestamp:
- `LOGIN` / `LOGIN_FAILED` / `LOGIN_BLOCKED`
- `TRANSFER_SENT` / `TRANSFER_RECEIVED`
- `PROFILE_UPDATE`
- `OTP_GENERATED` / `PASSWORD_RESET`

### 10. Access Control
- `require_login()` enforced on all authenticated pages.
- Users can only edit their own profile (session ID check).
- Users can only view their own transactions.
- Public profile page (`user_profile.php`) exposes **only** username, name, bio, image — no balance or email.

---

## Threat Model

| Attack | Mitigation |
|---|---|
| SQL Injection | PDO prepared statements |
| XSS | `htmlspecialchars()` on all output |
| CSRF | Per-session token validation |
| Brute Force | Rate limiting + lockout after 5 attempts |
| Session Hijacking | HttpOnly cookie, session regeneration, idle/absolute timeouts |
| Race Conditions | `SELECT ... FOR UPDATE` row locking |
| Malicious File Upload | GD re-processing, MIME whitelist, exec disabled in uploads/ |
| Password Cracking | Bcrypt with auto-salt |
| Account Takeover | OTP-based password reset with 5-min expiry |
| Clickjacking | `X-Frame-Options: DENY` |

---

## Known Remaining Limitations
- **No HTTPS**: Traffic is unencrypted in this local Docker setup. In production, use TLS with Let's Encrypt or similar.
- **OTP via screen**: OTP is displayed on-screen for local dev — in production, it must be sent via email (SMTP).
- **No 2FA**: Multi-factor authentication is not implemented.
- **File Storage**: Uploads stored on disk inside the container. In a distributed/production environment, use object storage (S3, GCS).
