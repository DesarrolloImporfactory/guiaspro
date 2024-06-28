<?php
class Conexion
{
    private $connection;
    private $marketplace;
    public function __construct()
    {
        $pdo = "mysql:host=" . HOST . ";dbname=" . DB . ";charset=" . CHARSET;
        try {
            $this->connection = new PDO($pdo, USER, PASSWORD);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {

            echo "Error: " . $e->getMessage();
        }
    }
    public function connect()
    {
        return $this->connection;
    }

    public function close()
    {
        $this->connection = null;
    }
}
