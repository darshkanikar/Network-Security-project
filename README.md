# Secure Money Transfer Web Application

A secure web application for money transfers and user profiles, built **without any third-party security frameworks** - all security mechanisms are implemented from scratch to demonstrate understanding of each control.

---

## Features

- **User Authentication**: Registration/Login with `password_hash(ARGON2ID)`, session management with idle (30 min) and absolute (2 hr) timeouts, and session ID regeneration on login.

- **Money Transfer**: Transfer money by username with atomic DB transactions (`BEGIN`/`COMMIT`/`ROLLBACK`), row-level locking (`SELECT ... FOR UPDATE`), negative balance prevention, and full transaction history.

- **Profile Management**: Update bio, full name, profile image. Secure image uploads with GD pixel-level re-processing (strips EXIF/metadata) and MIME magic-byte validation.

- **User Search**: Search users by username or full name (LIKE wildcards properly escaped).

- **Comments**: Optional comments on transfers, visible to the receiver.

- **Activity Logging**: Audit trail in DB for every security-relevant event: login, logout, failed logins, rate limits, CSRF failures, registrations, transfers, profile updates, and password changes.

- **Security**: Manual CSRF tokens, PDO prepared statements, XSS output escaping, rate limiting on login and transfers, secure session configuration, input validation and sanitization.

---

## Prerequisites

