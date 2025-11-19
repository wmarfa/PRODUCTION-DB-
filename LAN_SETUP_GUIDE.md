# Production Management System - LAN Setup Guide

## Overview
This guide will help you set up the Production Management System for offline operation on your Local Area Network (LAN).

## System Requirements

### Server Requirements
- **Operating System**: Windows 10/11, Linux (Ubuntu 18.04+), or macOS 10.15+
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **PHP**: Version 7.4+ (8.0+ recommended)
- **Database**: MySQL 5.7+ or MariaDB 10.3+
- **RAM**: Minimum 2GB, 4GB+ recommended
- **Storage**: Minimum 10GB free space
- **Network**: Ethernet connection to your LAN

### Client Requirements
- **Device**: Any modern web browser (Chrome, Firefox, Safari, Edge)
- **Network**: WiFi or Ethernet connection to the same LAN
- **Screen Resolution**: Minimum 1024x768 (Tablets: 768x1024+)

## 🚀 Quick Setup (15 Minutes)

### Step 1: Install Requirements

#### Windows Setup
```bash
# Install XAMPP (includes Apache, PHP, MySQL)
# Download from: https://www.apachefriends.org/
# Run installer with default settings

# Start Apache and MySQL from XAMPP Control Panel
```

#### Linux Setup (Ubuntu/Debian)
```bash
# Update package list
sudo apt update

# Install Apache, PHP, and MySQL
sudo apt install apache2 php php-mysql mysql-server

# Enable and start services
sudo systemctl enable apache2 mysql
sudo systemctl start apache2 mysql
```

#### macOS Setup
```bash
# Install Homebrew (if not already installed)
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"

# Install Apache, PHP, and MySQL
brew install apache2 php mysql

# Start services
brew services start apache2
brew services start mysql
```

### Step 2: Configure Database

