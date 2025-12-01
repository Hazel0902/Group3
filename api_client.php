<?php
class APIClient {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function getModuleEndpoints() {
        $sql = "SELECT * FROM module_endpoints";
        $result = $this->db->query($sql);
        
        $endpoints = array();
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $endpoints[] = $row;
            }
        }
        
        return $endpoints;
    }
    
    public function callAPI($url, $method = 'GET', $data = null) {
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        if ($method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            }
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            return array(
                'success' => false,
                'error' => $error
            );
        }
        
        return array(
            'success' => true,
            'data' => json_decode($response, true),
            'http_code' => $httpCode
        );
    }
    
    public function logAPICall($module, $endpoint, $method, $requestData, $responseCode, $responseData) {
        $module = $this->db->escape($module);
        $endpoint = $this->db->escape($endpoint);
        $method = $this->db->escape($method);
        $requestData = $this->db->escape(json_encode($requestData));
        $responseData = $this->db->escape(json_encode($responseData));
        
        $sql = "INSERT INTO api_call_logs (module_name, endpoint, request_method, request_data, response_code, response_data) 
                VALUES ('$module', '$endpoint', '$method', '$requestData', $responseCode, '$responseData')";
        
        $this->db->query($sql);
    }
    
    public function fetchAllModuleData() {
        $endpoints = $this->getModuleEndpoints();
        $allData = array();
        
        foreach ($endpoints as $endpoint) {
            $response = $this->callAPI($endpoint['endpoint_url'], $endpoint['request_method']);
            
            $this->logAPICall(
                $endpoint['module_name'],
                $endpoint['endpoint_url'],
                $endpoint['request_method'],
                null,
                $response['http_code'],
                $response['data']
            );
            
            if ($response['success']) {
                $allData[$endpoint['module_name']] = $response['data'];
            } else {
                $allData[$endpoint['module_name']] = array('error' => $response['error']);
            }
        }
        
        return $allData;
    }
}
?>