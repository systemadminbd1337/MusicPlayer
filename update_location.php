<?php
// update_location.php
session_start();
include "db.php";

if(empty($_SESSION['user'])) {
    die(json_encode(['success' => false, 'error' => 'Not logged in']));
}

$user_id = (int)$_SESSION['user']->id;

// Get real IP address
$ip = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
if ($ip === '::1') $ip = '127.0.0.1';

// Function to get location from IP
function getLocationFromIP($ip) {
    if ($ip === '127.0.0.1' || $ip === '::1') {
        return ['country' => 'Localhost', 'city' => 'Local', 'region' => 'Development', 'ip' => $ip];
    }
    
    $apis = [
        "http://ip-api.com/json/{$ip}?fields=status,message,country,city,regionName",
        "https://api.ipgeolocation.io/ipgeo?apiKey=demo&ip={$ip}",
    ];
    
    foreach($apis as $api_url) {
        try {
            $context = stream_context_create(['http' => ['timeout' => 3]]);
            $response = @file_get_contents($api_url, false, $context);
            
            if($response) {
                $data = json_decode($response, true);
                
                if(isset($data['country']) && $data['country']) {
                    return [
                        'country' => $data['country'],
                        'city' => $data['city'] ?? 'Unknown',
                        'region' => $data['regionName'] ?? 'Unknown',
                        'ip' => $ip
                    ];
                }
                
                if(isset($data['country_name']) && $data['country_name']) {
                    return [
                        'country' => $data['country_name'],
                        'city' => $data['city'] ?? 'Unknown',
                        'region' => $data['state_prov'] ?? 'Unknown',
                        'ip' => $ip
                    ];
                }
            }
        } catch(Exception $e) {
            continue;
        }
    }
    
    return ['country' => 'Unknown', 'city' => 'Unknown', 'region' => 'Unknown', 'ip' => $ip];
}

$location_data = getLocationFromIP($ip);

try {
    // Save to login logs
    $db->query("
        INSERT INTO k_user_login_logs 
        (user_id, ip, country, city, region, created_at) 
        VALUES ('$user_id', 
                '".dbx($location_data['ip'])."', 
                '".dbx($location_data['country'])."', 
                '".dbx($location_data['city'])."', 
                '".dbx($location_data['region'])."', 
                NOW())
    ");
    
    // Update users table
    $db->query("
        UPDATE k_users SET 
        last_login_ip = '".dbx($location_data['ip'])."',
        last_login_country = '".dbx($location_data['country'])."',
        last_login_city = '".dbx($location_data['city'])."',
        last_login_region = '".dbx($location_data['region'])."',
        last_login_time = NOW()
        WHERE id = '$user_id'
    ");
    
    echo json_encode([
        'success' => true, 
        'location' => $location_data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch(Throwable $e) {
    error_log("Update location error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>