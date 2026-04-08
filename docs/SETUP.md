# HotPlan Setup Guide

## Requirements

- PHP 8.1 or higher
- PDO extension (with SQLite, MySQL, or PostgreSQL support)
- cURL extension
- YAML parsing library (installed via Composer) or PHP `yaml` extension (optional)
- SQLite, MySQL, or PostgreSQL database

## Installation

### 1. Clone or Download the Project

```bash
git clone <repository-url> hotplan
cd hotplan
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Configure the Application

Copy the example configuration:

```bash
cp config/config.example.yaml config/config.yaml
```

PowerShell alternative:
```powershell
Copy-Item config/config.example.yaml config/config.yaml
```

Edit `config/config.yaml`:

```yaml
app:
  name: "HotPlan"
  timezone: "Europe/Bratislava"
  debug: false

database:
  type: "sqlite"
  path: "data/hotplan.db"

voip:
  provider: "sipura"
  host: "10.11.49.84"
  port: 80
  path: "/admin/bsipura.spa"
  forward_param: "43567"
```

### 4. Set Up Credentials

Create a credentials file (recommended) or use environment variables:

**Option A: Environment Variables**
```bash
export VOIP_USERNAME="admin"
export VOIP_PASSWORD="Apw44ai"
```

PowerShell alternative:
```powershell
$env:VOIP_USERNAME="admin"
$env:VOIP_PASSWORD="Apw44ai"
```

**Option B: Credentials File**
```bash
# Edit config/config.yaml
credentials:
  voip_username: "admin"
  voip_password: "Apw44ai"
```

### 5. Initialize Database

Run the migrations:

```bash
php public/cli.php db:migrate
```

Optional: add sample data (for testing):
```bash
php public/cli.php db:seed
```

Or via Composer:
```bash
composer migrate
```

Or manually (SQLite):
```bash
sqlite3 data/hotplan.db < database/migrations/001_initial_schema.sql
```

### 6. Set Directory Permissions

```bash
chmod 755 data logs
```

Windows note: `data/` is created automatically for SQLite; create `logs/` manually if you want file logging:
```powershell
New-Item -ItemType Directory -Force data,logs | Out-Null
```

## Configuration Reference

### Database Configuration

```yaml
database:
  type: "sqlite"  # sqlite, mysql, pgsql
  path: "data/hotplan.db"  # For SQLite
  
  # For MySQL:
  # host: "localhost"
  # port: 3306
  # name: "hotplan"
  # username: "user"
  # password: "password"
```

### VoIP Configuration

```yaml
voip:
  provider: "sipura"  # sipura, cisco, grandstream, generic
  host: "10.11.49.84"
  port: 80
  path: "/admin/bsipura.spa"
  timeout: 30
  retry_count: 3
  retry_delay: 5
  forward_param: "43567"
  forward_prefix: ""
  auth_type: "digest"
```

### Behavior Configuration

```yaml
behavior:
  # What to do when no rule matches
  # options: fallback, voicemail, nothing, last_known
  on_no_rule: "fallback"
  
  # What to do on device error
  # options: keep_last, clear, voicemail, nothing
  on_device_error: "keep_last"
  
  # How to handle multiple matching rules
  # options: priority, random, roundrobin, first_match
  on_multiple_match: "priority"
  
  enable_logging: true
  log_retention_days: 90
```

### Scheduler Configuration

```yaml
scheduler:
  enabled: true
  check_interval: 60    # seconds
  preload_minutes: 5    # minutes before event to activate
```

## Running the Application

## Web GUI (Simple)

Start a local PHP dev server (recommended for quick setup):
```bash
php -S 127.0.0.1:8080 -t public public/index.php
```
Then open `http://127.0.0.1:8080/` in your browser.

Login:
- Default: `admin` / `admin`
- Change it in `config/config.yaml` under `security.web_admin_*` (recommended)

### CLI Mode (Cron-based)

Single execution:
```bash
php public/cli.php forward:execute
```

Preview forwarding:
```bash
php public/cli.php forward:preview --start="2024-01-01" --end="2024-01-07"
```

### Daemon Mode

Start scheduler:
```bash
php public/cli.php scheduler:start
```

Stop scheduler:
```bash
php public/cli.php scheduler:stop
```

Check status:
```bash
php public/cli.php scheduler:status
```

### Web Server

Configure your web server to point to `public/`:

**Apache:**
```apache
<VirtualHost *:80>
    DocumentRoot /path/to/hotplan/public
    ServerName hotplan.local
    
    <Directory /path/to/hotplan/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

**Nginx:**
```nginx
server {
    listen 80;
    server_name hotplan.local;
    root /path/to/hotplan/public;
    index index.php;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## Setting Up Cron Jobs

Add to crontab:
```bash
# Run forwarding check every minute
* * * * * /usr/bin/php /path/to/hotplan/public/cli.php forward:execute

# Log rotation daily
0 0 * * * /usr/bin/php /path/to/hotplan/public/cli.php log:rotate
```

## Windows Task Scheduler

Create a task to run every minute:
```batch
php C:\path\to\hotplan\public\cli.php forward:execute
```

## Testing

Run unit tests:
```bash
./vendor/bin/phpunit
```

Run specific test:
```bash
./vendor/bin/phpunit tests/DecisionEngineTest.php
```

## Troubleshooting

### Device Not Reachable

1. Check network connectivity:
   ```bash
   ping 10.11.49.84
   ```

2. Check if device web interface is accessible:
   ```bash
   curl -v http://10.11.49.84/admin/
   ```

3. Verify credentials in configuration

### Database Errors

1. Check database file permissions:
   ```bash
   ls -la data/
   ```

2. Verify SQLite extension is enabled:
   ```bash
   php -m | grep sqlite
   ```

### Forwarding Not Changing

1. Check logs:
   ```bash
   tail -f logs/hotplan.log
   ```

2. Verify rules are active:
   ```bash
   php public/cli.php rules:list
   ```

3. Check if override is active:
   ```bash
   php public/cli.php override:list
   ```

## Security Checklist

- [ ] Changed default VoIP device password
- [ ] Set proper file permissions
- [ ] Enabled HTTPS on web server
- [ ] Secured database credentials
- [ ] Configured firewall rules
- [ ] Enabled logging
- [ ] Set up log rotation

## Next Steps

1. Add employees with phone numbers
2. Configure working hours
3. Set up rotation groups
4. Create forwarding rules
5. Add holidays
6. Test the system

## API Usage Examples

### Set Forwarding Manually
```bash
curl -X POST http://localhost/api/forward \
  -H "Content-Type: application/json" \
  -d '{"number": "+421901234567", "reason": "Testing"}'
```

### Get Current Status
```bash
curl http://localhost/api/status
```

### Create Override
```bash
curl -X POST http://localhost/api/override \
  -H "Content-Type: application/json" \
  -d '{"number": "999", "until": "2024-12-31 18:00", "reason": "Holiday"}'
```

---

For architecture details, see [ARCHITECTURE.md](ARCHITECTURE.md).
For API documentation, see [API.md](API.md).
