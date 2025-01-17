<?php
class Query extends Conexion
{
    private $pdo, $connection, $sql;
    public function __construct()
    {
        $this->pdo = new Conexion();
        $this->connection = $this->pdo->connect();
    }

    public function select($sql)
    {
        $this->sql = $sql;
        $query = $this->connection->prepare($this->sql);
        $query->execute();
        $result = $query->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    public function insert($sql, $data)
    {
        $this->sql = $sql;
        $query = $this->connection->prepare($this->sql);
        $query->execute($data);
        $result = $query->rowCount();
        return $result;
    }

    public function bitacora($guia, $estado, $transportadora)
    {
        $ch = curl_init("https://new.imporsuitpro.com/bitacora/estados");
        curl_setopt($ch, CURLOPT_POST, 1);
        //formdata
        $data = [
            'guia' => $guia,
            'estado' => $estado,
            'transportadora' => $transportadora
        ];
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        echo $response;
    }

    public function update($sql, $data)
    {
        $this->sql = $sql;
        $query = $this->connection->prepare($this->sql);
        $query->execute($data);
        $result = $query->rowCount();
        return $result;
    }

    public function delete($sql)
    {
        $this->sql = $sql;
        $query = $this->connection->prepare($this->sql);
        $query->execute();
        $result = $query->rowCount();
        return $result;
    }

    public function close()
    {
        $this->pdo->close();
    }

    public function insertSimple($sql)
    {
        $this->sql = $sql;
        $query = $this->connection->prepare($this->sql);
        $query->execute();
        $result = $query->rowCount();
        return $result;
    }
}
