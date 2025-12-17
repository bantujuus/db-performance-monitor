<?php
/**
 * Populate Test Data - FIXED VERSION
 * Cleans existing data before creating new sample databases
 */

require_once 'config.php';

echo "<h1>Populating Test Data...</h1>";
echo "<pre>";

try {
    $conn = getTargetConnection();
    
    // First, clean up any existing test databases
    echo "🧹 Cleaning up old test databases...\n";
    $conn->exec("DROP DATABASE IF EXISTS school_management");
    $conn->exec("DROP DATABASE IF EXISTS inventory_system");
    $conn->exec("DROP DATABASE IF EXISTS customer_portal");
    echo "✓ Old databases removed\n\n";
    
    // Create sample databases
    echo "Creating sample databases...\n";
    
    $databases = [
        'school_management' => 2000,  // Reduced for faster creation
        'inventory_system' => 1500,
        'customer_portal' => 1000,
    ];
    
    foreach ($databases as $dbName => $recordCount) {
        echo "\n=== Creating {$dbName} ===\n";
        
        // Create database
        $conn->exec("CREATE DATABASE {$dbName}");
        $conn->exec("USE {$dbName}");
        
        echo "Database created!\n";
        
        // Create sample tables based on database type
        if ($dbName === 'school_management') {
            // Students table
            $conn->exec("
                CREATE TABLE students (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    student_id VARCHAR(20) UNIQUE,
                    first_name VARCHAR(50),
                    last_name VARCHAR(50),
                    email VARCHAR(100),
                    phone VARCHAR(20),
                    date_of_birth DATE,
                    enrollment_date DATE,
                    grade_level VARCHAR(10),
                    gpa DECIMAL(3,2),
                    address TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_student_id (student_id),
                    INDEX idx_email (email)
                ) ENGINE=InnoDB
            ");
            
            echo "Creating {$recordCount} student records...\n";
            
            // Insert sample data in batches
            $batchSize = 100;
            for ($i = 0; $i < $recordCount; $i += $batchSize) {
                $values = [];
                for ($j = 0; $j < $batchSize && ($i + $j) < $recordCount; $j++) {
                    $num = $i + $j + 1;
                    $values[] = "(
                        'STU" . str_pad($num, 6, '0', STR_PAD_LEFT) . "',
                        'FirstName{$num}',
                        'LastName{$num}',
                        'student{$num}@school.edu',
                        '555-" . str_pad($num, 7, '0', STR_PAD_LEFT) . "',
                        DATE_SUB(CURDATE(), INTERVAL " . rand(15, 25) . " YEAR),
                        DATE_SUB(CURDATE(), INTERVAL " . rand(1, 4) . " YEAR),
                        'Grade " . rand(9, 12) . "',
                        " . (rand(200, 400) / 100) . ",
                        '123 Main St, City, State " . str_pad($num, 5, '0', STR_PAD_LEFT) . "'
                    )";
                }
                
                $conn->exec("
                    INSERT INTO students (student_id, first_name, last_name, email, phone, 
                                        date_of_birth, enrollment_date, grade_level, gpa, address)
                    VALUES " . implode(',', $values)
                );
                
                if (($i + $batchSize) % 500 == 0) {
                    echo "  ✓ Inserted " . min($i + $batchSize, $recordCount) . " / {$recordCount} records\n";
                    flush();
                }
            }
            
            // Create courses table
            $conn->exec("
                CREATE TABLE courses (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    course_code VARCHAR(20) UNIQUE,
                    course_name VARCHAR(100),
                    credits INT,
                    department VARCHAR(50),
                    description TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB
            ");
            
            $conn->exec("
                INSERT INTO courses (course_code, course_name, credits, department, description)
                VALUES 
                ('MATH101', 'Algebra I', 3, 'Mathematics', 'Introduction to algebraic concepts'),
                ('ENG101', 'English Literature', 3, 'English', 'Study of classic literature'),
                ('SCI101', 'Biology', 4, 'Science', 'Introduction to biological sciences'),
                ('HIST101', 'World History', 3, 'History', 'Overview of world historical events'),
                ('CS101', 'Computer Science', 3, 'Technology', 'Introduction to programming'),
                ('MATH201', 'Calculus', 4, 'Mathematics', 'Advanced calculus concepts'),
                ('PHY101', 'Physics', 4, 'Science', 'Introduction to physics'),
                ('CHEM101', 'Chemistry', 4, 'Science', 'Basic chemistry principles'),
                ('ART101', 'Art History', 2, 'Arts', 'Survey of art through ages'),
                ('MUS101', 'Music Theory', 2, 'Arts', 'Fundamentals of music')
            ");
            
            // Create enrollments table
            $conn->exec("
                CREATE TABLE enrollments (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    student_id VARCHAR(20),
                    course_code VARCHAR(20),
                    semester VARCHAR(20),
                    grade VARCHAR(2),
                    enrollment_date DATE,
                    INDEX idx_student (student_id),
                    INDEX idx_course (course_code)
                ) ENGINE=InnoDB
            ");
            
            echo "  ✓ School management system created!\n";
            
        } elseif ($dbName === 'inventory_system') {
            // Products table
            $conn->exec("
                CREATE TABLE products (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    product_code VARCHAR(20) UNIQUE,
                    product_name VARCHAR(100),
                    category VARCHAR(50),
                    price DECIMAL(10,2),
                    stock_quantity INT,
                    supplier VARCHAR(100),
                    description TEXT,
                    last_restocked DATE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_product_code (product_code),
                    INDEX idx_category (category)
                ) ENGINE=InnoDB
            ");
            
            echo "Creating {$recordCount} product records...\n";
            
            $categories = ['Electronics', 'Furniture', 'Stationery', 'Books', 'Tools', 'Sports', 'Clothing'];
            $batchSize = 100;
            
            for ($i = 0; $i < $recordCount; $i += $batchSize) {
                $values = [];
                for ($j = 0; $j < $batchSize && ($i + $j) < $recordCount; $j++) {
                    $num = $i + $j + 1;
                    $category = $categories[array_rand($categories)];
                    $values[] = "(
                        'PRD" . str_pad($num, 6, '0', STR_PAD_LEFT) . "',
                        'Product {$num}',
                        '{$category}',
                        " . rand(10, 1000) . "." . rand(0, 99) . ",
                        " . rand(0, 500) . ",
                        'Supplier " . rand(1, 20) . "',
                        'Description for product {$num} in {$category} category',
                        DATE_SUB(CURDATE(), INTERVAL " . rand(1, 180) . " DAY)
                    )";
                }
                
                $conn->exec("
                    INSERT INTO products (product_code, product_name, category, price, 
                                        stock_quantity, supplier, description, last_restocked)
                    VALUES " . implode(',', $values)
                );
                
                if (($i + $batchSize) % 500 == 0) {
                    echo "  ✓ Inserted " . min($i + $batchSize, $recordCount) . " / {$recordCount} records\n";
                    flush();
                }
            }
            
            // Create orders table
            $conn->exec("
                CREATE TABLE orders (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    order_number VARCHAR(20) UNIQUE,
                    product_code VARCHAR(20),
                    quantity INT,
                    order_date DATE,
                    status ENUM('pending', 'processing', 'shipped', 'delivered'),
                    INDEX idx_order_number (order_number)
                ) ENGINE=InnoDB
            ");
            
            echo "  ✓ Inventory system created!\n";
            
        } elseif ($dbName === 'customer_portal') {
            // Customers table
            $conn->exec("
                CREATE TABLE customers (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    customer_id VARCHAR(20) UNIQUE,
                    first_name VARCHAR(50),
                    last_name VARCHAR(50),
                    email VARCHAR(100),
                    phone VARCHAR(20),
                    company VARCHAR(100),
                    address TEXT,
                    city VARCHAR(50),
                    state VARCHAR(50),
                    zip_code VARCHAR(10),
                    country VARCHAR(50),
                    registration_date DATE,
                    status ENUM('active', 'inactive', 'pending') DEFAULT 'active',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_customer_id (customer_id),
                    INDEX idx_email (email)
                ) ENGINE=InnoDB
            ");
            
            echo "Creating {$recordCount} customer records...\n";
            
            $batchSize = 100;
            for ($i = 0; $i < $recordCount; $i += $batchSize) {
                $values = [];
                for ($j = 0; $j < $batchSize && ($i + $j) < $recordCount; $j++) {
                    $num = $i + $j + 1;
                    $statuses = ['active', 'inactive', 'pending'];
                    $status = $statuses[array_rand($statuses)];
                    $values[] = "(
                        'CUST" . str_pad($num, 6, '0', STR_PAD_LEFT) . "',
                        'FirstName{$num}',
                        'LastName{$num}',
                        'customer{$num}@email.com',
                        '555-" . str_pad($num, 7, '0', STR_PAD_LEFT) . "',
                        'Company {$num}',
                        '456 Business Ave, Suite " . rand(100, 999) . "',
                        'City{$num}',
                        'State',
                        '" . str_pad(rand(10000, 99999), 5, '0', STR_PAD_LEFT) . "',
                        'USA',
                        DATE_SUB(CURDATE(), INTERVAL " . rand(1, 365) . " DAY),
                        '{$status}'
                    )";
                }
                
                $conn->exec("
                    INSERT INTO customers (customer_id, first_name, last_name, email, phone,
                                         company, address, city, state, zip_code, country,
                                         registration_date, status)
                    VALUES " . implode(',', $values)
                );
                
                if (($i + $batchSize) % 500 == 0) {
                    echo "  ✓ Inserted " . min($i + $batchSize, $recordCount) . " / {$recordCount} records\n";
                    flush();
                }
            }
            
            // Create support tickets table
            $conn->exec("
                CREATE TABLE support_tickets (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    ticket_id VARCHAR(20) UNIQUE,
                    customer_id VARCHAR(20),
                    subject VARCHAR(200),
                    status ENUM('open', 'in_progress', 'resolved', 'closed'),
                    priority ENUM('low', 'medium', 'high', 'urgent'),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_ticket_id (ticket_id),
                    INDEX idx_customer (customer_id)
                ) ENGINE=InnoDB
            ");
            
            echo "  ✓ Customer portal created!\n";
        }
    }
    
    // Switch back to mysql database
    $conn->exec("USE mysql");
    
    echo "\n\n";
    echo "========================================\n";
    echo "✅ TEST DATA CREATED SUCCESSFULLY!\n";
    echo "========================================\n\n";
    
    // Show summary
    $stmt = $conn->query("
        SELECT 
            table_schema as 'Database',
            COUNT(*) as 'Tables',
            ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as 'Size_MB',
            FORMAT(SUM(table_rows), 0) as 'Total_Rows'
        FROM information_schema.TABLES
        WHERE table_schema IN ('school_management', 'inventory_system', 'customer_portal')
        GROUP BY table_schema
        ORDER BY SUM(data_length + index_length) DESC
    ");
    
    echo "📊 Database Summary:\n";
    echo str_repeat('=', 80) . "\n";
    echo str_pad('Database', 25) . str_pad('Tables', 10) . str_pad('Size (MB)', 15) . str_pad('Records', 15) . "\n";
    echo str_repeat('-', 80) . "\n";
    
    $totalSize = 0;
    $totalRecords = 0;
    
    $results = $stmt->fetchAll();
    foreach ($results as $row) {
        echo str_pad($row['Database'], 25) . 
             str_pad($row['Tables'], 10) . 
             str_pad($row['Size_MB'], 15) . 
             str_pad($row['Total_Rows'], 15) . "\n";
        $totalSize += $row['Size_MB'];
        $totalRecords += (int)str_replace(',', '', $row['Total_Rows']);
    }
    
    echo str_repeat('-', 80) . "\n";
    echo str_pad('TOTAL', 25) . 
         str_pad(count($results) . ' DBs', 10) . 
         str_pad(round($totalSize, 2), 15) . 
         str_pad(number_format($totalRecords), 15) . "\n";
    echo str_repeat('=', 80) . "\n\n";
    
    echo "🎉 Success! Your monitoring system now has realistic data!\n\n";
    echo "Next steps:\n";
    echo "1. View metrics: <a href='collect_metrics.php'>collect_metrics.php</a>\n";
    echo "2. Go to dashboard: <a href='http://localhost:3000'>http://localhost:3000</a>\n";
    echo "3. Test alerts: <a href='quick_test.php'>quick_test.php</a>\n\n";
    
    echo "Storage usage should now show around " . round($totalSize, 1) . " MB!\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString();
}

echo "</pre>";
?>

<style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
    h1 { color: #2563eb; }
    pre { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    a { color: #2563eb; text-decoration: none; padding: 8px 16px; background: #e0f2fe; 
        border-radius: 4px; margin: 0 5px; display: inline-block; }
    a:hover { background: #bae6fd; }
</style>