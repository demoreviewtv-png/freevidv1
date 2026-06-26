<?php
// Set headers so your HTML can read the data securely
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

// Configuration
$dir = "./videos/"; 
$views_file = "VIEWS.txt";
$ips_file = "DO NOT OPEN!!!.txt";
$cooldown_seconds = 300; // 5 minutes anti-spam window

// Helper function to get the actual user IP address
function get_user_ip() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // Can be a comma-separated list if multiple proxies
        $ip_list = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ip_list[0]);
    }
    return $_SERVER['REMOTE_ADDR'];
}

// Helper function to read JSON files safely
function read_json_database($file) {
    if (!file_exists($file)) {
        return array();
    }
    $content = file_get_contents($file);
    $data = json_decode($content, true);
    return is_array($data) ? $data : array();
}

// Helper function to save data safely
function save_json_database($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

// Check if an action is specified in the URL parameter
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

// 1. Fetch all view counts
if ($action === 'get_views') {
    echo json_encode(read_json_database($views_file));
    exit;
}

// 2. Increment view count (With IP Anti-Spam protection)
if ($action === 'increment' && isset($_GET['file'])) {
    $video_file = $_GET['file'];
    $user_ip = get_user_ip();
    $current_time = time();

    $views = read_json_database($views_file);
    $ip_log = read_json_database($ips_file);

    // Initialize track arrays if empty
    if (!isset($views[$video_file])) { $views[$video_file] = 0; }
    if (!isset($ip_log[$user_ip])) { $ip_log[$user_ip] = array(); }

    $can_count_view = true;

    // Check if this specific IP watched this specific video recently
    if (isset($ip_log[$user_ip][$video_file])) {
        $last_view_time = $ip_log[$user_ip][$video_file];
        if (($current_time - $last_view_time) < $cooldown_seconds) {
            $can_count_view = false; // Block request due to spamming window
        }
    }

    if ($can_count_view) {
        // Increment count
        $views[$video_file]++;
        save_json_database($views_file, $views);

        // Update IP tracking timestamp
        $ip_log[$user_ip][$video_file] = $current_time;
    }

    // Clean up old IP logs occasionally to keep file small (older than 24 hours)
    foreach ($ip_log as $ip => $videos) {
        foreach ($videos as $vid => $timestamp) {
            if (($current_time - $timestamp) > 86400) {
                unset($ip_log[$ip][$vid]);
            }
        }
        if (empty($ip_log[$ip])) { unset($ip_log[$ip]); }
    }
    save_json_database($ips_file, $ip_log);

    // Return the response back to client
    echo json_encode(array(
        "views" => $views[$video_file],
        "spammed" => !$can_count_view
    ));
    exit;
}

// 3. Default action: List video files
$video_files = array();
if (is_dir($dir)) {
    if ($dh = opendir($dir)) {
        while (($file = readdir($dh)) !== false) {
            if (preg_match('/\.(mp4|webm|mkv|avi)$/i', $file)) {
                $video_files[] = $file;
            }
        }
        closedir($dh);
    }
}

echo json_encode($video_files);
?>