- [Docker](https://docs.docker.com/get-docker/)
- [Docker Compose](https://docs.docker.com/compose/install/)
- `openssl` (pre-installed on most Linux/macOS systems - needed for TLS cert generation)

---

## Quick Start (One-Click Setup)

```bash
# Step 1: Load the bundled base Docker images (required on first run)
docker load -i base-images.tar

# Step 2: Run the setup script
chmod +x setup.sh
./setup.sh
```

> **Why docker load first?** The Nginx and PostgreSQL images are from a private registry (dhi.io). The bundled base-images.tar contains these pre-saved images so you don't need access to that registry. The PHP 8.4/Apache image is pulled automatically from Docker Hub.

The `setup.sh` script will:

1. Create the `secrets/db_password.txt` if missing
2. Generate a self-signed TLS certificate if `proxy/certs/` is missing
3. Build the Docker containers (PHP 8.4/Apache + PostgreSQL 18 + Nginx 1.29.5 reverse proxy)
4. Wait for the database to become ready
5. Load the database schema (`database.sql`)
6. Seed test user accounts (`setup_users.php`)

Once complete, open **https://localhost:8443** in your browser.

> Accept the self-signed certificate warning (this is expected in development).

---

## Manual Setup (Alternative)

```bash
# 1. Load bundled base images (required - private registry images)
docker load -i base-images.tar

# 2. Create secrets file
mkdir -p secrets && echo -n "V3rY_S3cur3_P@ssw0rd!" > secrets/db_password.txt

# 3. Generate TLS certificate
mkdir -p proxy/certs
openssl req -x509 -newkey ec -pkeyopt ec_paramgen_curve:P-384 \
    -keyout proxy/certs/server.key -out proxy/certs/server.crt \
    -days 365 -nodes -subj "/CN=localhost"

# 4. Build and start containers
docker-compose up -d --build

# 5. Wait ~10 seconds for PostgreSQL to initialize, then load schema
docker-compose exec -T db psql -h /tmp -U u_py09Karta -d asaNkrak_0sh \
    -f /docker-entrypoint-initdb.d/init.sql

# 6. Seed test accounts
docker-compose exec -T web php /var/www/html/setup_users.php
```

---

## Default Test Accounts

Password policy: 6-50 characters, must include uppercase, lowercase, digit, and special character.

| Username     | Password       | Balance     |
|:-------------|:---------------|:------------|
| alice        | Alice1@pass    | Rs 1000.00  |
| bob_b        | Bob2#secure    | Rs 500.00   |
| charlie      | Charlie3!x     | Rs 250.00   |
| eve_hacker   | Eve4$hack      | Rs 100.00   |

---

## Bundled Docker Images

The application uses three container images. Two are from a private registry and are bundled as `base-images.tar`:

| Container | Image                                        | Source              | In base-images.tar? |
|:----------|:---------------------------------------------|:--------------------|:--------------------|
| proxy     | `dhi.io/nginx:1` (pinned to SHA256 digest)   | Private registry    | Yes - must docker load |
| web       | `php:8.4-apache` (pinned to SHA256 digest)   | Docker Hub (public) | No - pulled automatically |
| db        | `dhi.io/postgres:18` (pinned to SHA256 digest) | Private registry  | Yes - must docker load |

All images are **pinned to SHA256 digests** in `docker-compose.yml` and `Dockerfile` to prevent supply chain tampering.

To regenerate `base-images.tar` (e.g., after upgrading images):

```bash
docker save dhi.io/nginx:1 dhi.io/postgres:18 -o base-images.tar
```

---

## Project Structure

```
TransactiWar/
|-- setup.sh                  # One-click Docker setup and run script
|-- setup_users.php           # Seeds test user accounts
|-- Dockerfile                # PHP 8.4 / Apache (runs as non-root www-data)
|-- docker-compose.yml        # Service orchestration (web + db + proxy)
|-- entrypoint.sh             # Container entrypoint
|-- database.sql              # PostgreSQL schema (Hindi-transliterated names)
|-- base-images.tar           # Bundled Nginx + PostgreSQL Docker images
|-- .gitignore                # Excludes secrets, certs, uploads from VCS
|-- secrets/
|   +-- db_password.txt       # Database password (Docker secret - never committed)
|-- proxy/
|   |-- nginx.conf            # Nginx HTTPS-only reverse proxy
|   |-- Dockerfile            # Nginx image config
|   +-- certs/                # TLS key and certificate (never committed)
+-- app/                      # Application source code
    |-- index.php             # Landing page
    |-- login.php             # User login
    |-- register.php          # User registration
    |-- logout.php            # Logout (with audit log)
    |-- dashboard.php         # Balance and transaction history
    |-- transfer.php          # Money transfer (atomic transaction)
    |-- profile.php           # Profile management (bio, image upload)
    |-- user_profile.php      # View other users profiles
    |-- search.php            # User search (LIKE wildcard-safe)
    |-- reset_password.php    # Change password
    |-- config/
    |   +-- db.php            # Database connection (PDO + Docker secrets)
    |-- includes/
    |   |-- session.php       # Session management (timeouts, regeneration)
    |   |-- security.php      # CSRF, rate limiting, logging, image hardening
    |   |-- header.php        # Common header (Bootstrap CDN with SRI hash)
    |   +-- footer.php        # Common footer (Bootstrap JS with SRI hash)
    +-- uploads/              # User profile images (GD-reprocessed, isolated volume)
```

---

## Docker Architecture

```
Internet
    | HTTPS :8443
    v
+------------------------------+
|  Nginx 1.29.5 (proxy)        |  TLS termination, HSTS, server_tokens off
|  read_only, cap_drop: ALL    |  Only on: frontend network
+--------------+---------------+
               | HTTP :8080 (internal only)
               v
+------------------------------+
|  PHP 8.4 / Apache (web)      |  Runs as www-data (UID 33), non-root
|  read_only, cap_drop: ALL    |  On: frontend + backend networks
|  no-new-privileges: true     |
+--------------+---------------+
               | PostgreSQL (internal only)
               v
+------------------------------+
|  PostgreSQL 18 (db)          |  internal: true (no internet access)
|  read_only filesystem        |  Password via Docker secret
+------------------------------+
```

---

## Security Mechanisms Implemented

| OWASP Category | Mechanism | Implementation |
|:---------------|:----------|:---------------|
| **A01 Broken Access Control** | Authorization checks | `require_login()` on all protected pages; IDOR prevented via session-based user IDs |
| **A02 Cryptographic Failures** | Password hashing | `PASSWORD_ARGON2ID` (OWASP recommended over bcrypt) |
| **A02 Cryptographic Failures** | Transport security | HTTPS enforced at Nginx; HSTS header; TLS 1.2 + 1.3 only |
| **A02 Cryptographic Failures** | Secure cookies | `HttpOnly`, `Secure`, `SameSite=Strict` |
| **A03 Injection** | SQL Injection | PDO prepared statements on every query |
| **A03 Injection** | XSS | `htmlspecialchars(ENT_QUOTES, UTF-8)` on all output |
| **A03 Injection** | LIKE wildcard abuse | `str_replace` to escape `%`, `_`, `\` before LIKE queries |
| **A04 Insecure Design** | Financial integrity | `BEGIN`/`COMMIT`/`ROLLBACK` + `SELECT ... FOR UPDATE` row locking |
| **A05 Security Misconfiguration** | Error disclosure | `display_errors=Off`, `expose_php=Off`, `log_errors=On` |
| **A05 Security Misconfiguration** | Version disclosure | `server_tokens off` (Nginx), `ServerTokens Prod` (Apache) |
| **A05 Security Misconfiguration** | Security headers | `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `CSP` |
| **A06 Outdated Components** | Image pinning | Docker images pinned to SHA256 digests |
| **A06 Outdated Components** | CDN integrity | Bootstrap loaded with `integrity=` SRI hash + `crossorigin=anonymous` |
| **A07 Auth Failures** | Brute force | Rate limiting: 5 failed logins per IP+username per 15 minutes |
| **A07 Auth Failures** | Session fixation | `session_regenerate_id(true)` on every login |
| **A07 Auth Failures** | Account enumeration | Uniform "Invalid username or password." for all failures |
| **A07 Auth Failures** | Session timeout | 30-min idle timeout + 2-hour absolute timeout |
| **A08 Software Integrity** | Supply chain | No runtime third-party PHP/JS libraries; Docker images digest-pinned |
| **A09 Logging and Monitoring** | Audit trail | DB audit log for: login, logout, failed login, CSRF failure, registration, transfer, profile update, password change |
| **A11 Exception Handling** | Fail closed | All exceptions roll back DB transactions; PDOExceptions logged internally, not shown to users |
| **File Upload** | Image hardening | MIME magic-byte validation, GD pixel-level rewrite (strips EXIF/polyglots), random filename, PHP execution disabled in uploads/ |
| **Container Security** | Least privilege | `cap_drop: ALL`, `no-new-privileges: true`, `read_only: true` on all containers |
| **Network** | Isolation | DB on `internal: true` network - unreachable from internet; Nginx cannot reach DB |

---

## Stopping and Resetting

```bash
# Stop the application (data preserved)
docker-compose down

# Full reset (deletes all data and volumes)
docker-compose down -v
./setup.sh
```

---

## References

### OWASP

- [OWASP Top 10 (2021)](https://owasp.org/www-project-top-ten/)
- [OWASP CSRF Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html)
- [OWASP Session Management Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html)
- [OWASP Password Storage Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Password_Storage_Cheat_Sheet.html)
- [OWASP SQL Injection Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/SQL_Injection_Prevention_Cheat_Sheet.html)
- [OWASP File Upload Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html)
- [OWASP Docker Security Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Docker_Security_Cheat_Sheet.html)

### PHP and Language References

- [PHP password_hash - Argon2id](https://www.php.net/manual/en/function.password-hash.php)
- [PHP PDO Prepared Statements](https://www.php.net/manual/en/pdo.prepared-statements.php)
- [PHP GD Image Processing](https://www.php.net/manual/en/book.image.php)
- [PHP session_regenerate_id](https://www.php.net/manual/en/function.session-regenerate-id.php)
- [PHP random_bytes (CSPRNG)](https://www.php.net/manual/en/function.random-bytes.php)

### Docker and Infrastructure

- [Docker Security Best Practices](https://docs.docker.com/engine/security/)
- [Docker Compose Documentation](https://docs.docker.com/compose/)
- [Docker Secrets](https://docs.docker.com/engine/swarm/secrets/)
- [Nginx Reverse Proxy Guide](https://docs.nginx.com/nginx/admin-guide/web-server/reverse-proxy/)
- [PostgreSQL Documentation](https://www.postgresql.org/docs/)

### Standards and Guidelines

- [NIST SP 800-63B - Digital Identity Guidelines (Password Rules)](https://pages.nist.gov/800-63-3/sp800-63b.html)
- [Mozilla TLS Configuration Generator](https://ssl-config.mozilla.org/)
- [Subresource Integrity (SRI) - MDN](https://developer.mozilla.org/en-US/docs/Web/Security/Subresource_Integrity)
