<?php
// Simple migration runner - executes SQL files in the migrations/ directory in alphabetical order.
$host = getenv('DB_HOST') ?: 'localhost';
$db   = getenv('DB_NAME') ?: 'event_system';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$charset = getenv('DB_CHARSET') ?: 'utf8mb4';
$port = getenv('DB_PORT') ?: '3306';

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $dsn = "mysql:host={$host};port={$port};charset={$charset}";
    $pdo = new PDO($dsn, $user, $pass, $options);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$db}`");
} catch (PDOException $e) {
    exit('Database connection failed: ' . $e->getMessage());
}

$migrationsDir = __DIR__ . '/migrations';
$files = array_values(array_filter(scandir($migrationsDir), function($f) use ($migrationsDir) {
    return is_file($migrationsDir . DIRECTORY_SEPARATOR . $f) && pathinfo($f, PATHINFO_EXTENSION) === 'sql';
}));

if (empty($files)) {
    echo "No migrations found in migrations/\n";
    exit(0);
}

foreach ($files as $file) {
    $path = $migrationsDir . DIRECTORY_SEPARATOR . $file;
    echo "Running migration: $file\n";
    $sql = file_get_contents($path);
    if ($sql === false) {
        echo "Failed to read $path\n";
        continue;
    }

    $statements = array_filter(array_map('trim', preg_split('/;\s*\n/', $sql)));
    foreach ($statements as $statement) {
        try {
            $pdo->exec($statement);
        } catch (PDOException $e) {
            $message = $e->getMessage();
            if (preg_match('/(Duplicate column name|Duplicate key name|already exists)/i', $message)) {
                echo "  Skipped existing object: " . trim(substr($statement, 0, 80)) . "...\n";
                continue;
            }
            echo "ERROR running statement: $message\n";
            echo "SQL: " . substr($statement, 0, 200) . "...\n";
            break 2;
        }
    }
    echo "OK: $file\n";
}

echo "Migrations finished.\n";
?>
