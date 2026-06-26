<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

// Configuration
$views_file = "VIEWS.txt";
$ips_file = "DO NOT OPEN!!!.txt";
$cooldown_seconds = 300; // 5 minute anti-spam

// 🌐 GitHub API Configuration for tracking files
$github_api_url = "https://api.github.com/repos/demoreviewtv-png/FREEVID-Vidstorage/contents/videos";

function get_user_ip() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip_list = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ip_list[0]);
    }
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
}

function read_json_database($file) {
    if (!file_exists($file)) return array();
    $content = @file_get_contents($file);
    $data = json_decode($content, true);
    return is_array($data) ? $data : array();
}

function save_json_database($file, $data) {
    @file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

$action = isset($_GET['action']) ? $_GET['action'] : 'list';

if ($action === 'get_views') {
    echo json_encode(read_json_database($views_file));
    exit;
}

if ($action === 'increment' && isset($_GET['file'])) {
    $video_file = $_GET['file'];
    $user_ip = get_user_ip();
    $current_time = time();

    $views = read_json_database($views_file);
    $ip_log = read_json_database($ips_file);

    if (!isset($views[$video_file])) { $views[$video_file] = 0; }
    if (!isset($ip_log[$user_ip])) { $ip_log[$user_ip] = array(); }

    $can_count_view = true;

    if (isset($ip_log[$user_ip][$video_file])) {
        $last_view_time = $ip_log[$user_ip][$video_file];
        if (($current_time - $last_view_time) < $cooldown_seconds) {
            $can_count_view = false;
        }
    }

    if ($can_count_view) {
        $views[$video_file]++;
        save_json_database($views_file, $views);
        $ip_log[$user_ip][$video_file] = $current_time;
    }

    echo json_encode(array("views" => $views[$video_file], "spammed" => !$can_count_view));
    exit;
}

// 📦 Fetch the file list dynamically from the GitHub Repository API
$video_files = array();

$opts = [
    "http" => [
        "method" => "GET",
        "header" => "User-Agent: WasmerPHP-App\r\n" // GitHub API requires a User-Agent header
    ]
];
$context = stream_context_create($opts);
$response = @file_get_contents($github_api_url, false, $context);

if ($response) {
    $repo_contents = json_decode($response, true);
    if (is_array($repo_contents)) {
        foreach ($repo_contents as $item) {
            // Filter out files that match common video extension formats
            if (isset($item['type']) && $item['type'] === 'file') {
                if (preg_match('/\.(mp4|webm|mkv|avi)$/i', $item['name'])) {
                    $video_files[] = $item['name'];
                }
            }
        }
    }
}

echo json_encode($video_files);
?>
