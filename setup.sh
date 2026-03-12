#!/bin/bash
# ============================================================
# TransactiWar — Docker Setup & Run Script
# Sets up and runs the web application inside Docker containers
# ============================================================

set -e

echo "============================================="
echo "  TransactiWar — Setup & Run"
echo "============================================="

# 1. Create secrets directory and password file if missing
if [ ! -f secrets/db_password.txt ]; then
    echo "[+] Creating secrets/db_password.txt..."
    mkdir -p secrets
    echo -n "V3rY_S3cur3_P@ssw0rd!" > secrets/db_password.txt
    echo "    Default DB password created."
else
    echo "[✓] secrets/db_password.txt already exists."
fi

# 2. Ensure uploads directory exists (Docker volume may not create it)
if [ ! -d app/uploads ]; then
    echo "[+] Creating app/uploads directory..."
    mkdir -p app/uploads
fi

# 3. Ensure TLS certificate directory exists
if [ ! -d proxy/certs ]; then
    echo "[!] proxy/certs/ not found."
    echo "    Generating self-signed TLS certificate for localhost..."
    mkdir -p proxy/certs
    openssl req -x509 -newkey ec \
        -pkeyopt ec_paramgen_curve:P-384 \
        -keyout proxy/certs/server.key \
        -out proxy/certs/server.crt \
        -days 365 -nodes \
        -subj "/CN=localhost"
    echo "[✓] Self-signed certificate generated."
else
    echo "[✓] TLS certificates already exist."
fi

# 4. Stop any existing containers
echo ""
echo "[*] Stopping any existing TransactiWar containers..."
docker-compose down 2>/dev/null || true

# 5. Build and start containers
echo ""
echo "[*] Building and starting containers..."
docker-compose up -d --build

# 6. Wait for the database to be ready
echo ""
echo "[*] Waiting for PostgreSQL to be ready..."
RETRIES=30
until docker-compose exec -T db pg_isready -h /tmp -U u_py09Karta -d asaNkrak_0sh > /dev/null 2>&1; do
    RETRIES=$((RETRIES - 1))
    if [ $RETRIES -le 0 ]; then
        echo "[✗] PostgreSQL did not become ready in time."
        echo "    Try running: docker-compose logs db"
        exit 1
    fi
    echo "    Waiting... ($RETRIES attempts remaining)"
    sleep 2
done
echo "[✓] PostgreSQL is ready."

# 7. Load the database schema
echo ""
echo "[*] Loading database schema (database.sql)..."
docker-compose exec -T db psql -h /tmp -U u_py09Karta -d asaNkrak_0sh -f /docker-entrypoint-initdb.d/init.sql
echo "[✓] Database schema loaded."

# 8. Seed test accounts
echo ""
echo "[*] Seeding test user accounts..."
docker-compose exec -T web php /var/www/html/setup_users.php 2>/dev/null || echo "    (Some users may already exist)"
echo "[✓] Test accounts created."

# 9. Done
echo ""
echo "============================================="
echo "  TransactiWar is now running!"
echo "============================================="
echo ""
echo "  Access the app at: https://localhost:8443"
echo "  (Accept the self-signed certificate warning in your browser)"
echo ""
echo "  Default test accounts (passwords meet complexity policy:"
echo "  6-50 chars, uppercase + lowercase + digit + special char):"
echo ""
echo "    alice      / Alice1@pass"
echo "    bob_b      / Bob2#secure"
echo "    charlie    / Charlie3!x"
echo "    eve_hacker / Eve4\$hack"
echo ""
echo "  To stop:  docker-compose down"
echo "  To reset: docker-compose down -v && ./setup.sh"
echo "============================================="
