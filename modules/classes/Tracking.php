<?php

class Tracking extends Database {

    public function verifyMeter($meter) {
        $data = array("meterno" => $meter, "request" => "meter");
        $data_string = json_encode($data);

        $response = $this->sendHttpRequest($data_string);
        return (String) $this->getCustomerName($response);
    }

    private function sendHttpRequest($data_string) {
        $ch = curl_init('http://212.22.188.77:8080/WebRequest/RequestServlet');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data_string))
        );
        $response = curl_exec($ch);
        return $response;
    }

    public function vendTokens($meter, $amount, $username, $customer_name, $created_at) {
        if (!$this->getObjectPermission("1", $username)) {
            return false;
        }
        $amount1 = (int) $amount;
        if ($amount1 < 50) {
            return "4";
        }
        $float = $this->fetchFloat($username);
        if ($amount1 > $float) {
            return "2";
        }

        $timelastvend = $this->checkLastMeterVend($meter);
        if (!$timelastvend) {
            return "3";
        }

        $data = array("meterno" => $meter, "amount" => ($amount1 * 100), "request" => "vend");
        $data_string = json_encode($data);
        $response = $this->sendHttpRequest($data_string);
        $repp = $this->meterDetails($response);
        $this->saveResponseString($repp['ref'], $response);
        $this->manageTransaction($repp, $meter, $amount1, $username, $customer_name, $created_at);
        if ($repp['res_code'] != "elec000") {
            return $repp;
        }
        return $repp;
    }

    private function manageTransaction($response, $meter, $amount, $username, $customer_name, $date) {
        $comission_account = $this->checkCommissionAccount($username);

        $revenue_id = $this->checkVendRevenueId($username);
        $account_idy = $this->getAcccountIdByUsername($username);
        $vendor_id = $this->getVendIdById($this->getAuthid($username));
        $coom_main = $this->checkCommissionMain($vendor_id);
        $percentage = "";
        if ($response['res_code'] == "elec000") {
            if ($revenue_id == "2") {
                $percentage = "1.7";
                if ($coom_main) {
                    $acc_iddd = $this->getAccountIdById($coom_main);
                    $this->saveVendorCommission($percentage, $amount, $acc_iddd, $username, $customer_name, $date);
                } else {
                    $this->saveVendorCommission($percentage, $amount, $account_idy, $username, $customer_name, $date);
                }
            } else if ($revenue_id == "3") {
                $percentage = "1.2";
                $user_id = $this->getAuthid($username);
                $this->saveVendorCommission($percentage, $amount, $account_idy, $username, $customer_name, $date);
                $mainVendor = $this->getMainVendor($user_id);
                $percentage_vendor = "0.5";
                $this->saveVendorCommission($percentage_vendor, $amount, $mainVendor, $username, $customer_name, $date);
            }
            $this->saveAccountHistory($amount, $account_idy, $username, $customer_name, $date);
        }
        $this->saveTransaction($percentage, $response, $meter, $amount, $account_idy, $customer_name, $username, $date);
        return true;
    }

    public function saveResponseString($ref, $response) {
        $sql = "INSERT INTO response_back (ref, response_string) VALUES (:ref, :response_string)";
        $stmt = $this->prepareQuery($sql);
        $stmt->bindValue("ref", $ref);
        $stmt->bindValue("response_string", $response);
        return $stmt->execute();
    }

    public function meterDetails($response) {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response);
        $ref = null;
        $token = null;
        $rctNum = null;
        $units = null;
        $time = null;
        $res_code = null;
        $res = null;
        $monthly = null;
        $total_tax = null;
        $fuel_index = null;
        $forex_charge = null;
        $erc_charge = null;
        $rep_charge = null;
        $inflation_adjustment = null;
        $description = null;
        $tax = null;
        $amt = null;

        if ($xml === false) {
            echo "Failed loading XML: ";
            foreach (libxml_get_errors() as $error) {
                return false;
            }
        } else {

            $time = (String) $xml['time'];
            foreach ($xml AS $value) {
                $res_code = (String) $value[0]->vendRes->res['code'];
                $res = (String) $value[0]->vendRes->res;
                $ref = (String) $value[0]->vendRes->ref;
                foreach ($value AS $key) {
                    $monthly = (String) $key[0]->fixed[0]['amt'] / 100;
                    $total_tax = (String) $key[0]->fixed[1]['amt'] / 100;
                    $fuel_index = (String) $key[0]->fixed[2]['amt'] / 100;
                    $forex_charge = (String) $key[0]->fixed[3]['amt'] / 100;
                    $erc_charge = (String) $key[0]->fixed[4]['amt'] / 100;
                    $rep_charge = (String) $key[0]->fixed[5]['amt'] / 100;
                    $inflation_adjustment = (String) $key[0]->fixed[6]['amt'] / 100;
                    $units = (String) $key[0]->stdToken['units'];
                    $tax = (String) $key[0]->stdToken['tax'];
                    $rctNum = (String) $key[0]->stdToken['rctNum'];
                    $description = (String) $key[0]->stdToken['desc'];
                    $token = (String) $key[0]->stdToken;
                    $amt = (String) $key[0]->stdToken['amt'] / 100;
                }
            }
        }
        return array("inflation_adjustment" => $inflation_adjustment, "rep_charge" => $rep_charge, "erc_charge" => $erc_charge, "forex_charge" => $forex_charge, "fuel_index" => $fuel_index, "total_tax" => $total_tax, "monthly" => $monthly, "res" => $res, "ref" => $ref, "res_code" => $res_code, "token" => $token, "rctNum" => $rctNum, "description" => $description, "units" => $units, "time" => $time, "tax" => $tax, "amt" => $amt);
    }

    private function checkLastMeterVend($meter) {
        $sql = "SELECT createdat FROM vend_transaction WHERE meterno=:meterno AND complete='1' ORDER BY createdat DESC LIMIT 1";
        $stmt = $this->prepareQuery($sql);
        $stmt->bindValue("meterno", $meter);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($data) == 0)
            return true;
        $dateString = $data[0]['createdat'];
        $date = strtotime($dateString);
        $diff = time() - $date;
        $time_diff = $this->time_diff($diff);
        if ($time_diff < 10)
            return false;
        return true;
    }

    public function fetchFloat($username) {
        $sql = "SELECT balafter FROM vend_accounthistory WHERE account_id=:id ORDER BY createdat DESC LIMIT 1";
        $stmt = $this->prepareQuery($sql);
        $stmt->bindValue("id", $this->getAcccountIdByUsername($username));
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($data) == 0)
            return 0;
        return ($data[0]['balafter'] / 100);
    }

}