1. **Access Database**:
   - Open phpMyAdmin (usually http://localhost/phpmyadmin)
   - Or use MySQL command line

2. **Create Database**:
   ```sql
   CREATE DATABASE performance_tracking CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. **Create User** (optional but recommended):
   ```sql
   CREATE USER 'production_user'@'localhost' IDENTIFIED BY 'your_password';
   GRANT ALL PRIVILEGES ON performance_tracking.* TO 'production_user'@'localhost';
   FLUSH PRIVILEGES;
   ```

### Step 3: Deploy Files

1. **Copy Files to Web Root**:
   - **Windows**: Copy all files to `C:/xampp/htdocs/production/`
   - **Linux**: Copy to `/var/www/html/production/`
   - **macOS**: Copy to `/usr/local/var/www/production/`

2. **Set File Permissions** (Linux/macOS):
   ```bash
   sudo chown -R www-data:www-data /var/www/html/production/
   sudo chmod -R 755 /var/www/html/production/
   ```

### Step 4: Configure Database Connection

Edit `config.php`:
```php
// Update these values
private $host = "localhost";
private $db_name = "performance_tracking";
private $username = "root"; // or your created user
private $password = ""; // your database password
```

### Step 5: Initialize Database

Open your browser and navigate to:
```
http://localhost/production/setup_database.php
```

This will create all necessary tables and sample data.

### Step 6: Verify Installation

Open your browser and navigate to:
```
http://localhost/production/
```

You should see the main dashboard. Then test the offline version:
```
http://localhost/production/enhanced_dashboard_offline.php
```

## 📱 Mobile Access Setup

### Find Your Server IP Address

#### Windows
```bash
ipconfig
# Look for "IPv4 Address" under your network adapter
```

#### Linux/macOS
```bash
ip addr show
# or
ifconfig
# Look for "inet" address
```

### Access from Mobile Devices

1. **Connect devices to the same WiFi network**
2. **Open browser and navigate to**:
   ```
   http://YOUR_SERVER_IP/production/
   ```
   For mobile interface:
   ```
   http://YOUR_SERVER_IP/production/mobile_floor_manager_offline.php
   ```

### Mobile-Friendly URLs
- **Desktop Dashboard**: `http://YOUR_SERVER_IP/production/enhanced_dashboard_offline.php`
- **Mobile Floor Manager**: `http://YOUR_SERVER_IP/production/mobile_floor_manager_offline.php`
- **Production Scheduler**: `http://YOUR_SERVER_IP/production/production_scheduler.php`
- **Analytics**: `http://YOUR_SERVER_IP/production/analytics_engine.php`

## 🔧 Advanced Configuration

### Apache Configuration (httpd.conf)

```apache
# Enable rewrite module
LoadModule rewrite_module modules/mod_rewrite.so

# Directory configuration for production folder
<Directory "/var/www/html/production">
    Options Indexes FollowSymLinks
    AllowOverride All
    Require all granted

    # Enable PHP
    AddHandler application/x-httpd-php .php

    # Security headers
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
</Directory>
```

### Nginx Configuration

```nginx
server {
    listen 80;
    server_name localhost;
    root /var/www/html/production;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
}
```

### PHP Configuration (php.ini)

```ini
; Increase limits for production data
max_execution_time = 300
max_input_time = 300
memory_limit = 512M
post_max_size = 100M
upload_max_filesize = 100M

; Enable error logging
log_errors = On
error_log = /var/log/php_errors.log

; Production settings
display_errors = Off
display_startup_errors = Off

; Enable sessions
session.save_path = "/var/lib/php/sessions"
session.gc_maxlifetime = 1440
```

## 🔒 Security Considerations

### Basic Security
1. **Change Default Passwords**: Update database and admin passwords
2. **Restrict Network Access**: Only allow access from your LAN IP range
3. **Use HTTPS**: Install SSL certificate for encrypted communication
4. **Regular Backups**: Schedule automatic database backups

### Firewall Configuration

#### Windows Firewall
```cmd
# Allow Apache through firewall
netsh advfirewall firewall add rule name="Apache" dir=in action=allow program="C:\xampp\apache\bin\httpd.exe"
```

#### Linux UFW
```bash
# Allow Apache and limit to your network range
sudo ufw allow from 192.168.1.0/24 to any port 80
sudo ufw allow from 192.168.1.0/24 to any port 443
```

### Access Control (Optional)
Add this to `.htaccess` in the production folder:
```apache
# Restrict access to your LAN only
Order deny,allow
Deny from all
Allow from 192.168.1.0/24
Allow from 127.0.0.1
```

## 📊 System Maintenance

### Daily Tasks
- **Data Entry**: Record production data for each shift
- **Quality Checks**: Perform quality inspections
- **Alert Review**: Address any system alerts

### Weekly Tasks
- **Backup Database**: Export performance data
- **Review Analytics**: Check production trends
- **System Updates**: Apply any security patches

### Monthly Tasks
- **Full System Backup**: Complete system image
- **Performance Review**: Analyze monthly KPIs
- **Maintenance Schedule**: Review and update

## 🚨 Troubleshooting

### Common Issues

#### Database Connection Failed
```bash
# Check MySQL service status
sudo systemctl status mysql

# Restart MySQL
sudo systemctl restart mysql

# Check PHP MySQL module
php -m | grep mysql
```

#### Page Not Found (404)
1. Verify files are in correct web directory
2. Check file permissions
3. Restart web server

#### Mobile Access Not Working
1. Verify devices are on same network
2. Check firewall settings
3. Test with another device
4. Verify server IP address

#### Slow Performance
1. Check server resources (RAM, CPU)
2. Optimize database queries
3. Enable PHP caching
4. Check network bandwidth

### Error Logs
- **Apache Error Log**: `/var/log/apache2/error.log`
- **PHP Error Log**: Configured in php.ini
- **MySQL Error Log**: `/var/log/mysql/error.log`

## 📈 Performance Optimization

### Database Optimization
```sql
-- Add indexes for better performance
CREATE INDEX idx_daily_performance_date ON daily_performance(date);
CREATE INDEX idx_alerts_status ON production_alerts(status);
CREATE INDEX idx_quality_measurements_date ON quality_measurements(measurement_time);
```

### Caching
```php
// Add to config.php for simple file caching
class SimpleCache {
    private $cache_dir = 'cache/';

    public function get($key) {
        $file = $this->cache_dir . md5($key) . '.cache';
        if (file_exists($file) && (time() - filemtime($file)) < 300) {
            return unserialize(file_get_contents($file));
        }
        return false;
    }

    public function set($key, $data) {
        if (!is_dir($this->cache_dir)) {
            mkdir($this->cache_dir, 0755, true);
        }
        $file = $this->cache_dir . md5($key) . '.cache';
        file_put_contents($file, serialize($data));
    }
}
```

## 📋 Quick Reference

### Important Files
- `config.php` - Database configuration
- `enhanced_dashboard_offline.php` - Main dashboard
- `mobile_floor_manager_offline.php` - Mobile interface
- `setup_database.php` - Database initialization
- `assets.php` - Local resource management

### Default Login
- **Username**: admin
- **Password**: admin123

### Key URLs
- **Desktop**: `http://YOUR_IP/enhanced_dashboard_offline.php`
- **Mobile**: `http://YOUR_IP/mobile_floor_manager_offline.php`
- **Database Setup**: `http://YOUR_IP/setup_database.php`

## 🎯 Success Tips

1. **Start Small**: Begin with basic production tracking
2. **Train Users**: Ensure all operators know how to use the system
3. **Regular Updates**: Keep data current for accurate analytics
4. **Mobile First**: Use mobile interface for floor operations
5. **Monitor Alerts**: Respond to system alerts promptly

## 📞 Support

For technical assistance:
1. Check error logs first
2. Verify basic connectivity
3. Test with a simple PHP file
4. Review this troubleshooting guide

---

**System Version**: 1.0.0
**Last Updated**: <?= date('Y-m-d') ?>
**Compatibility**: PHP 7.4+, MySQL 5.7+