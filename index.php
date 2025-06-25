<?php
session_start();

// Database and storage handling
$databaseFile = 'videos.json';
$usersFile = 'users.json';
$channelsFile = 'channels.json';
$changesFile = 'changes.json';
$videosDir = 'videos';
$thumbnailsDir = 'thumbnails';
$subtitlesDir = 'subtitles';

// Create directories with proper permissions
if (!is_dir($videosDir)) {
    if (!mkdir($videosDir, 0777, true)) {
        die(json_encode(['success' => false, 'message' => "Failed to create videos directory"]));
    }
    chmod($videosDir, 0777);
}
if (!is_dir($thumbnailsDir)) {
    if (!mkdir($thumbnailsDir, 0777, true)) {
        die(json_encode(['success' => false, 'message' => "Failed to create thumbnails directory"]));
    }
    chmod($thumbnailsDir, 0777);
}
if (!is_dir($subtitlesDir)) {
    if (!mkdir($subtitlesDir, 0777, true)) {
        die(json_encode(['success' => false, 'message' => "Failed to create subtitles directory"]));
    }
    chmod($subtitlesDir, 0777);
}

// Initialize videos database
if (!file_exists($databaseFile)) {
    file_put_contents($databaseFile, json_encode(['videos' => []]));
    chmod($databaseFile, 0666);
}

// Initialize users database
if (!file_exists($usersFile)) {
    $superadminPassword = password_hash('L_X@$2006k', PASSWORD_DEFAULT);
    $users = [
        'superadmin' => [
            'username' => 'superadmin',
            'password' => $superadminPassword,
            'role' => 'superadmin',
            'first_name' => 'Admin',
            'last_name' => 'System',
            'email' => 'admin@videohub.com',
            'phone' => '+1234567890',
            'birthdate' => '1990-01-01',
            'created_at' => date('Y-m-d H:i:s'),
            'last_login' => null,
            'channel_created' => true
        ]
    ];
    file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));
    chmod($usersFile, 0666);
}

// Initialize channels database
if (!file_exists($channelsFile)) {
    $channels = [
        'superadmin' => [
            'owner' => 'superadmin',
            'name' => 'Official VideoHub',
            'description' => 'The official channel of VideoHub',
            'created_at' => date('Y-m-d H:i:s'),
            'subscribers' => [],
            'videos' => []
        ]
    ];
    file_put_contents($channelsFile, json_encode($channels, JSON_PRETTY_PRINT));
    chmod($channelsFile, 0666);
}

// Initialize changes database
if (!file_exists($changesFile)) {
    file_put_contents($changesFile, json_encode(['changes' => []]));
    chmod($changesFile, 0666);
}

// Load videos database
$database = json_decode(file_get_contents($databaseFile), true) ?: ['videos' => []];

// Load users database
$users = json_decode(file_get_contents($usersFile), true) ?: [];

// Load channels database
$channels = json_decode(file_get_contents($channelsFile), true) ?: [];

// Load changes database
$changes = json_decode(file_get_contents($changesFile), true) ?: ['changes' => []];

// Check if today is any user's birthday
$today = date('m-d');
$birthdayUsers = [];
foreach ($users as $user) {
    if (!empty($user['birthdate'])) {
        $birthdate = date('m-d', strtotime($user['birthdate']));
        if ($birthdate === $today) {
            $birthdayUsers[] = $user['first_name'] . ' ' . $user['last_name'];
        }
    }
}

