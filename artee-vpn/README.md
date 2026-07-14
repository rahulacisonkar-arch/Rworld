# Artee VPN

Enterprise-grade Zero Trust VPN built on top of NetBird (WireGuard) with a custom administration portal written in **PHP 7.0.1** and **MySQL**.

## Project Structure

```
artee-vpn/
├── Dockerfile.php             # PHP 7.0 + Apache container configuration
├── docker-compose.yml         # Orchestrates MySQL, PHP, NetBird (mgmt/signal/coturn), and Caddy
├── .env                       # Environment variables (credentials, domains, ports)
├── app/                       # PHP Application backend code
│   ├── config/
│   │   └── database.php       # DB Connection configuration
│   └── helpers/
│       └── auth.php           # PHP session, login, and logging helpers
├── config/                    # Configuration files for Caddy, NetBird, and TURN
│   ├── Caddyfile
│   ├── management.json
│   └── turnserver.conf
├── database/
│   └── schema.sql             # MySQL database schema (Peers, Setup Keys, Logs)
└── public/                    # Web-facing pages (Landing page, CSS, Dashboard)
    ├── index.php              # Branded Landing Page
    ├── login.php              # Sign In page (CSRF protected)
    ├── logout.php             # Session destruction handler
    ├── dashboard.php          # Admin status console (live stats, recent activity)
    ├── assets/
    │   └── style.css          # Premium Custom CSS (Modern Glassmorphism Design)
    └── api/
        └── stats.php          # Live status JSON API endpoint
```

## Production Deployment Steps

To deploy Artee VPN to your production Linux server:

1. **Prerequisites**:
   * A Linux VPS (Ubuntu 20.04/22.04 recommended) with a public IP.
   * Docker & Docker Compose installed.
   * Ports `80/tcp`, `443/tcp/udp` (Caddy HTTPS), and `3478/udp` (STUN/TURN) open.
   * Point a domain name (e.g. `vpn.artee.com`) to your server's public IP address.

2. **Clone/Copy Files**:
   Copy the `artee-vpn` directory to your server.

3. **Configure Settings**:
   * Open `.env` and set `NETBIRD_DOMAIN` to your domain.
   * Replace the dummy secrets in `.env` with strong random keys (e.g., `openssl rand -hex 32`).
   * Update the database root passwords.
   * Open `config/turnserver.conf` and replace `YOUR_SERVER_PUBLIC_IP` with your actual public IP.

4. **Launch the Stack**:
   Run the following command inside the `artee-vpn/` folder:
   ```bash
   docker compose up -d --build
   ```

5. **Access Your Dashboard**:
   * Navigate to `https://your-domain.com/login.php` to access the custom PHP dashboard.
   * Default credentials:
     * **Email**: `admin@artee.com`
     * **Password**: `Admin@123`
   * *Ensure you change this password immediately via database query or code update for security.*
