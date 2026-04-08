<?php

declare(strict_types=1);

namespace HotPlan\Database;

use PDO;
use PDOException;
use HotPlan\Config\ConfigLoader;

/**
 * Database Connection Manager
 * 
 * Manages database connections using PDO.
 */
class Connection
{
    private static ?Connection $instance = null;
    private ?PDO $pdo = null;
    private ConfigLoader $config;
    
    private function __construct(ConfigLoader $config)
    {
        $this->config = $config;
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance(?ConfigLoader $config = null): self
    {
        if (self::$instance === null) {
            self::$instance = new self($config ?? ConfigLoader::getInstance());
        }
        return self::$instance;
    }
    
    /**
     * Reset singleton (for testing)
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
    
    /**
     * Get PDO connection
     */
    public function getPdo(): PDO
    {
        if ($this->pdo === null) {
            $this->connect();
        }
        return $this->pdo;
    }
    
    /**
     * Establish database connection
     */
    private function connect(): void
    {
        $dbConfig = $this->config->get('database', []);
        $type = $dbConfig['type'] ?? 'sqlite';
        
        switch ($type) {
            case 'sqlite':
                $this->connectSqlite($dbConfig);
                break;
            case 'mysql':
                $this->connectMysql($dbConfig);
                break;
            case 'pgsql':
                $this->connectPgsql($dbConfig);
                break;
            default:
                throw new \InvalidArgumentException("Unsupported database type: {$type}");
        }
    }
    
    /**
     * Connect to SQLite
     */
    private function connectSqlite(array $config): void
    {
        $path = $config['path'] ?? 'data/hotplan.db';
        
        // Ensure directory exists
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $dsn = "sqlite:{$path}";
        
        $this->pdo = new PDO($dsn, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        
        // Enable foreign keys for SQLite
        $this->pdo->exec('PRAGMA foreign_keys = ON');
    }
    
    /**
     * Connect to MySQL
     */
    private function connectMysql(array $config): void
    {
        $host = $config['host'] ?? 'localhost';
        $port = $config['port'] ?? 3306;
        $database = $config['name'] ?? 'hotplan';
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';
        
        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
        
        $this->pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    
    /**
     * Connect to PostgreSQL
     */
    private function connectPgsql(array $config): void
    {
        $host = $config['host'] ?? 'localhost';
        $port = $config['port'] ?? 5432;
        $database = $config['name'] ?? 'hotplan';
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';
        
        $dsn = "pgsql:host={$host};port={$port};dbname={$database}";
        
        $this->pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    
    /**
     * Execute a query
     */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    /**
     * Fetch a single row
     */
    public function fetch(string $sql, array $params = []): ?array
    {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    /**
     * Fetch all rows
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Insert a row and return the last insert ID
     */
    public function insert(string $table, array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $this->query($sql, array_values($data));
        
        return (int) $this->getPdo()->lastInsertId();
    }
    
    /**
     * Update rows
     */
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set = implode(' = ?, ', array_keys($data)) . ' = ?';
        
        $sql = "UPDATE {$table} SET {$set} WHERE {$where}";
        $params = array_merge(array_values($data), $whereParams);
        
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * Delete rows
     */
    public function delete(string $table, string $where, array $params = []): int
    {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * Begin a transaction
     */
    public function beginTransaction(): bool
    {
        return $this->getPdo()->beginTransaction();
    }
    
    /**
     * Commit a transaction
     */
    public function commit(): bool
    {
        return $this->getPdo()->commit();
    }
    
    /**
     * Rollback a transaction
     */
    public function rollback(): bool
    {
        return $this->getPdo()->rollBack();
    }
    
    /**
     * Run database migrations
     */
    public function migrate(string $migrationsPath): void
    {
        $files = glob($migrationsPath . '/*.sql');
        sort($files);
        
        foreach ($files as $file) {
            $sql = file_get_contents($file);
            $this->getPdo()->exec($sql);
        }
    }
}
