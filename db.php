<?php
// Prevenir acesso direto
if (!defined('DTUNNEL_APP')) {
    header('HTTP/1.0 403 Forbidden');
    exit;
}

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $dbPath = __DIR__ . '/database/database.sqlite';
        try {
            $this->pdo = new PDO('sqlite:' . $dbPath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->pdo->exec('PRAGMA foreign_keys = ON;');
            $this->pdo->exec('PRAGMA journal_mode = WAL;');
            $this->createTables();
        } catch (PDOException $e) {
            die('Error al conectar ao banco de dados: ' . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }

    private function createTables() {
        $sql = "
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            email TEXT UNIQUE NOT NULL,
            token TEXT UNIQUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME,
            is_blocked INTEGER DEFAULT 0,
            app_text_version INTEGER DEFAULT 1,
            app_layout_version INTEGER DEFAULT 1,
            app_config_version INTEGER DEFAULT 1
        );

        CREATE TABLE IF NOT EXISTS cdn (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            url TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'ACTIVE',
            user_id INTEGER NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            color TEXT NOT NULL DEFAULT '#4A90D9',
            sorter INTEGER DEFAULT 0,
            status TEXT NOT NULL DEFAULT 'ACTIVE',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            user_id INTEGER NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS app_configs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            auth_password TEXT,
            auth_username TEXT,
            auth_v2ray_uuid TEXT,
            category_id INTEGER,
            config_openvpn TEXT,
            config_payload_payload TEXT,
            config_payload_sni TEXT,
            config_v2ray TEXT,
            description TEXT,
            dns_server_dns1 TEXT,
            dns_server_dns2 TEXT,
            icon TEXT NOT NULL DEFAULT 'DEFAULT',
            mode TEXT NOT NULL DEFAULT 'SSH_DIRECT',
            name TEXT NOT NULL,
            proxy_host TEXT,
            proxy_port INTEGER,
            server_host TEXT,
            server_port INTEGER,
            dnstt_key TEXT,
            dnstt_name_server TEXT,
            dnstt_server TEXT,
            hy_obfs TEXT,
            hy_insecure INTEGER DEFAULT 1,
            hy_port TEXT DEFAULT '13375',
            hy_up_mbps INTEGER DEFAULT 100,
            hy_down_mbps INTEGER DEFAULT 150,
            hy_version INTEGER DEFAULT 1,
            sorter INTEGER DEFAULT 0,
            status TEXT NOT NULL DEFAULT 'ACTIVE',
            tls_version TEXT,
            udp_ports TEXT DEFAULT '7300',
            url_check_user TEXT NOT NULL DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            user_id INTEGER NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS app_texts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            label TEXT NOT NULL,
            text TEXT NOT NULL,
            user_id INTEGER NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS app_layouts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            name TEXT NOT NULL DEFAULT 'Layout',
            is_active INTEGER DEFAULT 0,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS app_layout_storages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            label TEXT NOT NULL,
            name TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'ACTIVE',
            type TEXT NOT NULL,
            value TEXT,
            app_layout_id INTEGER NOT NULL,
            FOREIGN KEY (app_layout_id) REFERENCES app_layouts(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS notifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            message TEXT NOT NULL,
            type TEXT NOT NULL DEFAULT 'info',
            is_read INTEGER DEFAULT 0,
            user_id INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );
        ";
        $this->pdo->exec($sql);
    }
}

function db() {
    return Database::getInstance()->getConnection();
}

function generateToken() {
    return bin2hex(random_bytes(32));
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}
