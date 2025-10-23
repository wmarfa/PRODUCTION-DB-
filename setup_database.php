<?php
// setup_database.php
$host = 'localhost';
$username = 'root';
$password = '';

try {
    // Connect to MySQL without selecting a database
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS performance_tracking");
    $pdo->exec("USE performance_tracking");

    // Drop tables if they exist (in correct order due to foreign keys)
    $pdo->exec("DROP TABLE IF EXISTS packing_performance");
    $pdo->exec("DROP TABLE IF EXISTS assy_performance");
    $pdo->exec("DROP TABLE IF EXISTS daily_performance");
    $pdo->exec("DROP TABLE IF EXISTS products");

    // Create products table with all required columns
    $pdo->exec("CREATE TABLE products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_code VARCHAR(100) NOT NULL UNIQUE,
        circuit DECIMAL(10,4) NOT NULL,
        mhr DECIMAL(10,4) NOT NULL,
        qty_sh_pack INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // Create daily_performance table
    $pdo->exec("CREATE TABLE daily_performance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        date DATE NOT NULL,
        line_shift VARCHAR(50) NOT NULL,
        leader VARCHAR(50) NOT NULL,
        mp INT NOT NULL,
        absent INT DEFAULT 0,
        separated_mp INT DEFAULT 0,
        plan INT NOT NULL,
        no_ot_mp INT DEFAULT 0,
        ot_mp INT DEFAULT 0,
        ot_hours DECIMAL(5,2) DEFAULT 0,
        assy_wt DECIMAL(5,2) DEFAULT 0,
        qc INT DEFAULT 0,
        total_assy_output INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // Create assy_performance table
    $pdo->exec("CREATE TABLE assy_performance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        daily_performance_id INT,
        product_id INT,
        assy_output INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (daily_performance_id) REFERENCES daily_performance(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id)
    )");

    // Create packing_performance table
    $pdo->exec("CREATE TABLE packing_performance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        daily_performance_id INT,
        product_id INT,
        packing_output INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (daily_performance_id) REFERENCES daily_performance(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id)
    )");

    // Insert sample products
    $pdo->exec("INSERT INTO products (product_code, circuit, mhr, qty_sh_pack) VALUES
        ('91115-G9201', 250.0000, 4.2300, 10),
        ('91115-G9301', 260.0000, 5.0100, 10),
        ('91115-G9401', 270.0000, 4.5000, 12),
        ('91115-G9501', 280.0000, 4.8000, 8)
    ");

    // Insert sample performance data
    $pdo->exec("INSERT INTO daily_performance (date, line_shift, leader, mp, absent, separated_mp, plan, no_ot_mp, ot_mp, ot_hours, assy_wt, qc, total_assy_output) VALUES
        ('2024-01-15', 'S101 DS', 'GRACE', 50, 5, 1, 100, 45, 5, 2.0, 8.0, 2, 88),
        ('2024-01-15', 'S102 NS', 'JOHN', 45, 3, 0, 90, 40, 5, 1.5, 8.0, 2, 92),
        ('2024-01-16', 'S101 DS', 'GRACE', 48, 2, 0, 95, 43, 5, 2.0, 8.0, 2, 96),
        ('2024-01-16', 'S102 NS', 'JOHN', 46, 4, 1, 88, 41, 5, 1.5, 8.0, 2, 85)
    ");

    // Insert sample ASSY performance data
    $pdo->exec("INSERT INTO assy_performance (daily_performance_id, product_id, assy_output) VALUES
        (1, 1, 50),
        (1, 2, 38),
        (2, 1, 45),
        (2, 3, 47),
        (3, 2, 60),
        (3, 4, 36),
        (4, 1, 40),
        (4, 3, 45)
    ");

    echo "✅ Database setup completed successfully!<br>";
    echo "✅ Tables created with proper structure<br>";
    echo "✅ Sample products and performance data inserted<br>";
    echo "<div class='mt-3'>";
    echo "<a href='products.php' class='btn btn-success'>Manage Products</a> ";
    echo "<a href='view_data.php' class='btn btn-primary'>View Performance Data</a> ";
    echo "<a href='entry_form.php' class='btn btn-info'>Add New Data</a>";
    echo "</div>";

} catch(PDOException $e) {
    die("❌ Database setup failed: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="card">
            <div class="card-body text-center">
                <h2>Database Setup Complete</h2>
                <p class="text-success">Your database has been set up successfully!</p>
            </div>
        </div>
    </div>
</body>
</html>