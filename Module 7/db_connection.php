<?php
class DatabaseConnection {
    private $host = 'localhost';
    private $username = 'root';
    private $password = '';
    private $database = 'module7_bi';
    private $connection;
    
    public function __construct() {
        $this->connection = new mysqli($this->host, $this->username, $this->password, $this->database);
        
        if ($this->connection->connect_error) {
            die("Connection failed: " . $this->connection->connect_error);
        }
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function query($sql) {
        return $this->connection->query($sql);
    }
    
    public function escape($string) {
        return $this->connection->real_escape_string($string);
    }
    
    public function close() {
        $this->connection->close();
    }
}
?>
