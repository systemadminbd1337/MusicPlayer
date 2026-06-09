# Backlink Management System

A PHP-MySQL web application for managing backlink purchases and automation, featuring a user-friendly interface (`link-depo.php`), automated backlink processing (`cron/auto_backlink_worker.php`), verification (`cron/verify_links.php`), and an admin dashboard (`admin/auto_jobs.php`). Customers can bulk-add links, which are automatically processed via external site APIs and monitored for success.

## Table of Contents
- [Prerequisites](#prerequisites)
- [Directory Structure](#directory-structure)
- [Setup Instructions](#setup-instructions)
  - [1. Server Setup](#1-server-setup)
  - [2. Database Configuration](#2-database-configuration)
  - [3. File Configuration](#3-file-configuration)
  - [4. Cron Job Setup](#4-cron-job-setup)
- [Usage](#usage)
  - [Customer Usage](#customer-usage)
  - [Admin Usage](#admin-usage)
- [Testing](#testing)
- [Troubleshooting](#troubleshooting)
- [Customization for Production](#customization-for-production)
- [License](#license)

## Prerequisites
- **Linux Server**: Ubuntu 20.04/22.04 LTS recommended.
- **Web Server**: Apache2 with `mod_rewrite` enabled.
- **PHP**: Version 7.4 or 8.1 with extensions:
  - `php-mysql`
  - `php-curl`
  - `php-json`
  - `php-mbstring`
- **Database**: MariaDB 10.x or MySQL 8.x.
- **Cron**: For scheduling automation tasks.
- **Optional**: Proxy list for API requests to avoid rate limits.

## Directory Structure
```
/var/www/html/
├── link-depo.php          # User interface for browsing and purchasing links
├── db.php                 # Database connection
├── footer.php             # Footer include
├── inc/
│   └── auto_functions.php # Automation functions (process_order, verify_order)
├── cron/
│   ├── auto_backlink_worker.php # Processes pending orders
│   └── verify_links.php        # Verifies backlinks
├── admin/
│   ├── _bootstrap.php     # Session and auth setup
│   └── auto_jobs.php      # Admin dashboard for monitoring jobs
```

## Setup Instructions

### 1. Server Setup
1. **Install Dependencies** (on Ubuntu):
   ```bash
   sudo apt update
   sudo apt install apache2 mariadb-server php libapache2-mod-php php-mysql php-curl php-json php-mbstring
   sudo systemctl enable apache2 mariadb
   sudo systemctl start apache2 mariadb
   ```

2. **Set Permissions**:
   - Ensure Apache can read/write files:
     ```bash
     sudo chown -R www-data:www-data /var/www/html
     sudo chmod -R 755 /var/www/html
     ```

3. **Upload Files**:
   - Copy the project files to `/var/www/html/`:
     ```bash
     sudo cp -r /path/to/your/project/* /var/www/html/
     ```
   - Ensure directory structure matches above.

### 2. Database Configuration
1. **Create Database**:
   ```bash
   mysql -u root -p
   ```
   ```sql
   CREATE DATABASE hacklink_panel;
   GRANT ALL PRIVILEGES ON hacklink_panel.* TO 'hacklink_user'@'localhost' IDENTIFIED BY 'your_secure_password';
   FLUSH PRIVILEGES;
   EXIT;
   ```

2. **Import Schema**:
   - Create tables (`k_users`, `k_linkdb`, `k_orders`, `k_job_logs`):
     ```sql
     USE hacklink_panel;

     CREATE TABLE k_users (
       id INT AUTO_INCREMENT PRIMARY KEY,
       username VARCHAR(255) NOT NULL,
       kredi INT DEFAULT 0
     );

     CREATE TABLE k_linkdb (
       id INT AUTO_INCREMENT PRIMARY KEY,
       domain VARCHAR(255) NOT NULL,
       da INT,
       pa INT,
       type VARCHAR(50),
       price INT,
       status VARCHAR(20),
       api_endpoint VARCHAR(255) DEFAULT NULL
     );

     CREATE TABLE k_orders (
       id INT AUTO_INCREMENT PRIMARY KEY,
       uid INT,
       lid INT,
       target_url VARCHAR(255),
       anchor VARCHAR(255),
       duration_months INT,
       created_at DATETIME,
       tarih DATETIME,
       status VARCHAR(20) DEFAULT 'pending',
       attempts INT DEFAULT 0,
       notes TEXT,
       processed_at DATETIME,
       backlink_url VARCHAR(255),
       url_checks TEXT
     );

     CREATE TABLE k_job_logs (
       id INT AUTO_INCREMENT PRIMARY KEY,
       order_id INT,
       timestamp DATETIME,
       action VARCHAR(50),
       message TEXT
     );
     ```

3. **Add Test Data**:
   - Add a test user and link:
     ```sql
     INSERT INTO k_users (username, kredi) VALUES ('testuser', 10);
     INSERT INTO k_linkdb (domain, da, pa, type, price, status, api_endpoint)
     VALUES ('example.com', 50, 40, 'dofollow', 1, 'active', 'https://example.com/api/add-backlink');
     ```

4. **Configure `db.php`**:
   - Edit `/var/www/html/db.php`:
     ```php
     <?php
     // db.php
     class DB {
       private $pdo;
       public $rows_affected = 0;

       function __construct($host = 'localhost', $user = 'hacklink_user', $pass = 'your_secure_password', $name = 'hacklink_panel') {
         $this->pdo = new PDO("mysql:host=$host;dbname=$name", $user, $pass);
         $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
       }

       function get_var($sql) {
         $stmt = $this->pdo->query($sql);
         return $stmt->fetchColumn();
       }

       function get_results($sql) {
         $stmt = $this->pdo->query($sql);
         return $stmt->fetchAll(PDO::FETCH_OBJ);
       }

       function get_row($sql) {
         $stmt = $this->pdo->query($sql);
         return $stmt->fetch(PDO::FETCH_OBJ);
       }

       function query($sql) {
         $this->rows_affected = $this->pdo->exec($sql);
         return $this->rows_affected;
       }

       function escape($v) {
         return $this->pdo->quote($v);
       }
     }

     function dbx($v) {
       global $db;
       return $db->escape($v);
     }

     $db = new DB();
     ?>
     ```

### 3. File Configuration
1. **Verify Files**:
   - Ensure all files are in `/var/www/html/` as per the directory structure.
   - Create `footer.php` if missing:
     ```php
     <?php
     // /var/www/html/footer.php
     ?>
     ```

2. **Configure `_bootstrap.php`**:
   - Edit `/var/www/html/admin/_bootstrap.php`:
     ```php
     <?php
     // admin/_bootstrap.php
     session_start();
     function redirect($url) {
       header("Location: $url");
       exit();
     }
     ?>
     ```

3. **Update `auto_functions.php`**:
   - Ensure `/var/www/html/inc/auto_functions.php` matches the latest version (provided previously).
   - Set `$adminEmail` to a valid email for notifications.
   - Add API keys or proxies if required:
     ```php
     $apiAuthKey = 'your_global_api_key'; // Optional
     $proxyList = ['http://user:pass@proxy:port']; // Optional
     ```

4. **Test API Endpoint**:
   - Replace `https://example.com/api/add-backlink` in `k_linkdb.api_endpoint` with real site APIs.
   - For local testing, use:
     ```sql
     INSERT INTO k_linkdb (domain, da, pa, type, price, status, api_endpoint)
     VALUES ('localhost', 50, 40, 'dofollow', 1, 'active', 'http://localhost/add_backlink.php');
     ```
   - Create `/var/www/html/add_backlink.php` for testing:
     ```php
     <?php
     // add_backlink.php
     header('Content-Type: application/json; charset=utf-8');
     if ($_SERVER['REQUEST_METHOD'] === 'POST') {
       $url = $_POST['url'] ?? '';
       $anchor = $_POST['anchor'] ?? '';
       if (empty($url) || empty($anchor)) {
         http_response_code(400);
         echo json_encode(['success' => false, 'error' => 'Missing url or anchor']);
         exit;
       }
       $backlink_url = "https://example.com/mock-post/" . rand(1000, 9999);
       echo json_encode([
         'success' => true,
         'backlink_url' => $backlink_url,
         'message' => "Backlink created for $url with anchor '$anchor'"
       ]);
     } else {
       http_response_code(405);
       echo json_encode(['success' => false, 'error' => 'Method not allowed']);
     }
     ?>
     ```

### 4. Cron Job Setup
1. **Edit Crontab**:
   ```bash
   crontab -e
   ```
   - Add:
     ```bash
     */5 * * * * php /var/www/html/cron/auto_backlink_worker.php
     0 * * * * php /var/www/html/cron/verify_links.php
     ```
   - This runs `auto_backlink_worker.php` every 5 minutes and `verify_links.php` hourly.

2. **Verify Cron**:
   - Check logs:
     ```bash
     grep CRON /var/log/syslog
     ```

## Usage

### Customer Usage
1. **Access Link Depot**:
   - Open `http://your-server-ip/link-depo.php` in a browser.
   - Log in with a user account (e.g., `testuser` with credits).
   - Browse links in the DataTable, select multiple links, and click "Bulk Add."
   - Enter target URL, anchor text, and duration, then submit.
   - Credits are deducted, and orders are created in `k_orders` with `status='pending'`.

2. **Automation**:
   - The cron job (`auto_backlink_worker.php`) processes orders, sending requests to the API endpoints in `k_linkdb.api_endpoint`.
   - Orders update to `status='processed'` with a `backlink_url` or `failed` after max retries.
   - `verify_links.php` checks if backlinks are live, updating `k_orders.url_checks`.

### Admin Usage
1. **Monitor Jobs**:
   - Open `http://your-server-ip/admin/auto_jobs.php` after logging in.
   - View stats (pending, processing, processed, failed orders) and a table of non-processed orders.
   - Use "Retry" to reset failed orders to `pending` or "Force Process" to re-run `process_order`.

## Testing
1. **Local Testing**:
   - Add a test link with `domain='localhost'` and `api_endpoint='http://localhost/add_backlink.php'`.
   - Run:
     ```bash
     php /var/www/html/cron/auto_backlink_worker.php
     ```
   - Check `k_orders` and `k_job_logs`:
     ```sql
     SELECT id, status, backlink_url, notes FROM k_orders;
     SELECT * FROM k_job_logs;
     ```

2. **Real Site Testing**:
   - Update `k_linkdb` with a real site’s `domain` and `api_endpoint`.
   - Test the API:
     ```bash
     curl -X POST https://example.com/api/add-backlink -d "url=https://your-site.com&anchor=Test"
     ```
   - Run the cron job and verify `k_orders`.

3. **Verification**:
   - Create a test page for verification:
     ```php
     <?php
     // /var/www/html/test-post.php
     echo '<a href="https://your-site.com">Test Link</a>';
     ?>
     ```
   - Update `k_orders.backlink_url`:
     ```sql
     UPDATE k_orders SET backlink_url = 'http://your-server-ip/test-post.php' WHERE id = 1;
     ```
   - Run:
     ```bash
     php /var/www/html/cron/verify_links.php
     ```

## Troubleshooting
- **Orders Not Processing**:
  - Check cron logs: `grep CRON /var/log/syslog`.
  - Test API endpoint: `curl -X POST https://example.com/api/add-backlink -d "url=https://your-site.com&anchor=Test"`.
  - Verify `k_orders.notes` and `k_job_logs`.

- **Database Errors**:
  - Ensure `db.php` credentials match the database user.
  - Check table structure: `DESCRIBE k_orders;`.

- **API Issues**:
  - Add proxies to `$proxyList` in `auto_functions.php` if rate-limited.
  - Update `process_order` for specific API parameters (e.g., `api_key`).

- **Admin Panel Errors**:
  - Ensure `_bootstrap.php` starts the session and defines `redirect`.
  - Verify `footer.php` exists.

## Customization for Production
1. **Real API Integration**:
   - Update `k_linkdb.api_endpoint` with actual API URLs.
   - Modify `process_order` in `auto_functions.php`:
     ```php
     $data = [
       'url' => $order->target_url,
       'anchor' => $order->anchor,
       'api_key' => 'your_api_key',
       'token' => 'your_token'
     ];
     ```
   - Use JSON if required:
     ```php
     $data = json_encode($data);
     curl_setopt_array($ch, [
       CURLOPT_POSTFIELDS => $data,
       CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json']
     ]);
     ```

2. **Security**:
   - Secure `db.php` credentials.
   - Enable HTTPS on Apache.
   - Use prepared statements for all queries.

3. **Scalability**:
   - Increase `$maxRetries` or adjust `$retryBackoffMinutes` in `auto_functions.php`.
   - Add more proxies to `$proxyList`.

## License
This project is unlicensed. Modify and distribute as needed.