// Function to log changes
function logChange($action, $details, $user) {
    global $changes, $changesFile;
    
    $change = [
        'id' => uniqid(),
        'action' => $action,
        'user' => $user,
        'details' => $details,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    $changes['changes'][] = $change;
    file_put_contents($changesFile, json_encode($changes, JSON_PRETTY_PRINT));
}

// Handle authentication
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'login') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if (isset($users[$username]) && password_verify($password, $users[$username]['password'])) {
            $_SESSION['user'] = $users[$username];
            $_SESSION['user']['username'] = $username;
            $users[$username]['last_login'] = date('Y-m-d H:i:s');
            file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));
            
            // Log login
            logChange('login', "User logged in: $username", $username);
            
            // Check for birthday
            $birthdayMsg = '';
            if (!empty($users[$username]['birthdate'])) {
                $birthdate = date('m-d', strtotime($users[$username]['birthdate']));
                if ($birthdate === $today) {
                    $birthdayMsg = 'Happy Birthday, ' . $users[$username]['first_name'] . '!';
                }
            }
            
            $response = [
                'success' => true, 
                'message' => 'Login successful!' . ($birthdayMsg ? ' ' . $birthdayMsg : ''),
                'is_birthday' => !empty($birthdayMsg)
            ];
        } else {
            $response = ['success' => false, 'message' => 'Invalid username or password'];
        }
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    elseif ($_POST['action'] === 'register') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $email = $_POST['email'] ?? '';
        $first_name = $_POST['first_name'] ?? '';
        $last_name = $_POST['last_name'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $birthdate = $_POST['birthdate'] ?? '';
        
        $response = ['success' => false, 'message' => ''];
        
        // Validate input
        if (empty($username) || empty($password) || empty($email)) {
            $response['message'] = 'Username, password and email are required';
        } elseif (isset($users[$username])) {
            $response['message'] = 'Username already taken';
        } else {
            // Create new user
            $users[$username] = [
                'username' => $username,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'role' => 'user',
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'phone' => $phone,
                'birthdate' => $birthdate,
                'created_at' => date('Y-m-d H:i:s'),
                'last_login' => null,
                'channel_created' => false
            ];
            
            file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));
            
            // Log registration
            logChange('register', "New user registered: $username", $username);
            
            $response = [
                'success' => true, 
                'message' => 'Registration successful! You can now login.'
            ];
        }
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    elseif ($_POST['action'] === 'logout') {
        $username = $_SESSION['user']['username'] ?? 'unknown';
        unset($_SESSION['user']);
        
        // Log logout
        logChange('logout', "User logged out: $username", $username);
        
        $response = ['success' => true, 'message' => 'Logout successful!'];
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    // Channel creation
    elseif ($_POST['action'] === 'create_channel') {
        if (!isset($_SESSION['user'])) {
            $response = ['success' => false, 'message' => 'You must be logged in'];
        } else {
            $username = $_SESSION['user']['username'];
            $name = $_POST['name'] ?? '';
            $description = $_POST['description'] ?? '';
            
            if (empty($name)) {
                $response = ['success' => false, 'message' => 'Channel name is required'];
            } elseif (isset($channels[$username])) {
                $response = ['success' => false, 'message' => 'You already have a channel'];
            } else {
                $channels[$username] = [
                    'owner' => $username,
                    'name' => $name,
                    'description' => $description,
                    'created_at' => date('Y-m-d H:i:s'),
                    'subscribers' => [],
                    'videos' => []
                ];
                
                $users[$username]['channel_created'] = true;
                $_SESSION['user']['channel_created'] = true;
                
                file_put_contents($channelsFile, json_encode($channels, JSON_PRETTY_PRINT));
                file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));
                
                // Log channel creation
                logChange('create_channel', "Channel created: $name", $username);
                
                $response = [
                    'success' => true,
                    'message' => 'Channel created successfully!'
                ];
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    // Like video
    elseif ($_POST['action'] === 'like_video') {
        if (!isset($_SESSION['user'])) {
            $response = ['success' => false, 'message' => 'You must be logged in'];
        } else {
            $videoId = $_POST['video_id'] ?? '';
            $username = $_SESSION['user']['username'];
            $liked = false;
            
            foreach ($database['videos'] as &$video) {
                if ($video['id'] === $videoId) {
                    if (!isset($video['likes'])) {
                        $video['likes'] = [];
                    }
                    
                    $index = array_search($username, $video['likes']);
                    if ($index === false) {
                        $video['likes'][] = $username;
                        $liked = true;
                    } else {
                        array_splice($video['likes'], $index, 1);
                    }
                    
                    $response = [
                        'success' => true,
                        'liked' => $liked,
                        'likes' => count($video['likes'])
                    ];
                    break;
                }
            }
            
            if (isset($response)) {
                file_put_contents($databaseFile, json_encode($database, JSON_PRETTY_PRINT));
            } else {
                $response = ['success' => false, 'message' => 'Video not found'];
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    // Subscribe to channel
    elseif ($_POST['action'] === 'subscribe') {
        if (!isset($_SESSION['user'])) {
            $response = ['success' => false, 'message' => 'You must be logged in'];
        } else {
            $channelOwner = $_POST['channel_owner'] ?? '';
            $username = $_SESSION['user']['username'];
            
            if (!isset($channels[$channelOwner])) {
                $response = ['success' => false, 'message' => 'Channel not found'];
            } else {
                $index = array_search($username, $channels[$channelOwner]['subscribers']);
                if ($index === false) {
                    $channels[$channelOwner]['subscribers'][] = $username;
                    $subscribed = true;
                    
                    // Log subscription
                    logChange('subscribe', "Subscribed to channel: $channelOwner", $username);
                } else {
                    array_splice($channels[$channelOwner]['subscribers'], $index, 1);
                    $subscribed = false;
                    
                    // Log unsubscription
                    logChange('unsubscribe', "Unsubscribed from channel: $channelOwner", $username);
                }
                
                file_put_contents($channelsFile, json_encode($channels, JSON_PRETTY_PRINT));
                
                $response = [
                    'success' => true,
                    'subscribed' => $subscribed,
                    'subscribers' => count($channels[$channelOwner]['subscribers'])
                ];
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    // Increment video views
    elseif ($_POST['action'] === 'increment_views') {
        $videoId = $_POST['video_id'] ?? '';
        $response = ['success' => false];
        
        foreach ($database['videos'] as &$video) {
            if ($video['id'] === $videoId) {
                if (!isset($video['views'])) {
                    $video['views'] = 0;
                }
                
                $video['views']++;
                $response = [
                    'success' => true,
                    'views' => $video['views']
                ];
                break;
            }
        }
        
        if ($response['success']) {
            file_put_contents($databaseFile, json_encode($database, JSON_PRETTY_PRINT));
        }
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    // Remove video from channel
    elseif ($_POST['action'] === 'remove_from_channel') {
        if (!isset($_SESSION['user'])) {
            $response = ['success' => false, 'message' => 'You must be logged in'];
        } else {
            $videoId = $_POST['video_id'] ?? '';
            $username = $_SESSION['user']['username'];
            
            if (!isset($channels[$username])) {
                $response = ['success' => false, 'message' => 'Channel not found'];
            } else {
                // Find video in database
                $videoFound = false;
                $videoOwner = '';
                
                foreach ($database['videos'] as $index => $video) {
                    if ($video['id'] === $videoId) {
                        $videoFound = true;
                        $videoOwner = $video['artist'];
                        break;
                    }
                }
                
                if (!$videoFound) {
                    $response = ['success' => false, 'message' => 'Video not found'];
                } elseif ($videoOwner !== $username) {
                    $response = ['success' => false, 'message' => 'You are not the owner of this video'];
                } else {
                    // Remove video from channel
                    $videoIndex = array_search($videoId, $channels[$username]['videos']);
                    if ($videoIndex !== false) {
                        array_splice($channels[$username]['videos'], $videoIndex, 1);
                        file_put_contents($channelsFile, json_encode($channels, JSON_PRETTY_PRINT));
                        
                        // Log removal
                        logChange('remove_video', "Removed video from channel: $videoId", $username);
                        
                        $response = ['success' => true, 'message' => 'Video removed from your channel'];
                    } else {
                        $response = ['success' => false, 'message' => 'Video not found in your channel'];
                    }
                }
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    // Delete channel (superadmin only)
    elseif ($_POST['action'] === 'delete_channel') {
        if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'superadmin') {
            $response = ['success' => false, 'message' => 'Permission denied'];
        } else {
            $channelOwner = $_POST['username'] ?? '';
            $username = $_SESSION['user']['username'];
            
            if (!isset($channels[$channelOwner])) {
                $response = ['success' => false, 'message' => 'Channel not found'];
            } else {
                $channelName = $channels[$channelOwner]['name'];
                unset($channels[$channelOwner]);
                file_put_contents($channelsFile, json_encode($channels, JSON_PRETTY_PRINT));
                
                // Log deletion
                logChange('delete_channel', "Deleted channel: $channelName ($channelOwner)", $username);
                
                $response = ['success' => true, 'message' => 'Channel deleted successfully'];
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}

// Check if user is logged in and has superadmin role
$isSuperadmin = isset($_SESSION['user']) && $_SESSION['user']['role'] === 'superadmin';

// Handle file uploads - for both superadmin and channel owners
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && 
    ($isSuperadmin || (isset($_SESSION['user']) && $_SESSION['user']['channel_created']))) {
    
    $response = ['success' => false, 'message' => ''];
    
    try {
        if ($_POST['action'] === 'upload') {
            // Fixed artist handling for non-superadmin users
            $artist = $isSuperadmin ? ($_POST['artist'] ?? '') : $_SESSION['user']['username'];
            $username = $_SESSION['user']['username'];
            
            $title = $_POST['title'] ?? '';
            $category = $_POST['category'] ?? '';
            $tags = $_POST['tags'] ?? '';
            $description = $_POST['description'] ?? '';
            $thumbnailData = $_POST['thumbnail'] ?? '';
            $duration = $_POST['duration'] ?? 0;
            
            // Validate required fields
            if (empty($title) || empty($category)) {
                throw new Exception('Title and category are required');
            }
            
            // Handle video upload
            if (!isset($_FILES['video']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
                $errorMsg = 'Video upload failed: ';
                $errorMsg .= isset($_FILES['video']) ? 'Error code ' . $_FILES['video']['error'] : 'No file uploaded';
                throw new Exception($errorMsg);
            }
            
            $videoFile = $_FILES['video'];
            $videoExt = pathinfo($videoFile['name'], PATHINFO_EXTENSION);
            $allowedExtensions = ['mp4', 'mov', 'webm'];
            
            if (!in_array(strtolower($videoExt), $allowedExtensions)) {
                throw new Exception('Invalid video format. Allowed: ' . implode(', ', $allowedExtensions));
            }
            
            $videoFilename = uniqid() . '.' . $videoExt;
            $videoPath = "$videosDir/$videoFilename";
            
            if (!move_uploaded_file($videoFile['tmp_name'], $videoPath)) {
                throw new Exception('Failed to save video file. Check directory permissions.');
            }
            chmod($videoPath, 0666);
            
            // Save thumbnail
            if (strpos($thumbnailData, 'base64') === false) {
                throw new Exception('Invalid thumbnail data format');
            }
            
            $thumbnailData = preg_replace('/^data:image\/\w+;base64,/', '', $thumbnailData);
            $thumbnailData = str_replace(' ', '+', $thumbnailData);
            $thumbnailBinary = base64_decode($thumbnailData);
            
            if ($thumbnailBinary === false) {
                throw new Exception('Thumbnail base64 decode failed');
            }
            
            $thumbnailFilename = uniqid() . '.jpg';
            $thumbnailPath = "$thumbnailsDir/$thumbnailFilename";
            
            if (!file_put_contents($thumbnailPath, $thumbnailBinary)) {
                throw new Exception('Failed to save thumbnail. Check directory permissions.');
            }
            chmod($thumbnailPath, 0666);

            // Format duration
            $durationFormatted = gmdate('i:s', $duration);
            if ($duration >= 3600) {
                $durationFormatted = gmdate('H:i:s', $duration);
            }
            
            // Generate multiple resolutions (simulated)
            $resolutions = [
                '1080p' => $videoFilename,
                '720p' => $videoFilename,
                '480p' => $videoFilename,
                '360p' => $videoFilename
            ];
            
            // Generate AI subtitles (simulated)
            $subtitles = [];
            $languages = ['en', 'es', 'fr', 'de'];
            foreach ($languages as $lang) {
                $subtitles[$lang] = "subtitle_".uniqid()."_{$lang}.vtt";
                // Create placeholder subtitle file
                file_put_contents("$subtitlesDir/{$subtitles[$lang]}", "WEBVTT\n\n1\n00:00:01.000 --> 00:00:04.000\n[AI-generated subtitles in {$lang}]");
            }
            
            // Add to database
            $newVideo = [
                'id' => uniqid(),
                'title' => $title,
                'artist' => $artist,
                'category' => $category,
                'tags' => $tags,
                'description' => $description,
                'duration' => $duration,
                'durationFormatted' => $durationFormatted,
                'videoFilename' => $videoFilename,
                'thumbnailFilename' => $thumbnailFilename,
                'uploadDate' => date('Y-m-d'),
                'uploadDateFormatted' => 'Just now',
                'fileSize' => filesize($videoPath),
                'resolutions' => $resolutions,
                'subtitles' => $subtitles,
                'views' => 0,
                'likes' => []
            ];
            
            $database['videos'][] = $newVideo;
            file_put_contents($databaseFile, json_encode($database, JSON_PRETTY_PRINT));
            chmod($databaseFile, 0666);
            
            // Add video to channel
            if (isset($channels[$artist])) {
                $channels[$artist]['videos'][] = $newVideo['id'];
                file_put_contents($channelsFile, json_encode($channels, JSON_PRETTY_PRINT));
            }
            
            // Log upload
            logChange('upload', "Uploaded video: $title", $username);
            
            $response = [
                'success' => true,
                'message' => 'Video uploaded successfully!',
                'video' => $newVideo
            ];
        }
        elseif ($_POST['action'] === 'delete') {
            $id = $_POST['id'] ?? '';
            $index = -1;
            $username = $_SESSION['user']['username'];
            
            foreach ($database['videos'] as $i => $video) {
                if ($video['id'] === $id) {
                    $index = $i;
                    break;
                }
            }
            
            if ($index >= 0) {
                $video = $database['videos'][$index];
                
                // Check permissions for deletion
                if (!$isSuperadmin) {
                    if (!isset($_SESSION['user']) || $video['artist'] !== $_SESSION['user']['username']) {
                        throw new Exception('You do not have permission to delete this video');
                    }
                }
                
                // Delete files
                @unlink("$videosDir/{$video['videoFilename']}");
                @unlink("$thumbnailsDir/{$video['thumbnailFilename']}");
                
                // Delete subtitle files
                if (isset($video['subtitles'])) {
                    foreach ($video['subtitles'] as $subFile) {
                        @unlink("$subtitlesDir/$subFile");
                    }
                }
                
                // Remove from database
                array_splice($database['videos'], $index, 1);
                file_put_contents($databaseFile, json_encode($database, JSON_PRETTY_PRINT));
                
                // Remove video from channel
                if (isset($channels[$video['artist']])) {
                    $channel = $channels[$video['artist']];
                    $videoIndex = array_search($video['id'], $channel['videos']);
                    if ($videoIndex !== false) {
                        array_splice($channels[$video['artist']]['videos'], $videoIndex, 1);
                        file_put_contents($channelsFile, json_encode($channels, JSON_PRETTY_PRINT));
                    }
                }
                
                // Log deletion
                logChange('delete', "Deleted video: {$video['title']}", $username);
                
                $response = [
                    'success' => true,
                    'message' => 'Video deleted successfully!'
                ];
            } else {
                throw new Exception('Video not found');
            }
        }
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Get all videos
$videos = $database['videos'] ?? [];

// Create base URL for resources
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
$projectPath = dirname($_SERVER['SCRIPT_NAME']);
$resourceBaseUrl = rtrim($baseUrl . $projectPath, '/') . '/';

// Prepare videos JSON for JavaScript
$videos_json = json_encode($videos);
$videos_escaped = htmlspecialchars($videos_json, ENT_QUOTES, 'UTF-8');

// Prepare channels JSON for JavaScript
$channels_json = json_encode($channels);
$channels_escaped = htmlspecialchars($channels_json, ENT_QUOTES, 'UTF-8');

// Prepare changes JSON for JavaScript
$changes_json = json_encode($changes);
$changes_escaped = htmlspecialchars($changes_json, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VideoHub - Multi-Res & AI Subtitles</title>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#4361ee',
                        secondary: '#3a0ca3',
                        accent: '#4cc9f0',
                        background: '#0f172a',
                        card: '#1e293b',
                        admin: '#f59e0b',
                        google: '#4285F4',
                        apple: '#000000',
                        microsoft: '#7FBA00'
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, var(--background) 0%, var(--card) 100%);
            min-height: 100vh;
            color: #e2e8f0;
            overflow-x: hidden;
        }
        
        .admin-badge {
            background: rgba(245, 158, 11, 0.2);
            color: #f59e0b;
            border: 1px solid rgba(245, 158, 11, 0.4);
        }
        
        .drop-zone {
            border: 2px dashed var(--accent);
            transition: all 0.3s;
            background: rgba(30, 41, 59, 0.3);
        }
        
        .drop-zone.dragover {
            background-color: rgba(76, 201, 240, 0.1);
            border-color: var(--primary);
        }
        
        .video-card {
            transition: all 0.3s ease;
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .video-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.2);
        }
        
        .thumbnail-container {
            position: relative;
            padding-bottom: 56.25%; /* 16:9 aspect ratio */
        }
        
        .play-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to top, rgba(0,0,0,0.7) 0%, transparent 60%);
            opacity: 0;
            transition: opacity 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .video-card:hover .play-overlay {
            opacity: 1;
        }
        
        .modal-overlay {
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(5px);
        }
        
        .modal-content {
            background: rgba(30, 41, 59, 0.95);
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            max-width: 95%;
            width: 100%;
        }
        
        .notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            z-index: 1000;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s ease;
        }
        
        .notification.show {
            transform: translateY(0);
            opacity: 1;
        }
        
        .success-notification {
            background: var(--primary);
            color: white;
        }
        
        .error-notification {
            background: #ef4444;
            color: white;
        }
        
        .admin-notification {
            background: #f59e0b;
            color: white;
        }
        
        .birthday-notification {
            background: linear-gradient(90deg, #ec4899, #8b5cf6);
            color: white;
        }
        
        .progress-bar {
            height: 4px;
            background-color: var(--primary);
            width: 0%;
            transition: width 0.1s;
        }
        
        .tag {
            display: inline-block;
            background: rgba(67, 97, 238, 0.2);
            color: var(--accent);
            border-radius: 20px;
            padding: 2px 10px;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .tag:hover {
            background: rgba(67, 97, 238, 0.4);
        }
        
        .filter-tag {
            display: inline-flex;
            align-items: center;
            background: rgba(67, 97, 238, 0.3);
            color: var(--accent);
            border-radius: 20px;
            padding: 4px 12px;
            margin: 0 4px 8px 0;
        }
        
        .filter-tag-remove {
            margin-left: 8px;
            cursor: pointer;
        }
        
        .sort-btn {
            background: rgba(30, 41, 59, 0.7);
            color: var(--accent);
            border-radius: 8px;
            padding: 6px 12px;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .sort-btn:hover, .sort-btn.active {
            background: var(--primary);
            color: white;
        }
        
        .search-container {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 15px;
        }
        
        .admin-panel {
            background: rgba(15, 23, 42, 0.9);
            border-left: 3px solid #f59e0b;
        }
        
        .social-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px 15px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .social-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .google-btn {
            background: var(--google);
        }
        
        .apple-btn {
            background: var(--apple);
        }
        
        .microsoft-btn {
            background: var(--microsoft);
        }
        
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            color: #94a3b8;
            margin: 20px 0;
        }
        
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #334155;
        }
        
        .divider::before {
            margin-right: 10px;
        }
        
        .divider::after {
            margin-left: 10px;
        }
        
        @media (max-width: 768px) {
            .modal-content {
                border-radius: 12px;
            }
            
            .player-container {
                width: 100%;
            }
        }
        
        .fade-enter-active, .fade-leave-active {
            transition: opacity 0.3s;
        }
        .fade-enter, .fade-leave-to {
            opacity: 0;
        }
        .scale-enter-active, .scale-leave-active {
            transition: all 0.3s ease;
        }
        .scale-enter, .scale-leave-to {
            opacity: 0;
            transform: scale(0.95);
        }
        
        .user-card {
            background: rgba(30, 41, 59, 0.7);
            border-radius: 10px;
            transition: all 0.3s;
        }
        
        .user-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .res-menu {
            position: absolute;
            bottom: 70px;
            right: 10px;
            background: rgba(0, 0, 0, 0.7);
            border-radius: 8px;
            padding: 8px;
            z-index: 50;
        }
        
        .res-option {
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            color: white;
        }
        
        .res-option:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .res-option.active {
            background: var(--primary);
            color: white;
        }
        
        .sub-menu {
            position: absolute;
            bottom: 70px;
            right: 100px;
            background: rgba(0, 0, 0, 0.7);
            border-radius: 8px;
            padding: 8px;
            z-index: 50;
        }
        
        .sub-option {
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            color: white;
        }
        
        .sub-option:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .sub-option.active {
            background: var(--accent);
            color: white;
        }
        
        .player-controls {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.7) 0%, transparent);
            padding: 15px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .control-btn {
            background: rgba(0,0,0,0.5);
            color: white;
            border-radius: 8px;
            padding: 5px 12px;
            font-size: 14px;
            display: flex;
            align-items: center;
        }
        
        .control-btn i {
            margin-right: 5px;
        }
        
        /* STYLES FOR CHANNELS AND STATS */
        .channel-card {
            background: rgba(30, 41, 59, 0.7);
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .channel-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.2);
        }
        
        .channel-header {
            position: relative;
            height: 150px;
            background: linear-gradient(135deg, #4361ee, #3a0ca3);
        }
        
        .channel-avatar {
            position: absolute;
            bottom: -40px;
            left: 20px;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 3px solid var(--card);
            background: var(--background);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
        }
        
        .like-btn {
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .like-btn:hover {
            transform: scale(1.1);
        }
        
        .like-btn.liked {
            color: #ef4444;
        }
        
        .subscribe-btn {
            background: #ef4444;
            color: white;
            border-radius: 20px;
            padding: 5px 15px;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .subscribe-btn.subscribed {
            background: #64748b;
        }
        
        .stat-card {
            background: rgba(30, 41, 59, 0.5);
            border-radius: 10px;
            padding: 15px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: #94a3b8;
        }
        
        .remove-btn {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 20px;
            padding: 3px 10px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .remove-btn:hover {
            background: rgba(239, 68, 68, 0.2);
        }
    </style>
</head>
<body class="min-h-screen" x-data="videoHubApp()" x-init="init(<?= $videos_escaped ?>, '<?= $resourceBaseUrl ?>', <?= $isSuperadmin ? 'true' : 'false' ?>, <?= !empty($birthdayUsers) ? 'true' : 'false' ?>, <?= $channels_escaped ?>, <?= $changes_escaped ?>)">
    <!-- Header -->
    <header class="bg-gradient-to-r from-secondary to-primary py-4 shadow-xl sticky top-0 z-20">
        <div class="container mx-auto px-4 flex justify-between items-center">
            <div class="flex items-center">
                <div class="bg-white p-2 rounded-lg mr-3 shadow-lg shadow-blue-500/30">
                    <i class="fas fa-play-circle text-2xl text-primary"></i>
                </div>
                <div>
                    <h1 class="text-xl md:text-2xl font-bold text-white">VideoHub Pro</h1>
                    <p class="text-blue-100 text-xs md:text-sm">Multi-Res & AI Subtitles</p>
                </div>
            </div>
            
            <div class="flex items-center space-x-2">
                <template x-if="isSuperadmin || (isLoggedIn && userHasChannel)">
                    <button @click="openUploadModal" class="flex items-center bg-white text-primary font-medium py-1 px-3 md:py-2 md:px-4 rounded-lg hover:bg-blue-50 transition">
                        <i class="fas fa-cloud-upload-alt mr-1 md:mr-2 text-sm md:text-base"></i> 
                        <span class="hidden md:inline">Upload</span>
                    </button>
                </template>
                
                <template x-if="isLoggedIn">
                    <div class="flex items-center">
                        <div class="flex items-center bg-card rounded-lg px-3 py-1.5 mr-2 cursor-pointer" @click="openChannelDashboard">
                            <i class="fas fa-user-circle text-accent mr-2"></i>
                            <span x-text="username"></span>
                            <span x-show="isSuperadmin" class="admin-badge text-xs ml-2 px-2 py-0.5 rounded-full">Superadmin</span>
                        </div>
                        <button @click="logout" class="flex items-center bg-gray-700 hover:bg-gray-600 text-white font-medium py-1 px-3 md:py-2 md:px-4 rounded-lg transition">
                            <i class="fas fa-sign-out-alt mr-1 md:mr-2 text-sm md:text-base"></i> 
                            <span class="hidden md:inline">Logout</span>
                        </button>
                    </div>
                </template>
                
                <template x-if="!isLoggedIn">
                    <div class="flex space-x-2">
                        <button @click="loginModalOpen = true" class="flex items-center bg-white text-primary font-medium py-1 px-3 md:py-2 md:px-4 rounded-lg hover:bg-blue-50 transition">
                            <i class="fas fa-sign-in-alt mr-1 md:mr-2 text-sm md:text-base"></i> 
                            <span class="hidden md:inline">Login</span>
                        </button>
                        <button @click="registerModalOpen = true" class="flex items-center bg-gradient-to-r from-purple-500 to-pink-500 text-white font-medium py-1 px-3 md:py-2 md:px-4 rounded-lg hover:opacity-90 transition">
                            <i class="fas fa-user-plus mr-1 md:mr-2 text-sm md:text-base"></i> 
                            <span class="hidden md:inline">Register</span>
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </header>
    
    <!-- Admin Panel -->
    <template x-if="isSuperadmin">
        <div class="admin-panel border-b border-yellow-500/20 py-2">
            <div class="container mx-auto px-4 flex justify-between items-center">
                <div class="flex items-center text-yellow-300">
                    <i class="fas fa-shield-alt mr-2"></i>
                    <span class="font-medium">SUPERADMIN PRIVILEGES ACTIVATED</span>
                </div>
                <div class="flex space-x-2">
                    <button @click="adminView = 'videos'" :class="{'bg-yellow-500 text-white': adminView === 'videos'}" class="px-3 py-1 rounded text-sm">
                        Videos
                    </button>
                    <button @click="adminView = 'users'" :class="{'bg-yellow-500 text-white': adminView === 'users'}" class="px-3 py-1 rounded text-sm">
                        Users
                    </button>
                    <button @click="adminView = 'stats'" :class="{'bg-yellow-500 text-white': adminView === 'stats'}" class="px-3 py-1 rounded text-sm">
                        Statistics
                    </button>
                    <button @click="adminView = 'channels'" :class="{'bg-yellow-500 text-white': adminView === 'channels'}" class="px-3 py-1 rounded text-sm">
                        Channels
                    </button>
                    <button @click="adminView = 'changes'" :class="{'bg-yellow-500 text-white': adminView === 'changes'}" class="px-3 py-1 rounded text-sm">
                        Change Log
                    </button>
                </div>
            </div>
        </div>
    </template>
    
    <!-- Login Modal -->
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" x-show="loginModalOpen" x-cloak
         x-transition:enter="fade-enter"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="fade-leave"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        <div class="modal-overlay absolute inset-0" @click="loginModalOpen = false"></div>
        
        <div class="modal-content relative z-10 max-w-md w-full"
             @click.stop
             x-transition:enter="scale-enter"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="scale-leave"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95">
            <button @click="loginModalOpen = false" class="absolute top-4 right-4 text-gray-400 hover:text-white z-20">
                <i class="fas fa-times"></i>
            </button>
            
            <div class="p-6">
                <div class="text-center mb-6">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gradient-to-r from-primary to-secondary mb-3">
                        <i class="fas fa-lock text-2xl text-white"></i>
                    </div>
                    <h2 class="text-2xl font-bold">Login to VideoHub</h2>
                    <p class="text-gray-400 mt-1">Access your account to enjoy our video collection</p>
                </div>
                
                <form id="login-form" @submit.prevent="login">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Username</label>
                            <input type="text" x-model="loginUsername" required 
                                   class="w-full p-3 rounded-lg bg-gray-800 border border-gray-700 text-white transition focus:border-accent focus:outline-none"
                                   placeholder="username">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-1">Password</label>
                            <input type="password" x-model="loginPassword" required 
                                   class="w-full p-3 rounded-lg bg-gray-800 border border-gray-700 text-white transition focus:border-accent focus:outline-none"
                                   placeholder="••••••••">
                        </div>
                        
                        <div class="flex justify-end pt-2">
                            <button type="submit" class="w-full bg-primary hover:bg-blue-700 px-4 py-3 rounded-lg text-white font-medium transition">
                                Sign In
                            </button>
                        </div>
                        
                        <div class="divider">or continue with</div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <button type="button" class="social-btn google-btn">
                                <i class="fab fa-google mr-2"></i> Google
                            </button>
                            <button type="button" class="social-btn apple-btn">
                                <i class="fab fa-apple mr-2"></i> Apple
                            </button>
                            <button type="button" class="social-btn microsoft-btn">
                                <i class="fab fa-microsoft mr-2"></i> Microsoft
                            </button>
                        </div>
                        
                        <div class="text-center text-sm mt-4">
                            Don't have an account? 
                            <button type="button" @click="showRegisterInstead" class="text-accent hover:underline">
                                Register now
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Registration Modal -->
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" x-show="registerModalOpen" x-cloak
         x-transition:enter="fade-enter"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="fade-leave"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        <div class="modal-overlay absolute inset-0" @click="registerModalOpen = false"></div>
        
        <div class="modal-content relative z-10 max-w-md w-full max-h-[90vh] overflow-y-auto"
             @click.stop
             x-transition:enter="scale-enter"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="scale-leave"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95">
            <button @click="registerModalOpen = false" class="absolute top-4 right-4 text-gray-400 hover:text-white z-20">
                <i class="fas fa-times"></i>
            </button>
            
            <div class="p-6">
                <div class="text-center mb-6">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gradient-to-r from-purple-500 to-pink-500 mb-3">
                        <i class="fas fa-user-plus text-2xl text-white"></i>
                    </div>
                    <h2 class="text-2xl font-bold">Create an Account</h2>
                    <p class="text-gray-400 mt-1">Join our video community today</p>
                </div>
                
                <form id="register-form" @submit.prevent="register">
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-1">First Name *</label>
                                <input type="text" x-model="registerFirstName" required 
                                       class="w-full p-3 rounded-lg bg-gray-800 border border-gray-700 text-white transition focus:border-accent focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1">Last Name *</label>
                                <input type="text" x-model="registerLastName" required 
                                       class="w-full p-3 rounded-lg bg-gray-800 border border-gray-700 text-white transition focus:border-accent focus:outline-none">
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-1">Username *</label>
                            <input type="text" x-model="registerUsername" required 
                                   class="w-full p-3 rounded-lg bg-gray-800 border border-gray-700 text-white transition focus:border-accent focus:outline-none"
                                   placeholder="username">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-1">Email *</label>
                            <input type="email" x-model="registerEmail" required 
                                   class="w-full p-3 rounded-lg bg-gray-800 border border-gray-700 text-white transition focus:border-accent focus:outline-none"
                                   placeholder="email@example.com">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-1">Phone Number</label>
                            <input type="tel" x-model="registerPhone" 
                                   class="w-full p-3 rounded-lg bg-gray-800 border border-gray-700 text-white transition focus:border-accent focus:outline-none"
                                   placeholder="+1 (123) 456-7890">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-1">Birthdate</label>
                            <input type="date" x-model="registerBirthdate" 
                                   class="w-full p-3 rounded-lg bg-gray-800 border border-gray-700 text-white transition focus:border-accent focus:outline-none">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-1">Password *</label>
                            <input type="password" x-model="registerPassword" required 
                                   class="w-full p-3 rounded-lg bg-gray-800 border border-gray-700 text-white transition focus:border-accent focus:outline-none">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-1">Confirm Password *</label>
                            <input type="password" x-model="registerPasswordConfirm" required 
                                   class="w-full p-3 rounded-lg bg-gray-800 border border-gray-700 text-white transition focus:border-accent focus:outline-none">
                        </div>
                        
                        <div class="flex items-center">
                            <input type="checkbox" id="terms" required class="mr-2">
                            <label for="terms" class="text-sm">I agree to the <a href="#" class="text-accent hover:underline">Terms of Service</a> and <a href="#" class="text-accent hover:underline">Privacy Policy</a></label>
                        </div>
                        
                        <div class="flex justify-end pt-2">
                            <button type="submit" class="w-full bg-gradient-to-r from-purple-500 to-pink-500 hover:opacity-90 px-4 py-3 rounded-lg text-white font-medium transition">
                                Create Account
                            </button>
                        </div>
                        
                        <div class="divider">or sign up with</div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <button type="button" class="social-btn google-btn">
                                <i class="fab fa-google mr-2"></i> Google
                            </button>
                            <button type="button" class="social-btn apple-btn">
                                <i class="fab fa-apple mr-2"></i> Apple
                            </button>
                            <button type="button" class="social-btn microsoft-btn">
                                <i class="fab fa-microsoft mr-2"></i> Microsoft
                            </button>
                        </div>
                        
                        <div class="text-center text-sm mt-4">
                            Already have an account? 
                            <button type="button" @click="showLoginInstead" class="text-accent hover:underline">
                                Login here
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <template x-if="!isSuperadmin || (isSuperadmin && adminView === 'videos')">
        <main class="flex-grow container mx-auto px-4 pb-6">
            <!-- Birthday Banner -->
            <div class="bg-gradient-to-r from-pink-500 to-purple-600 rounded-xl p-5 mb-6 text-center" x-show="birthdayNotification">
                <div class="flex items-center justify-center">
                    <i class="fas fa-birthday-cake text-2xl mr-3"></i>
                    <h2 class="text-xl font-bold" x-text="birthdayMessage"></h2>
                </div>
            </div>
            
            <!-- Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-card rounded-xl p-4 shadow-lg">
                    <div class="flex items-center">
                        <div class="p-2 rounded-full bg-blue-500/20 text-blue-400 mr-3">
                            <i class="fas fa-film text-xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-400 text-xs md:text-sm">Total Videos</p>
                            <p class="text-xl font-bold" x-text="videos.length"></p>
                        </div>
                    </div>
                </div>
                <div class="bg-card rounded-xl p-4 shadow-lg">
                    <div class="flex items-center">
                        <div class="p-2 rounded-full bg-purple-500/20 text-purple-400 mr-3">
                            <i class="fas fa-folder text-xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-400 text-xs md:text-sm">Categories</p>
                            <p class="text-xl font-bold" x-text="new Set(videos.map(v => v.category)).size"></p>
                        </div>
                    </div>
                </div>
                <div class="bg-card rounded-xl p-4 shadow-lg">
                    <div class="flex items-center">
                        <div class="p-2 rounded-full bg-green-500/20 text-green-400 mr-3">
                            <i class="fas fa-hdd text-xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-400 text-xs md:text-sm">Storage</p>
                            <p class="text-xl font-bold" x-text="formatFileSize(totalStorage)"></p>
                        </div>
                    </div>
                </div>
                <div class="bg-card rounded-xl p-4 shadow-lg">
                    <div class="flex items-center">
                        <div class="p-2 rounded-full bg-yellow-500/20 text-yellow-400 mr-3">
                            <i class="fas fa-users text-xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-400 text-xs md:text-sm">Active Users</p>
                            <p class="text-xl font-bold">24</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Video Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5 mb-8">
                <template x-for="video in paginatedVideos" :key="video.id">
                    <div class="video-card shadow-lg hover:shadow-xl">
                        <div class="thumbnail-container cursor-pointer" @click="playVideo(video)">
                            <img :src="resourceBaseUrl + 'thumbnails/' + video.thumbnailFilename" :alt="video.title" class="absolute inset-0 w-full h-full object-cover">
                            <div class="play-overlay">
                                <div class="bg-primary text-white rounded-full w-12 h-12 flex items-center justify-center">
                                    <i class="fas fa-play"></i>
                                </div>
                            </div>
                            <div class="absolute bottom-0 left-0 right-0 p-3">
                                <div class="text-xs text-white bg-primary inline-block px-2 py-1 rounded mb-1" x-text="video.durationFormatted"></div>
                                <h3 class="text-base font-bold text-white truncate" x-text="video.title"></h3>
                            </div>
                        </div>
                        
                        <div class="p-4">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <div class="text-xs text-blue-300 flex items-center">
                                        <i class="fas fa-user mr-1"></i>
                                        <span x-text="video.artist || 'Unknown'"></span>
                                    </div>
                                    <div class="text-xs text-purple-300 flex items-center mt-1">
                                        <i class="fas fa-folder mr-1"></i>
                                        <span x-text="video.category || 'Uncategorized'"></span>
                                    </div>
                                </div>
                                <div class="text-xs text-gray-400" x-text="video.uploadDateFormatted"></div>
                            </div>
                            
                            <p class="text-xs text-gray-300 mb-2 truncate" x-text="video.description || 'No description'"></p>
                            
                            <div class="flex flex-wrap gap-1 mt-3">
                                <div class="text-xs text-gray-400">Tags:</div>
                                <template x-for="tag in video.tags.split(',')" :key="tag">
                                    <span 
                                        @click="addTagFilter(tag.trim())" 
                                        class="tag"
                                        x-text="tag.trim()"
                                    ></span>
                                </template>
                            </div>
                            
                            <!-- Video stats -->
                            <div class="flex justify-between mt-3">
                                <div class="flex space-x-3">
                                    <div class="text-xs flex items-center">
                                        <i class="fas fa-eye mr-1"></i>
                                        <span x-text="video.views || 0"></span>
                                    </div>
                                    <div class="text-xs flex items-center">
                                        <i class="fas fa-heart mr-1"></i>
                                        <span x-text="video.likes ? video.likes.length : 0"></span>
                                    </div>
                                </div>
                                
                                <div x-show="isSuperadmin || video.artist === username">
                                    <button @click="deleteVideo(video)" class="text-red-400 hover:text-red-300 text-sm">
                                        <i class="fas fa-trash mr-1"></i> Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
                
                <template x-if="videos.length === 0">
                    <div class="col-span-full text-center py-10">
                        <div class="inline-block p-4 rounded-full bg-blue-500/10 mb-3">
                            <i class="fas fa-video text-3xl text-accent"></i>
                        </div>
                        <h3 class="text-lg font-bold mb-1">Your video library is empty</h3>
                        <p class="text-gray-400 mb-4">Upload your first video to get started</p>
                        <button @click="openUploadModal" class="bg-primary hover:bg-blue-700 text-white font-medium py-2 px-5 rounded-lg transition" x-show="isSuperadmin || (isLoggedIn && userHasChannel)">
                            <i class="fas fa-cloud-upload-alt mr-2"></i> Upload Video
                        </button>
                    </div>
                </template>
                
                <template x-if="videos.length > 0 && filteredVideos.length === 0">
                    <div class="col-span-full text-center py-10">
                        <div class="inline-block p-4 rounded-full bg-blue-500/10 mb-3">
                            <i class="fas fa-search text-3xl text-accent"></i>
                        </div>
                        <h3 class="text-lg font-bold mb-1">No videos match your filters</h3>
                        <p class="text-gray-400 mb-4">Try adjusting your search or filters</p>
                        <button @click="clearFilters" class="bg-gray-700 hover:bg-gray-600 text-white font-medium py-2 px-5 rounded-lg transition">
                            <i class="fas fa-filter mr-2"></i> Clear Filters
                        </button>
                    </div>
                </template>
            </div>
            
            <!-- Pagination -->
            <div class="flex items-center justify-between bg-card rounded-xl p-3 mt-4">
                <div class="text-xs text-gray-400">
                    Showing <span x-text="(currentPage - 1) * itemsPerPage + 1"></span> to 
                    <span x-text="Math.min(currentPage * itemsPerPage, filteredVideos.length)"></span> of 
                    <span x-text="filteredVideos.length"></span> videos
                </div>
                
                <div class="flex space-x-1">
                    <button @click="prevPage" :disabled="currentPage === 1" class="w-8 h-8 rounded-full bg-blue-500/20 text-blue-400 flex items-center justify-center disabled:opacity-50 hover:bg-blue-500 hover:text-white transition">
                        <i class="fas fa-chevron-left text-xs"></i>
                    </button>
                    
                    <template x-for="page in totalPages" :key="page">
                        <button @click="currentPage = page" :class="{'bg-blue-500 text-white': currentPage === page, 'bg-blue-500/20 text-blue-400': currentPage !== page}" class="w-8 h-8 rounded-full flex items-center justify-center hover:bg-blue-500 hover:text-white transition text-xs" x-text="page"></button>
                    </template>
                    
                    <button @click="nextPage" :disabled="currentPage === totalPages" class="w-8 h-8 rounded-full bg-blue-500/20 text-blue-400 flex items-center justify-center disabled:opacity-50 hover:bg-blue-500 hover:text-white transition">
                        <i class="fas fa-chevron-right text-xs"></i>
                    </button>
                </div>
            </div>
        </main>
    </template>
    
    <!-- Admin Stats View -->
    <template x-if="isSuperadmin && adminView === 'stats'">
        <main class="container mx-auto px-4 py-6">
            <div class="bg-card rounded-xl p-5 mb-6">
                <h2 class="text-xl font-bold mb-6">Platform Statistics</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-gradient-to-r from-primary to-secondary rounded-xl p-5">
                        <div class="text-3xl font-bold mb-2" x-text="videos.length"></div>
                        <div class="text-sm">Total Videos</div>
                        <div class="mt-3">
                            <i class="fas fa-film text-2xl opacity-50"></i>
                        </div>
                    </div>
                    
                    <div class="bg-gradient-to-r from-purple-500 to-purple-700 rounded-xl p-5">
                        <div class="text-3xl font-bold mb-2" x-text="formatFileSize(totalStorage)"></div>
                        <div class="text-sm">Total Storage</div>
                        <div class="mt-3">
                            <i class="fas fa-hdd text-2xl opacity-50"></i>
                        </div>
                    </div>
                    
                    <div class="bg-gradient-to-r from-green-500 to-green-700 rounded-xl p-5">
                        <div class="text-3xl font-bold mb-2">42</div>
                        <div class="text-sm">Registered Users</div>
                        <div class="mt-3">
                            <i class="fas fa-users text-2xl opacity-50"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gray-800 rounded-xl p-5">
                    <h3 class="text-lg font-bold mb-4">Recent Activity</h3>
                    <div class="space-y-3">
                        <div class="flex items-center text-sm">
                            <div class="w-8 h-8 rounded-full bg-blue-500 flex items-center justify-center text-white mr-3">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <div>
                                <div><span class="font-medium">Alex Johnson</span> registered a new account</div>
                                <div class="text-gray-400 text-xs">2 hours ago</div>
                            </div>
                        </div>
                        <div class="flex items-center text-sm">
                            <div class="w-8 h-8 rounded-full bg-purple-500 flex items-center justify-center text-white mr-3">
                                <i class="fas fa-play-circle"></i>
                            </div>
                            <div>
                                <div><span class="font-medium">Sarah Miller</span> watched "Mountain Adventure"</div>
                                <div class="text-gray-400 text-xs">Yesterday</div>
                            </div>
                        </div>
                        <div class="flex items-center text-sm">
                            <div class="w-8 h-8 rounded-full bg-yellow-500 flex items-center justify-center text-white mr-3">
                                <i class="fas fa-birthday-cake"></i>
                            </div>
                            <div>
                                <div>Today is <span class="font-medium">Michael Chen</span>'s birthday!</div>
                                <div class="text-gray-400 text-xs">Today</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </template>
    
    <!-- Admin Channels View -->
    <template x-if="isSuperadmin && adminView === 'channels'">
        <main class="container mx-auto px-4 py-6">
            <div class="bg-card rounded-xl p-5 mb-6">
                <h2 class="text-xl font-bold mb-6">Channel Management</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    <template x-for="(channel, username) in channels" :key="username">
                        <div class="channel-card shadow-lg">
                            <div class="channel-header">
                                <div class="channel-avatar">
                                    <i class="fas fa-user text-white"></i>
                                </div>
                            </div>
                            <div class="p-5 pt-12">
                                <h3 class="text-lg font-bold mb-1" x-text="channel.name"></h3>
                                <p class="text-sm text-gray-400 mb-3" x-text="'@' + username"></p>
                                
                                <div class="flex justify-between mb-3">
                                    <div class="text-sm">
                                        <i class="fas fa-film mr-1"></i>
                                        <span x-text="channel.videos.length"></span> videos
                                    </div>
                                    <div class="text-sm">
                                        <i class="fas fa-users mr-1"></i>
                                        <span x-text="channel.subscribers.length"></span> subscribers
                                    </div>
                                </div>
                                
                                <p class="text-xs text-gray-300 mb-4" x-text="channel.description || 'No description'"></p>
                                
                                <div class="flex justify-between items-center">
                                    <div class="text-xs text-gray-400" x-text="'Created: ' + formatDate(channel.created_at)"></div>
                                    <button @click="deleteChannel(username)" class="text-red-400 hover:text-red-300 text-xs">
                                        <i class="fas fa-trash mr-1"></i> Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                    
                    <template x-if="Object.keys(channels).length === 0">
                        <div class="col-span-full text-center py-10">
                            <div class="inline-block p-4 rounded-full bg-blue-500/10 mb-3">
                                <i class="fas fa-tower-broadcast text-3xl text-accent"></i>
                            </div>
                            <h3 class="text-lg font-bold mb-1">No channels found</h3>
                            <p class="text-gray-400">Channels will appear when users create them</p>
                        </div>
                    </template>
                </div>
            </div>
        </main>
    </template>
    
    <!-- Admin Changes Log View -->
    <template x-if="isSuperadmin && adminView === 'changes'">
        <main class="container mx-auto px-4 py-6">
            <div class="bg-card rounded-xl p-5 mb-6">
                <h2 class="text-xl font-bold mb-6">System Change Log</h2>
                
                <div class="bg-gray-800 rounded-xl p-5">
                    <div class="flex justify-between mb-4">
                        <h3 class="text-lg font-bold">Recent Activity</h3>
                        <button @click="exportChanges" class="bg-primary hover:bg-blue-700 text-white text-sm px-3 py-1 rounded">
                            <i class="fas fa-download mr-1"></i> Export
                        </button>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left border-b border-gray-700">
                                    <th class="pb-2">Timestamp</th>
                                    <th class="pb-2">Action</th>
                                    <th class="pb-2">User</th>
                                    <th class="pb-2">Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="change in changes" :key="change.id">
                                    <tr class="border-b border-gray-700 hover:bg-gray-700/50">
                                        <td class="py-3" x-text="formatDateTime(change.timestamp)"></td>
                                        <td class="py-3 capitalize" x-text="change.action.replace('_', ' ')"></td>
                                        <td class="py-3" x-text="change.user"></td>
                                        <td class="py-3" x-text="change.details"></td>
                                    </tr>
                                </template>
                                
                                <template x-if="changes.length === 0">
                                    <tr>
                                        <td colspan="4" class="py-4 text-center text-gray-400">
                                            No activity recorded yet
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="flex justify-between items-center mt-4">
                        <div class="text-sm text-gray-400">
                            Showing <span x-text="Math.min(changes.length, 50)"></span> of 
                            <span x-text="changes.length"></span> entries
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </template>
    
    <!-- Upload Modal -->
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" x-show="uploadModalOpen" 
         x-transition:enter="fade-enter"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="fade-leave"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        <div class="modal-overlay absolute inset-0" @click="closeUploadModal"></div>
        
        <div class="modal-content relative z-10 max-h-[90vh] overflow-y-auto"
             @click.stop
             x-transition:enter="scale-enter"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="scale-leave"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95">
            <button @click="closeUploadModal" class="absolute top-4 right-4 text-gray-400 hover:text-white z-20">
                <i class="fas fa-times"></i>
            </button>
            
            <div class="p-5">
                <h2 class="text-xl font-bold mb-4 text-center">Upload New Video</h2>
                
                <!-- Upload Progress -->
                <div x-show="uploadProgress > 0" class="mb-4">
                    <div class="text-sm text-gray-400 mb-1">Uploading: <span x-text="uploadProgress + '%'"></span></div>
                    <div class="w-full bg-gray-700 rounded-full h-2">
                        <div class="progress-bar h-2 rounded-full" :style="'width:' + uploadProgress + '%'"></div>
                    </div>
                </div>
                
                <!-- File Drop Zone -->
                <form id="upload-form" method="post" enctype="multipart/form-data" class="pb-4">
                    <input type="hidden" name="action" value="upload">
                    <input type="hidden" name="thumbnail" id="thumbnail-data">
                    <input type="hidden" name="duration" id="video-duration">
                    <div class="drop-zone rounded-xl p-6 mb-4 cursor-pointer text-center"
                         @dragover.prevent="uploadDragover = true" 
                         @dragleave="uploadDragover = false"
                         @drop.prevent="handleVideoDrop($event)"
                         @click="document.getElementById('video-file').click()"
                         :class="{'dragover': uploadDragover}">
                        <input type="file" id="video-file" name="video" class="hidden" accept="video/*" @change="handleVideoUpload">
                        <div class="text-center">
                            <i class="fas fa-cloud-upload-alt text-4xl text-accent mb-3"></i>
                            <h3 class="text-lg font-bold mb-1">Drag & Drop your video file</h3>
                            <p class="text-gray-400 mb-2">or click to browse files</p>
                            <p class="text-xs text-gray-500">Supported formats: MP4, WebM, MOV (Max 100MB)</p>
                        </div>
                    </div>
                    
                    <!-- File Info -->
                    <div x-show="uploadedVideo" class="mb-4">
                        <div class="flex items-center bg-gray-800 p-3 rounded-lg">
                            <i class="fas fa-file-video text-xl text-accent mr-2"></i>
                            <div class="flex-grow">
                                <div class="font-medium text-sm" x-text="uploadedVideo.name"></div>
                                <div class="text-xs text-gray-400" x-text="formatFileSize(uploadedVideo.size)"></div>
                            </div>
                            <button @click="uploadedVideo = null" class="text-gray-500 hover:text-white">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Thumbnail Preview -->
                    <div x-show="thumbnailPreview" class="mb-4 max-h-60 overflow-hidden">
                        <div class="text-sm text-gray-400 mb-1">Thumbnail Preview</div>
                        <img :src="thumbnailPreview" alt="Thumbnail preview" class="w-full rounded-lg border border-gray-700">
                    </div>
                    
                    <!-- Metadata Form -->
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Title *</label>
                            <input type="text" name="title" required 
                                   class="w-full p-2.5 rounded-lg bg-gray-800 border border-gray-700 text-white transition focus:border-accent focus:outline-none">
                        </div>
                        
                        <template x-if="isSuperadmin">
                            <div>
                                <label class="block text-sm font-medium mb-1">Creator *</label>
                                <input type="text" name="artist" required 
                                       class="w-full p-2.5 rounded-lg bg-gray-800 border border-gray-700 text-white transition focus:border-accent focus:outline-none">
                            </div>
                        </template>
                        
                        <div>
                            <label class="block text-sm font-medium mb-1">Category *</label>
                            <select name="category" required 
                                    class="w-full p-2.5 rounded-lg bg-gray-800 border border-gray-700 text-white transition focus:border-accent focus:outline-none">
                                <option value="">Select a category</option>
                                <option value="Music">Music</option>
                                <option value="Gaming">Gaming</option>
                                <option value="Education">Education</option>
                                <option value="Entertainment">Entertainment</option>
                                <option value="Technology">Technology</option>
                                <option value="Sports">Sports</option>
                                <option value="Travel">Travel</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-1">Description</label>
                            <textarea name="description" rows="2" 
                                      class="w-full p-2.5 rounded-lg bg-gray-800 border border-gray-700 text-white transition focus:border-accent focus:outline-none"></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-1">Tags (comma separated)</label>
                            <input type="text" name="tags" 
                                   class="w-full p-2.5 rounded-lg bg-gray-800 border border-gray-700 text-white transition focus:border-accent focus:outline-none"
                                   placeholder="e.g., nature, tutorial, 4k">
                        </div>
                        
                        <div class="flex justify-end space-x-2 pt-2">
                            <button @click="closeUploadModal" type="button" 
                                    class="px-4 py-2 rounded-lg border border-gray-600 text-gray-300 hover:bg-gray-700 text-sm">
                                Cancel
                            </button>
                            <button type="submit" :disabled="isUploading || !uploadedVideo || !thumbnailPreview"
                                    class="bg-primary hover:bg-blue-700 px-4 py-2 rounded-lg text-white transition text-sm disabled:opacity-50">
                                <span x-show="!isUploading">Upload Video</span>
                                <span x-show="isUploading">Uploading...</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Video Player Modal -->
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" x-show="playerModalOpen" 
         x-transition:enter="fade-enter"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="fade-leave"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        <div class="modal-overlay absolute inset-0" @click="closePlayer"></div>
        
        <div class="player-container relative z-10 w-full max-w-4xl"
             @click.stop
             x-transition:enter="scale-enter"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="scale-leave"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95">
            <button @click="closePlayer" class="absolute top-4 right-4 text-gray-400 hover:text-white z-20">
                <i class="fas fa-times text-2xl"></i>
            </button>
            
            <div class="bg-card rounded-xl overflow-hidden">
                <div class="relative" style="padding-top: 56.25%;">
                    <video 
                        x-ref="videoPlayer"
                        class="absolute top-0 left-0 w-full h-full"
                        controls
                        autoplay
                    ></video>
                    
                    <!-- Resolution menu -->
                    <div class="res-menu" x-show="showResolutionMenu" @click.outside="showResolutionMenu = false">
                        <template x-for="res in availableResolutions">
                            <div 
                                @click="changeResolution(res)" 
                                class="res-option"
                                :class="{'active': currentResolution === res}"
                                x-text="res"
                            ></div>
                        </template>
                    </div>
                    
                    <!-- Subtitles menu -->
                    <div class="sub-menu" x-show="showSubtitlesMenu" @click.outside="showSubtitlesMenu = false">
                        <div 
                            @click="changeSubtitles('none')" 
                            class="sub-option"
                            :class="{'active': currentSubtitles === 'none'}"
                        >
                            No subtitles
                        </div>
                        <template x-for="(sub, lang) in availableSubtitles">
                            <div 
                                @click="changeSubtitles(lang)" 
                                class="sub-option"
                                :class="{'active': currentSubtitles === lang}"
                                x-text="lang.toUpperCase()"
                            ></div>
                        </template>
                    </div>
                    
                    <!-- Player controls -->
                    <div class="player-controls">
                        <div class="flex space-x-2">
                            <button @click="showResolutionMenu = !showResolutionMenu" class="control-btn">
                                <i class="fas fa-expand"></i>
                                <span x-text="currentResolution"></span>
                            </button>
                            <button @click="showSubtitlesMenu = !showSubtitlesMenu" class="control-btn">
                                <i class="fas fa-closed-captioning"></i>
                                <span x-text="currentSubtitles === 'none' ? 'Subtitles' : currentSubtitles.toUpperCase()"></span>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="p-5">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h2 class="text-xl font-bold mb-2" x-text="currentVideo.title"></h2>
                            <div class="flex items-center">
                                <span class="text-blue-400 font-medium" x-text="currentVideo.artist || 'Unknown'"></span>
                                <!-- Subscribe button -->
                                <button x-show="currentVideo.artist && currentVideo.artist !== username" 
                                        @click="toggleSubscribe(currentVideo.artist)"
                                        :class="{'subscribe-btn': true, 'subscribed': isSubscribed(currentVideo.artist)}"
                                        class="ml-3 text-sm">
                                    <span x-text="isSubscribed(currentVideo.artist) ? 'Subscribed' : 'Subscribe'"></span>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Video stats -->
                        <div class="flex items-center space-x-3">
                            <div class="flex items-center">
                                <i class="fas fa-eye mr-1 text-gray-400"></i>
                                <span x-text="currentVideo.views || 0" class="text-sm"></span>
                            </div>
                            <button @click="toggleLike(currentVideo.id)" class="flex items-center like-btn" :class="{'liked': isLiked(currentVideo.id)}">
                                <i class="fas fa-heart mr-1"></i>
                                <span x-text="currentVideo.likes ? currentVideo.likes.length : 0"></span>
                            </button>
                        </div>
                    </div>
                    
                    <div class="flex flex-wrap gap-4 mb-4">
                        <div class="text-sm">
                            <i class="fas fa-clock mr-2 text-blue-400"></i>
                            <span x-text="currentVideo.durationFormatted"></span>
                        </div>
                        <div class="text-sm">
                            <i class="fas fa-calendar mr-2 text-blue-400"></i>
                            <span x-text="currentVideo.uploadDateFormatted"></span>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <span class="text-sm font-semibold text-blue-400">Category:</span>
                        <span class="text-sm ml-2" x-text="currentVideo.category || 'Uncategorized'"></span>
                    </div>
                    
                    <div class="mb-4">
                        <span class="text-sm font-semibold text-blue-400">Tags:</span>
                        <div class="flex flex-wrap mt-1 gap-2">
                            <template x-for="tag in currentVideo.tags.split(',')" :key="tag">
                                <span 
                                    @click="addTagFilter(tag.trim())" 
                                    class="tag"
                                    x-text="tag.trim()"
                                ></span>
                            </template>
                        </div>
                    </div>
                    
                    <p class="text-gray-300" x-text="currentVideo.description || 'No description available'"></p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Channel Dashboard Modal -->
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" x-show="channelDashboardOpen" 
         x-transition:enter="fade-enter"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="fade-leave"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        <div class="modal-overlay absolute inset-0" @click="channelDashboardOpen = false"></div>
        
        <div class="modal-content relative z-10 w-full max-w-4xl max-h-[90vh] overflow-y-auto"
             @click.stop
             x-transition:enter="scale-enter"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="scale-leave"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95">
            <button @click="channelDashboardOpen = false" class="absolute top-4 right-4 text-gray-400 hover:text-white z-20">
                <i class="fas fa-times"></i>
            </button>
            
            <div class="p-5">
                <h2 class="text-2xl font-bold mb-6 text-center" x-text="channelDashboard.title"></h2>
                
                <!-- Channel Stats -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div class="stat-card">
                        <div class="stat-value" x-text="channelDashboard.videos"></div>
                        <div class="stat-label">Videos</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" x-text="channelDashboard.views"></div>
                        <div class="stat-label">Total Views</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" x-text="channelDashboard.likes"></div>
                        <div class="stat-label">Total Likes</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" x-text="channelDashboard.subscribers"></div>
                        <div class="stat-label">Subscribers</div>
                    </div>
                </div>
                
                <!-- Channel Videos -->
                <h3 class="text-xl font-bold mb-4">Your Videos</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <template x-for="video in channelVideos" :key="video.id">
                        <div class="video-card shadow-lg">
                            <div class="thumbnail-container cursor-pointer" @click="playVideo(video)">
                                <img :src="resourceBaseUrl + 'thumbnails/' + video.thumbnailFilename" :alt="video.title" class="absolute inset-0 w-full h-full object-cover">
                                <div class="play-overlay">
                                    <div class="bg-primary text-white rounded-full w-12 h-12 flex items-center justify-center">
                                        <i class="fas fa-play"></i>
                                    </div>
                                </div>
                                <div class="absolute bottom-0 left-0 right-0 p-3">
                                    <div class="text-xs text-white bg-primary inline-block px-2 py-1 rounded mb-1" x-text="video.durationFormatted"></div>
                                    <h3 class="text-base font-bold text-white truncate" x-text="video.title"></h3>
                                </div>
                            </div>
                            
                            <div class="p-4">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <div class="text-xs text-blue-300 flex items-center">
                                            <i class="fas fa-folder mr-1"></i>
                                            <span x-text="video.category || 'Uncategorized'"></span>
                                        </div>
                                    </div>
                                    <div class="text-xs text-gray-400" x-text="video.uploadDateFormatted"></div>
                                </div>
                                
                                <p class="text-xs text-gray-300 mb-2 truncate" x-text="video.description || 'No description'"></p>
                                
                                <div class="flex justify-between mt-3">
                                    <div class="flex space-x-3">
                                        <div class="text-xs flex items-center">
                                            <i class="fas fa-eye mr-1"></i>
                                            <span x-text="video.views || 0"></span>
                                        </div>
                                        <div class="text-xs flex items-center">
                                            <i class="fas fa-heart mr-1"></i>
                                            <span x-text="video.likes ? video.likes.length : 0"></span>
                                        </div>
                                    </div>
                                    
                                    <!-- Remove button for channel owner -->
                                    <button @click="removeFromChannel(video.id)" class="remove-btn">
                                        <i class="fas fa-times mr-1"></i> Remove
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                    
                    <template x-if="channelVideos.length === 0">
                        <div class="col-span-full text-center py-5">
                            <div class="inline-block p-4 rounded-full bg-blue-500/10 mb-3">
                                <i class="fas fa-video-slash text-2xl text-accent"></i>
                            </div>
                            <h3 class="text-lg font-bold mb-1">No videos uploaded yet</h3>
                            <p class="text-gray-400">Upload your first video to get started</p>
                        </div>
                    </template>
                </div>
                
                <!-- Superadmin Stats -->
                <template x-if="isSuperadmin">
                    <div class="mt-8">
                        <h3 class="text-xl font-bold mb-4">Platform Statistics</h3>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="stat-card">
                                <div class="stat-value" x-text="platformStats.totalUsers"></div>
                                <div class="stat-label">Total Users</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value" x-text="platformStats.totalVideos"></div>
                                <div class="stat-label">Total Videos</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value" x-text="platformStats.totalChannels"></div>
                                <div class="stat-label">Channels</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value" x-text="platformStats.totalViews"></div>
                                <div class="stat-label">Total Views</div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
    
    <!-- Channel Creation Modal -->
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" x-show="channelCreationOpen" 
         x-transition:enter="fade-enter"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="fade-leave"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        <div class="modal-overlay absolute inset-0" @click="channelCreationOpen = false"></div>
        
        <div class="modal-content relative z-10 max-w-md w-full"
             @click.stop
             x-transition:enter="scale-enter"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="scale-leave"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95">
            <button @click="channelCreationOpen = false" class="absolute top-4 right-4 text-gray-400 hover:text-white z-20">
                <i class="fas fa-times"></i>
            </button>
            
            <div class="p-6">
                <div class="text-center mb-6">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gradient-to-r from-purple-500 to-pink-500 mb-3">
                        <i class="fas fa-tower-broadcast text-2xl text-white"></i>
                    </div>
                    <h2 class="text-2xl font-bold">Create Your Channel</h2>
                    <p class="text-gray-400 mt-1">Start sharing your content with the world</p>
                </div>
                
                <form @submit.prevent="createChannel">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Channel Name *</label>
                            <input type="text" x-model="channelName" required 
                                   class="w-full p-3 rounded-lg bg-gray-800 border border-gray-700 text-white transition focus:border-accent focus:outline-none"
                                   placeholder="Your channel name">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-1">Description</label>
                            <textarea x-model="channelDescription" rows="3"
                                      class="w-full p-3 rounded-lg bg-gray-800 border border-gray-700 text-white transition focus:border-accent focus:outline-none"
                                      placeholder="Tell viewers about your channel"></textarea>
                        </div>
                        
                        <div class="flex justify-end space-x-2 pt-2">
                            <button @click="channelCreationOpen = false" type="button" 
                                    class="px-4 py-2 rounded-lg border border-gray-600 text-gray-300 hover:bg-gray-700 text-sm">
                                Cancel
                            </button>
                            <button type="submit" 
                                    class="bg-gradient-to-r from-purple-500 to-pink-500 hover:opacity-90 px-4 py-2 rounded-lg text-white transition text-sm">
                                Create Channel
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Notifications -->
    <div class="notification success-notification" :class="{'show': successNotification}">
        <div class="flex items-center">
            <i class="fas fa-check-circle text-lg mr-2"></i>
            <span x-text="notificationMessage"></span>
        </div>
    </div>

    <div class="notification error-notification" :class="{'show': errorNotification}">
        <div class="flex items-center">
            <i class="fas fa-exclamation-circle text-lg mr-2"></i>
            <span x-text="notificationMessage"></span>
        </div>
    </div>

    <div class="notification admin-notification" :class="{'show': adminNotification}">
        <div class="flex items-center">
            <i class="fas fa-shield-alt text-lg mr-2"></i>
            <span x-text="notificationMessage"></span>
        </div>
    </div>

    <div class="notification birthday-notification" :class="{'show': birthdayNotification}">
        <div class="flex items-center">
            <i class="fas fa-birthday-cake text-lg mr-2"></i>
            <span x-text="birthdayMessage"></span>
        </div>
    </div>

    <script>
        function videoHubApp() {
            return {
                // Initial data
                videos: [],
                channels: {},
                changes: [],
                resourceBaseUrl: '',
                itemsPerPage: 8,
                currentPage: 1,
                uploadModalOpen: false,
                playerModalOpen: false,
                loginModalOpen: false,
                registerModalOpen: false,
                channelDashboardOpen: false,
                channelCreationOpen: false,
                uploadDragover: false,
                uploadedVideo: null,
                currentVideo: null,
                successNotification: false,
                errorNotification: false,
                adminNotification: false,
                birthdayNotification: false,
                notificationMessage: '',
                birthdayMessage: '',
                uploadProgress: 0,
                searchTerm: '',
                sortBy: 'date',
                activeFilters: [],
                thumbnailPreview: null,
                videoDuration: 0,
                isUploading: false,
                isSuperadmin: false,
                isLoggedIn: false,
                username: '',
                loginUsername: '',
                loginPassword: '',
                registerUsername: '',
                registerPassword: '',
                registerPasswordConfirm: '',
                registerFirstName: '',
                registerLastName: '',
                registerEmail: '',
                registerPhone: '',
                registerBirthdate: '',
                adminView: 'videos',
                userFormOpen: false,
                hasBirthdayToday: false,
                showResolutionMenu: false,
                showSubtitlesMenu: false,
                currentResolution: '1080p',
                currentSubtitles: 'none',
                availableResolutions: ['1080p', '720p', '480p', '360p'],
                availableSubtitles: {},
                
                // Channel dashboard data
                channelDashboard: {
                    title: '',
                    videos: 0,
                    views: 0,
                    likes: 0,
                    subscribers: 0
                },
                channelVideos: [],
                platformStats: {
                    totalUsers: 0,
                    totalVideos: 0,
                    totalChannels: 0,
                    totalViews: 0
                },
                channelName: '',
                channelDescription: '',
                
                // Computed properties
                get filteredVideos() {
                    let filtered = [...this.videos];
                    
                    // Apply search term
                    if (this.searchTerm) {
                        const term = this.searchTerm.toLowerCase();
                        filtered = filtered.filter(video => 
                            video.title.toLowerCase().includes(term) ||
                            video.description.toLowerCase().includes(term) ||
                            video.artist.toLowerCase().includes(term) ||
                            video.tags.toLowerCase().includes(term) ||
                            video.category.toLowerCase().includes(term)
                        );
                    }
                    
                    // Apply active filters
                    if (this.activeFilters.length > 0) {
                        this.activeFilters.forEach(filter => {
                            if (filter.type === 'category') {
                                filtered = filtered.filter(video => video.category === filter.value);
                            } else if (filter.type === 'artist') {
                                filtered = filtered.filter(video => video.artist === filter.value);
                            } else if (filter.type === 'tag') {
                                filtered = filtered.filter(video => 
                                    video.tags.toLowerCase().includes(filter.value.toLowerCase())
                                );
                            }
                        });
                    }
                    
                    // Apply sorting
                    if (this.sortBy === 'title-asc') {
                        filtered.sort((a, b) => a.title.localeCompare(b.title));
                    } else if (this.sortBy === 'title-desc') {
                        filtered.sort((a, b) => b.title.localeCompare(a.title));
                    } else if (this.sortBy === 'length-asc') {
                        filtered.sort((a, b) => a.duration - b.duration);
                    } else if (this.sortBy === 'length-desc') {
                        filtered.sort((a, b) => b.duration - a.duration);
                    } else if (this.sortBy === 'date') {
                        filtered.sort((a, b) => new Date(b.uploadDate) - new Date(a.uploadDate));
                    }
                    
                    return filtered;
                },
                
                get paginatedVideos() {
                    const start = (this.currentPage - 1) * this.itemsPerPage;
                    const end = start + this.itemsPerPage;
                    return this.filteredVideos.slice(start, end);
                },
                
                get totalPages() {
                    return Math.ceil(this.filteredVideos.length / this.itemsPerPage);
                },
                
                get totalStorage() {
                    return this.videos.reduce((sum, video) => sum + (video.fileSize || 0), 0);
                },
                
                get userHasChannel() {
                    return this.isLoggedIn && this.channels[this.username] !== undefined;
                },
                
                // Methods
                init(initialVideos, resourceBaseUrl, isSuperadmin, hasBirthday, initialChannels, initialChanges) {
                    this.videos = initialVideos;
                    this.resourceBaseUrl = resourceBaseUrl;
                    this.isSuperadmin = isSuperadmin;
                    this.isLoggedIn = isSuperadmin;
                    this.username = isSuperadmin ? 'superadmin' : '';
                    this.hasBirthdayToday = hasBirthday;
                    this.channels = initialChannels;
                    this.changes = initialChanges.changes || [];
                    
                    if (this.changes.length > 50) {
                        this.changes = this.changes.slice(0, 50);
                    }
                    
                    if (this.isSuperadmin) {
                        this.showNotification('Superadmin privileges activated', 'admin');
                    }
                    
                    // Add event listener for form submission
                    const uploadForm = document.getElementById('upload-form');
                    if (uploadForm) {
                        uploadForm.addEventListener('submit', (e) => {
                            e.preventDefault();
                            
                            // Prevent multiple submissions
                            if (this.isUploading) return;
                            
                            if (!this.uploadedVideo) {
                                this.showNotification('Please upload a video file', 'error');
                                return;
                            }
                            
                            if (!this.thumbnailPreview) {
                                this.showNotification('Please wait for thumbnail generation', 'error');
                                return;
                            }
                            
                            this.isUploading = true;
                            this.uploadProgress = 0;
                            
                            // Create FormData and include the video file
                            const formData = new FormData(uploadForm);
                            if (this.uploadedVideo) {
                                formData.set('video', this.uploadedVideo);
                            }
                            
                            // Use XMLHttpRequest for real progress tracking
                            const xhr = new XMLHttpRequest();
                            
                            // Progress tracking
                            xhr.upload.addEventListener('progress', (event) => {
                                if (event.lengthComputable) {
                                    this.uploadProgress = Math.round(
                                        (event.loaded / event.total) * 100
                                    );
                                }
                            });
                            
                            xhr.onreadystatechange = () => {
                                if (xhr.readyState === XMLHttpRequest.DONE) {
                                    this.isUploading = false;
                                    
                                    if (xhr.status === 200) {
                                        try {
                                            const data = JSON.parse(xhr.responseText);
                                            if (data.success) {
                                                this.showNotification(data.message, 'success');
                                                
                                                // Add the new video to the local state
                                                this.videos.unshift(data.video);
                                                
                                                // Reset form and state
                                                this.uploadedVideo = null;
                                                this.thumbnailPreview = null;
                                                uploadForm.reset();
                                                
                                                setTimeout(() => {
                                                    this.closeUploadModal();
                                                }, 1500);
                                            } else {
                                                this.showNotification(data.message, 'error');
                                            }
                                        } catch (e) {
                                            this.showNotification('Error parsing response: ' + e.message, 'error');
                                        }
                                    } else {
                                        this.showNotification('Upload failed with status: ' + xhr.status, 'error');
                                    }
                                }
                            };
                            
                            xhr.onerror = () => {
                                this.isUploading = false;
                                this.showNotification('Upload failed. Please check your connection.', 'error');
                            };
                            
                            xhr.open('POST', '');
                            xhr.send(formData);
                        });
                    }
                    
                    // Show birthday notification if applicable
                    if (this.hasBirthdayToday) {
                        this.birthdayMessage = "Happy Birthday! Enjoy your special day with VideoHub!";
                        this.birthdayNotification = true;
                        setTimeout(() => {
                            this.birthdayNotification = false;
                        }, 10000);
                    }
                },
                
                openUploadModal() {
                    this.uploadModalOpen = true;
                    this.uploadedVideo = null;
                    this.thumbnailPreview = null;
                    this.uploadProgress = 0;
                    this.isUploading = false;
                },
                
                closeUploadModal() {
                    this.uploadModalOpen = false;
                },
                
                handleVideoDrop(event) {
                    this.uploadDragover = false;
                    const file = event.dataTransfer.files[0];
                    if (file && file.type.startsWith('video/') && file.size <= 100 * 1024 * 1024) {
                        this.uploadedVideo = file;
                        this.generateThumbnail(file);
                    } else if (file.size > 100 * 1024 * 1024) {
                        this.showNotification('File size exceeds 100MB limit', 'error');
                    }
                },
                
                handleVideoUpload(event) {
                    const file = event.target.files[0];
                    if (file && file.type.startsWith('video/') && file.size <= 100 * 1024 * 1024) {
                        this.uploadedVideo = file;
                        this.generateThumbnail(file);
                    } else if (file.size > 100 * 1024 * 1024) {
                        this.showNotification('File size exceeds 100MB limit', 'error');
                    }
                },
                
                generateThumbnail(file) {
                    if (!file) return;
                    
                    const video = document.createElement('video');
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');
                    
                    video.src = URL.createObjectURL(file);
                    video.muted = true;
                    
                    // First event: when metadata is loaded
                    video.addEventListener('loadedmetadata', () => {
                        // Set canvas dimensions
                        canvas.width = video.videoWidth;
                        canvas.height = video.videoHeight;
                        
                        // Seek to 25% of video duration for thumbnail
                        video.currentTime = Math.min(video.duration * 0.25, 10);
                    });
                    
                    // Second event: when the seek operation completes
                    video.addEventListener('seeked', () => {
                        try {
                            // Draw video frame to canvas
                            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                            
                            // Get base64 data
                            this.thumbnailPreview = canvas.toDataURL('image/jpeg');
                            document.getElementById('thumbnail-data').value = this.thumbnailPreview;
                            
                            // Get duration
                            this.videoDuration = Math.round(video.duration);
                            document.getElementById('video-duration').value = this.videoDuration;
                        } catch (e) {
                            console.error('Thumbnail generation error:', e);
                            this.showNotification('Error generating thumbnail: ' + e.message, 'error');
                        } finally {
                            // Clean up
                            URL.revokeObjectURL(video.src);
                        }
                    });
                    
                    video.addEventListener('error', (e) => {
                        this.showNotification('Error processing video: ' + e.message, 'error');
                    });
                    
                    video.load();
                },
                
                playVideo(video) {
                    this.currentVideo = video;
                    this.playerModalOpen = true;
                    this.currentResolution = '1080p';
                    this.currentSubtitles = 'none';
                    this.availableSubtitles = video.subtitles || {};
                    
                    // Increment view count
                    this.incrementViews(video.id);
                    
                    // Wait for modal to be visible before initializing player
                    this.$nextTick(() => {
                        this.setupVideoPlayer();
                    });
                },
                
                setupVideoPlayer() {
                    const player = this.$refs.videoPlayer;
                    if (!player) {
                        this.showNotification('Video player element not found', 'error');
                        return;
                    }
                    
                    // Reset the video element
                    player.pause();
                    player.innerHTML = '';
                    
                    // Create a new source element
                    const source = document.createElement('source');
                    source.src = this.resourceBaseUrl + 'videos/' + this.currentVideo.videoFilename;
                    source.type = this.getVideoMimeType(this.currentVideo.videoFilename);
                    
                    // Remove any existing sources
                    while (player.firstChild) {
                        player.removeChild(player.firstChild);
                    }
                    
                    // Add the new source
                    player.appendChild(source);
                    
                    // Add subtitle tracks if available
                    for (const [lang, subFile] of Object.entries(this.availableSubtitles)) {
                        const track = document.createElement('track');
                        track.kind = 'subtitles';
                        track.src = this.resourceBaseUrl + 'subtitles/' + subFile;
                        track.srclang = lang;
                        track.label = lang.toUpperCase();
                        player.appendChild(track);
                    }
                    
                    // Reload the video element
                    player.load();
                    
                    // Attempt playback
                    const playPromise = player.play();
                    
                    if (playPromise !== undefined) {
                        playPromise.catch(e => {
                            console.error('Playback failed:', e);
                            this.showNotification('Error playing video: ' + e.message, 'error');
                        });
                    }
                },
                
                // Helper to get MIME type from extension
                getVideoMimeType(filename) {
                    const ext = filename.split('.').pop().toLowerCase();
                    switch(ext) {
                        case 'mp4': return 'video/mp4';
                        case 'mov': return 'video/quicktime';
                        case 'webm': return 'video/webm';
                        default: return 'video/mp4';
                    }
                },
                
                closePlayer() {
                    this.playerModalOpen = false;
                    this.showResolutionMenu = false;
                    this.showSubtitlesMenu = false;
                    if (this.$refs.videoPlayer) {
                        this.$refs.videoPlayer.pause();
                        this.$refs.videoPlayer.removeAttribute('src');
                    }
                },
                
                changeResolution(resolution) {
                    this.currentResolution = resolution;
                    this.showResolutionMenu = false;
                    this.showNotification('Resolution changed to ' + resolution, 'success');
                },
                
                changeSubtitles(lang) {
                    this.currentSubtitles = lang;
                    this.showSubtitlesMenu = false;
                    
                    const player = this.$refs.videoPlayer;
                    if (!player) return;
                    
                    // Enable/disable subtitle tracks
                    const tracks = player.querySelectorAll('track');
                    tracks.forEach(track => {
                        track.track.mode = track.srclang === lang ? 'showing' : 'disabled';
                    });
                    
                    if (lang !== 'none') {
                        this.showNotification('Subtitles enabled: ' + lang.toUpperCase(), 'success');
                    }
                },
                
                deleteVideo(video) {
                    if (confirm(`Are you sure you want to delete "${video.title}"? This cannot be undone.`)) {
                        const formData = new FormData();
                        formData.append('action', 'delete');
                        formData.append('id', video.id);
                        
                        fetch('', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                this.showNotification(data.message, 'success');
                                // Remove video from local state
                                this.videos = this.videos.filter(v => v.id !== video.id);
                            } else {
                                this.showNotification(data.message, 'error');
                            }
                        })
                        .catch(error => {
                            this.showNotification('Delete failed: ' + error.message, 'error');
                        });
                    }
                },
                
                deleteChannel(username) {
                    if (confirm(`Are you sure you want to delete channel "${username}"? This cannot be undone.`)) {
                        const formData = new FormData();
                        formData.append('action', 'delete_channel');
                        formData.append('username', username);
                        
                        fetch('', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                this.showNotification(data.message, 'success');
                                // Remove channel from local state
                                delete this.channels[username];
                            } else {
                                this.showNotification(data.message, 'error');
                            }
                        })
                        .catch(error => {
                            this.showNotification('Delete failed: ' + error.message, 'error');
                        });
                    }
                },
                
                login() {
                    const formData = new FormData();
                    formData.append('action', 'login');
                    formData.append('username', this.loginUsername);
                    formData.append('password', this.loginPassword);
                    
                    fetch('', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            this.showNotification(data.message, 'success');
                            this.isLoggedIn = true;
                            this.username = this.loginUsername;
                            this.isSuperadmin = (this.loginUsername === 'superadmin');
                            this.loginModalOpen = false;
                            
                            if (data.is_birthday) {
                                this.birthdayMessage = data.message.split('! ')[1];
                                this.birthdayNotification = true;
                                setTimeout(() => {
                                    this.birthdayNotification = false;
                                }, 10000);
                            }
                            
                            if (this.isSuperadmin) {
                                this.showNotification('Superadmin privileges activated', 'admin');
                            }
                        } else {
                            this.showNotification(data.message, 'error');
                        }
                    })
                    .catch(error => {
                        this.showNotification('Login failed: ' + error.message, 'error');
                    });
                },
                
                register() {
                    if (this.registerPassword !== this.registerPasswordConfirm) {
                        this.showNotification('Passwords do not match', 'error');
                        return;
                    }
                    
                    const formData = new FormData();
                    formData.append('action', 'register');
                    formData.append('username', this.registerUsername);
                    formData.append('password', this.registerPassword);
                    formData.append('email', this.registerEmail);
                    formData.append('first_name', this.registerFirstName);
                    formData.append('last_name', this.registerLastName);
                    formData.append('phone', this.registerPhone);
                    formData.append('birthdate', this.registerBirthdate);
                    
                    fetch('', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            this.showNotification(data.message, 'success');
                            this.registerModalOpen = false;
                            this.loginModalOpen = true;
                            this.loginUsername = this.registerUsername;
                        } else {
                            this.showNotification(data.message, 'error');
                        }
                    })
                    .catch(error => {
                        this.showNotification('Registration failed: ' + error.message, 'error');
                    });
                },
                
                logout() {
                    const formData = new FormData();
                    formData.append('action', 'logout');
                    
                    fetch('', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            this.showNotification('Logout successful!', 'success');
                            this.isLoggedIn = false;
                            this.username = '';
                            this.isSuperadmin = false;
                        }
                    })
                    .catch(error => {
                        this.showNotification('Logout failed: ' + error.message, 'error');
                    });
                },
                
                showRegisterInstead() {
                    this.loginModalOpen = false;
                    this.registerModalOpen = true;
                },
                
                showLoginInstead() {
                    this.registerModalOpen = false;
                    this.loginModalOpen = true;
                },
                
                prevPage() {
                    if (this.currentPage > 1) {
                        this.currentPage--;
                    }
                },
                
                nextPage() {
                    if (this.currentPage < this.totalPages) {
                        this.currentPage++;
                    }
                },
                
                formatFileSize(bytes) {
                    if (!bytes) return '0 bytes';
                    if (bytes < 1024) return bytes + ' bytes';
                    else if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
                    else return (bytes / 1048576).toFixed(1) + ' MB';
                },
                
                showNotification(message, type = 'success') {
                    this.notificationMessage = message;
                    
                    if (type === 'success') {
                        this.successNotification = true;
                        setTimeout(() => {
                            this.successNotification = false;
                        }, 3000);
                    } else if (type === 'error') {
                        this.errorNotification = true;
                        setTimeout(() => {
                            this.errorNotification = false;
                        }, 3000);
                    } else if (type === 'admin') {
                        this.adminNotification = true;
                        setTimeout(() => {
                            this.adminNotification = false;
                        }, 3000);
                    } else if (type === 'birthday') {
                        this.birthdayNotification = true;
                        setTimeout(() => {
                            this.birthdayNotification = false;
                        }, 10000);
                    }
                },
                
                addTagFilter(tag) {
                    // Check if this tag filter already exists
                    if (this.activeFilters.some(f => f.type === 'tag' && f.value === tag)) return;
                    
                    this.activeFilters.push({
                        type: 'tag',
                        value: tag,
                        label: 'Tag'
                    });
                },
                
                removeFilter(filter) {
                    this.activeFilters = this.activeFilters.filter(f => 
                        !(f.type === filter.type && f.value === filter.value)
                    );
                },
                
                clearFilters() {
                    this.activeFilters = [];
                    this.searchTerm = '';
                },
                
                // Open channel dashboard
                openChannelDashboard() {
                    if (!this.isLoggedIn) return;
                    
                    // Check if user has a channel
                    if (!this.userHasChannel) {
                        this.channelCreationOpen = true;
                        return;
                    }
                    
                    this.loadChannelDashboard();
                    this.channelDashboardOpen = true;
                },
                
                // Load channel dashboard data
                loadChannelDashboard() {
                    // Get user's videos
                    this.channelVideos = this.videos.filter(v => v.artist === this.username);
                    
                    // Get channel data
                    const channel = this.channels[this.username];
                    
                    // Calculate stats
                    this.channelDashboard = {
                        title: `${this.username}'s Channel`,
                        videos: this.channelVideos.length,
                        views: this.channelVideos.reduce((sum, v) => sum + (v.views || 0), 0),
                        likes: this.channelVideos.reduce((sum, v) => sum + (v.likes ? v.likes.length : 0), 0),
                        subscribers: channel ? channel.subscribers.length : 0
                    };
                    
                    // Load superadmin stats
                    if (this.isSuperadmin) {
                        this.platformStats = {
                            totalUsers: Object.keys(this.users).length,
                            totalVideos: this.videos.length,
                            totalChannels: Object.keys(this.channels).length,
                            totalViews: this.videos.reduce((sum, v) => sum + (v.views || 0), 0)
                        };
                    }
                },
                
                // Create channel
                createChannel() {
                    if (!this.isLoggedIn) {
                        this.showNotification('You must be logged in', 'error');
                        return;
                    }
                    
                    const formData = new FormData();
                    formData.append('action', 'create_channel');
                    formData.append('name', this.channelName);
                    formData.append('description', this.channelDescription);
                    
                    fetch('', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            this.showNotification(data.message, 'success');
                            this.channelCreationOpen = false;
                            
                            // Add channel to local state
                            this.channels[this.username] = {
                                owner: this.username,
                                name: this.channelName,
                                description: this.channelDescription,
                                created_at: new Date().toISOString(),
                                subscribers: [],
                                videos: []
                            };
                            
                            // Load dashboard
                            this.loadChannelDashboard();
                            this.channelDashboardOpen = true;
                        } else {
                            this.showNotification(data.message, 'error');
                        }
                    })
                    .catch(error => {
                        this.showNotification('Channel creation failed: ' + error.message, 'error');
                    });
                },
                
                // Toggle like on video
                toggleLike(videoId) {
                    if (!this.isLoggedIn) {
                        this.showNotification('You must be logged in to like videos', 'error');
                        return;
                    }
                    
                    const formData = new FormData();
                    formData.append('action', 'like_video');
                    formData.append('video_id', videoId);
                    
                    fetch('', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update local state
                            const video = this.videos.find(v => v.id === videoId);
                            if (video) {
                                if (!video.likes) video.likes = [];
                                
                                const index = video.likes.indexOf(this.username);
                                if (index === -1) {
                                    video.likes.push(this.username);
                                } else {
                                    video.likes.splice(index, 1);
                                }
                                
                                // Update current video if it's the same
                                if (this.currentVideo && this.currentVideo.id === videoId) {
                                    this.currentVideo.likes = [...video.likes];
                                }
                            }
                            
                            this.showNotification('Like updated', 'success');
                        }
                    });
                },
                
                // Check if user liked a video
                isLiked(videoId) {
                    const video = this.videos.find(v => v.id === videoId);
                    return video && video.likes && video.likes.includes(this.username);
                },
                
                // Toggle subscription to channel
                toggleSubscribe(channelOwner) {
                    if (!this.isLoggedIn) {
                        this.showNotification('You must be logged in to subscribe', 'error');
                        return;
                    }
                    
                    const formData = new FormData();
                    formData.append('action', 'subscribe');
                    formData.append('channel_owner', channelOwner);
                    
                    fetch('', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update local state
                            if (!this.channels[channelOwner]) return;
                            
                            const index = this.channels[channelOwner].subscribers.indexOf(this.username);
                            if (index === -1) {
                                this.channels[channelOwner].subscribers.push(this.username);
                            } else {
                                this.channels[channelOwner].subscribers.splice(index, 1);
                            }
                            
                            this.showNotification('Subscription updated', 'success');
                        }
                    });
                },
                
                // Check if user subscribed to a channel
                isSubscribed(channelOwner) {
                    return this.channels[channelOwner] && 
                           this.channels[channelOwner].subscribers.includes(this.username);
                },
                
                // Increment video views
                incrementViews(videoId) {
                    const formData = new FormData();
                    formData.append('action', 'increment_views');
                    formData.append('video_id', videoId);
                    
                    fetch('', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update local state
                            const video = this.videos.find(v => v.id === videoId);
                            if (video) {
                                if (!video.views) video.views = 0;
                                video.views = data.views;
                                
                                // Update current video if it's the same
                                if (this.currentVideo && this.currentVideo.id === videoId) {
                                    this.currentVideo.views = data.views;
                                }
                            }
                        }
                    });
                },
                
                // Remove video from channel
                removeFromChannel(videoId) {
                    if (!this.isLoggedIn) {
                        this.showNotification('You must be logged in', 'error');
                        return;
                    }
                    
                    if (confirm('Are you sure you want to remove this video from your channel?')) {
                        const formData = new FormData();
                        formData.append('action', 'remove_from_channel');
                        formData.append('video_id', videoId);
                        
                        fetch('', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                this.showNotification(data.message, 'success');
                                
                                // Update local state
                                const channel = this.channels[this.username];
                                if (channel) {
                                    const index = channel.videos.indexOf(videoId);
                                    if (index !== -1) {
                                        channel.videos.splice(index, 1);
                                    }
                                }
                                
                                // Reload dashboard
                                this.loadChannelDashboard();
                            } else {
                                this.showNotification(data.message, 'error');
                            }
                        })
                        .catch(error => {
                            this.showNotification('Remove failed: ' + error.message, 'error');
                        });
                    }
                },
                
                // Format date for display
                formatDate(dateString) {
                    const date = new Date(dateString);
                    return date.toLocaleDateString();
                },
                
                // Format date-time for display
                formatDateTime(dateString) {
                    const date = new Date(dateString);
                    return date.toLocaleString();
                },
                
                // Export changes to CSV
                exportChanges() {
                    let csvContent = "Timestamp,Action,User,Details\n";
                    
                    this.changes.forEach(change => {
                        csvContent += `"${change.timestamp}","${change.action}","${change.user}","${change.details}"\n`;
                    });
                    
                    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                    const url = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    
                    link.setAttribute('href', url);
                    link.setAttribute('download', 'videohub-changes.csv');
                    link.style.visibility = 'hidden';
                    
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                }
            };
        }
    </script>
</body>
</html>