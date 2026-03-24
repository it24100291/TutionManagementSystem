<?php
function getDB() {
    static $pdo = null;
    
    if ($pdo === null) {
        $host = env('DB_HOST', 'localhost');
        $dbname = env('DB_NAME', 'UserManagement');
        $user = env('DB_USER', 'root');
        $pass = env('DB_PASS');

        if ($pass === null) {
            throw new RuntimeException('Database password is not configured');
        }
        
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        $pdo = new PDO($dsn, $user, $pass, $options);
    }
    
    return $pdo;
}
