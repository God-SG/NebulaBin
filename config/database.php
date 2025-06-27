<?php
class Database {
    private $host = '185.113.141.250';
    private $db_name = 'alwaysfor_9i45jtgbdoo';
    private $username = 'alwaysfor';
    private $password = 'W$rteFyghdSFHW$RHFNWIOHWDOUGHUVBDOSO(/#RBFNcjhbDOUVBOGEFuh';
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host={$this->host};dbname={$this->db_name}",
                $this->username,
                $this->password
            );
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        return $this->conn;
    }
}
?>
