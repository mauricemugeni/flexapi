<?php

class Login extends Database {

    public function loginUser($username, $password) {
        $sql = "SELECT * FROM User WHERE userID=:username AND password=:password";
        $stmt = $this->prepareQuery($sql);
        $stmt->bindValue("password", $password);
        $stmt->bindValue("username", $username);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($data) == 0)
            return false;
        $data = $data[0];
        return $data;
    }

    public function getApiKey($user_id) {
        $sql = "SELECT userType FROM User WHERE userID=:user_id";
        $stmt = $this->prepareQuery($sql);
        $stmt->bindValue("user_id", $user_id);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return isset($data[0]['userType']) ? "{$data[0]['userType']}" : null;
    }

    public function updatePass($password, $username) {
        $sql = "UPDATE User SET password=:password WHERE userID=:username";
        $stmt = $this->prepareQuery($sql);
        $stmt->bindValue("password", $password);
        $stmt->bindValue("username", $username);
        return $stmt->execute();
    }

}
