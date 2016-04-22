<?php

require WPATH . "modules/classes/Login.php";
require WPATH . "modules/classes/Tracking.php";
$login = new Login();
$vend = new Tracking();
if (isset($_GET['request']) || isset($_POST['request'])) {
    $request = isset($_GET['request']) ? $_GET['request'] : $_POST['request'];
    if ($request == "login") {
        $username = isset($_GET['username']) ? $_GET['username'] : $_POST['username'];
        $password = isset($_GET['password']) ? $_GET['password'] : $_POST['password'];
        $response = array("status" => 0, "msg" => 0, "access_level" => 0);
        $req = $login->loginUser($username, $password);
        if ($req == true) {
            $response["status"] = 0;
            $response["msg"] = "success";
            $response["access_level"] = $login->getApiKey($req['userID']);
            echo json_encode($response);
        } else {
            $response["status"] = 1;
            $response["msg"] = "Wrong Username or Password";
            echo json_encode($response);
        }
    }
} else {
    $response = array("status" => 0, "msg" => 0);
    $response['status'] = 1;
    $response['msg'] = "Wrong request. Please contact info@flexcom.co.ke";
    echo json_encode($response);
}

