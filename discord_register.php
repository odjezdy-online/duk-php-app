<?php
session_start();

// Load database configuration first
require_once 'config.php';

// Load Discord configuration
$config = require_once 'config.discord.php';

// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is already logged in
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header('Location: admin.php');
    exit;
}

// Handle OAuth2 callback
if (isset($_GET['code'])) {
    // Exchange code for access token
    $token = getDiscordAccessToken($_GET['code']);
        if ($token) {
            // Get user info from Discord
            $userInfo = getDiscordUserInfo($token);
            if ($userInfo) {
                // Store access token with user info
                $userInfo['access_token'] = $token;
                // Store pending registration
                handleRegistration($userInfo);
            header('Location: register_success.php');
            exit;
        }
    }
    header('Location: register_error.php');
    exit;
}

// Functions
function getDiscordAccessToken($code) {
    global $config;
    
    $postData = [
        'client_id' => $config['client_id'],
        'client_secret' => $config['client_secret'],
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => $config['redirect_uri']
    ];

    $ch = curl_init($config['api_url'] . '/oauth2/token');
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response) {
        $data = json_decode($response, true);
        return $data['access_token'] ?? null;
    }
    return null;
}

function getDiscordUserInfo($token) {
    global $config;
    
    $ch = curl_init($config['api_url'] . '/users/@me');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response) {
        return json_decode($response, true);
    }
    return null;
}

function handleRegistration($userInfo) {
    global $link;
    $users = json_decode(file_get_contents('users.json'), true) ?: ['pending' => [], 'approved' => []];
    
    // First check if the Discord username exists in MySQL
    $sql = "SELECT id, username FROM transport_admin.users WHERE LOWER(username) = LOWER(?)";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "s", $userInfo['username']);
        
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_store_result($stmt);
            
            if (mysqli_stmt_num_rows($stmt) == 1) {
                // User exists in MySQL, automatically approve them
                mysqli_stmt_bind_result($stmt, $id, $db_username);
                mysqli_stmt_fetch($stmt);
                
                $users['approved'][$userInfo['id']] = [
                    'username' => $userInfo['username'],
                    'discriminator' => $userInfo['discriminator'] ?? '',
                    'avatar' => $userInfo['avatar'],
                    'registered_at' => date('Y-m-d H:i:s'),
                    'discord_id' => $userInfo['id']
                ];
                
                file_put_contents('users.json', json_encode($users, JSON_PRETTY_PRINT));
                
                $_SESSION['loggedin'] = true;
                $_SESSION['id'] = $id;
                $_SESSION['username'] = $db_username;
                $_SESSION['discord_id'] = $userInfo['id'];
                
                header('Location: admin.php');
                exit;
            }
        }
        mysqli_stmt_close($stmt);
    }
    
    // If we get here, check if user is already registered in Discord system
    if (isset($users['approved'][$userInfo['id']])) {
        // User is already approved, log them in
        $_SESSION['loggedin'] = true;
        $_SESSION['discord_id'] = $userInfo['id'];
        $_SESSION['username'] = $users['approved'][$userInfo['id']]['username'];
        $_SESSION['is_discord_user'] = true;
        header('Location: admin.php');
        exit;
    }
    
    if (isset($users['pending'][$userInfo['id']])) {
        $_SESSION['error'] = 'Vaše registrace již čeká na schválení.';
        header('Location: register_error.php');
        exit;
    }
    
    try {
        // Odeslat požadavek na verifikaci Discord botovi
        //$botConfig = parse_ini_file(__DIR__ . '/discord_bot/.env');
        //$apiSecret = $botConfig['API_SECRET'] ?? '';
        $apiSecret = 'you_fucking_b1tch!_go_ky$';
        
        $ch = curl_init('http://127.0.0.1:5008/verify');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'user_id' => $userInfo['id'],
            'username' => $userInfo['username'],
            'api_secret' => $apiSecret
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-API-Secret: ' . $apiSecret
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200) {
            $errorMsg = 'Chyba při komunikaci s Discord botem: ';
            if ($error) {
                $errorMsg .= "CURL Error: " . $error;
            } else {
                $errorMsg .= "HTTP Status: " . $httpCode . ", Response: " . $response;
            }
            error_log($errorMsg);
            throw new Exception($errorMsg);
        }

        if ($response === false) {
            $errorMsg = 'Nepodařilo se spojit s Discord botem: ' . $error;
            error_log($errorMsg);
            throw new Exception($errorMsg);
        }

        // Přidat do čekajících registrací
        $users['pending'][$userInfo['id']] = [
            'username' => $userInfo['username'],
            'discriminator' => $userInfo['discriminator'] ?? '',
            'avatar' => $userInfo['avatar'],
            'registered_at' => date('Y-m-d H:i:s'),
            'discord_id' => $userInfo['id']
        ];
        
        // Load existing bus data
        $busData = [];
        if (file_exists('bus_data.json')) {
            $busData = json_decode(file_get_contents('bus_data.json'), true) ?: [];
        }

        // Update users data
        if (file_put_contents('users.json', json_encode($users, JSON_PRETTY_PRINT))) {
            // Save bus data back
            if (!empty($busData)) {
                file_put_contents('bus_data.json', json_encode($busData, JSON_PRETTY_PRINT));
            }

            $_SESSION['success'] = 'Vaše registrace byla úspěšně přijata a čeká na schválení administrátorem. ' .
                                 'Budete informováni přes Discord, až bude vaše žádost schválena.';
            $_SESSION['pending_discord_id'] = $userInfo['id'];
            header('Location: register_success.php');
            exit;
        }
    } catch (Exception $e) {
        error_log("Discord registration error: " . $e->getMessage());
        $_SESSION['error'] = 'Došlo k chybě při zpracování registrace. Prosím zkuste to později.';
        header('Location: register_error.php');
        exit;
    }
}

// If no code parameter, redirect to Discord OAuth2
if (!isset($_GET['code'])) {
    $params = [
        'client_id' => $config['client_id'],
        'redirect_uri' => $config['redirect_uri'],
        'response_type' => 'code',
        'scope' => implode(' ', $config['scopes'])
    ];
    
    $authUrl = 'https://discord.com/api/oauth2/authorize?' . http_build_query($params);
    header('Location: ' . $authUrl);
    exit;
}

// Handle errors
if (isset($_GET['error'])) {
    $_SESSION['error'] = 'Přihlášení přes Discord selhalo: ' . htmlspecialchars($_GET['error_description'] ?? 'Neznámá chyba');
    header('Location: register_error.php');
    exit;
}
?>