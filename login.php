<?php
// Configure session parameters before starting the session
ini_set('session.cookie_lifetime', 86400); // 24 hours
ini_set('session.gc_maxlifetime', 86400); // 24 hours

// Set session cookie parameters
session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/',
    'domain' => '',  // Current domain
    'secure' => true,  // Only send cookie over HTTPS
    'httponly' => true // Protect against XSS
]);

session_start();

// Load database configuration first
require_once 'config.php';

// Load Discord configuration
$config = require_once 'config.discord.php';

// Check if user is already logged in
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header('Location: admin.php');
    exit;
}

// Load users data
$users = [];
if (file_exists('users.json')) {
    $users = json_decode(file_get_contents('users.json'), true);
}

// Define variables and initialize with empty values
$username = $password = "";
$username_err = $password_err = "";

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check if username is empty
    if (empty(trim($_POST["username"]))) {
        $username_err = "Prosím zadejte uživatelské jméno.";
    } else {
        $username = trim($_POST["username"]);
    }
    
    // Check if password is empty
    if (empty(trim($_POST["password"]))) {
        $password_err = "Prosím zadejte heslo.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validate credentials
    if (empty($username_err) && empty($password_err)) {
        // Prepare a select statement
        $sql = "SELECT id, username, password FROM transport_admin.users WHERE username = ?";
        
        if ($stmt = mysqli_prepare($link, $sql)) {
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "s", $param_username);
            
            // Set parameters
            $param_username = $username;
            
            // Attempt to execute the prepared statement
            if (mysqli_stmt_execute($stmt)) {
                // Store result
                mysqli_stmt_store_result($stmt);
                
                // Check if username exists, if yes then verify password
                if (mysqli_stmt_num_rows($stmt) == 1) {                    
                    // Bind result variables
                    mysqli_stmt_bind_result($stmt, $id, $username, $hashed_password);
                    if (mysqli_stmt_fetch($stmt)) {
                        if (hash('sha256', $password) === $hashed_password) {
                            // Check if user is admin (username 'admin')
                            $_SESSION["loggedin"] = true;
                            $_SESSION["id"] = $id;
                            $_SESSION["username"] = $username;
                            $_SESSION["is_admin"] = ($username === 'mxnticek');
                            
                            // Redirect user to admin page
                            header("Location: admin.php");
                            exit;
                        } else {
                            // Password is not valid
                            $password_err = "Neplatné heslo.";
                        }
                    }
                } else {
                    // Username doesn't exist
                    $username_err = "Účet s tímto uživatelským jménem neexistuje.";
                }
            } else {
                echo "Oops! Něco se pokazilo. Zkuste to prosím později.";
            }

            // Close statement
            mysqli_stmt_close($stmt);
        }
    }
    
    // Close connection
    mysqli_close($link);
}
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Přihlášení - Dopravní Údaje Kolektiv</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
            <!-- Header -->
            <div class="bg-gradient-to-r from-blue-600 to-indigo-700 p-6 text-white text-center">
                <i class="fas fa-bus text-4xl mb-3"></i>
                <h1 class="text-2xl font-bold">Dopravní Údaje Kolektiv</h1>
                <p class="text-blue-100 mt-2">Připojte se k naší komunitě</p>
            </div>
            
            <div class="p-8">
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-r">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle mr-2"></i>
                            <?= htmlspecialchars($_SESSION['error']) ?>
                        </div>
                        <?php unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-r">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle mr-2"></i>
                            <?= htmlspecialchars($_SESSION['success']) ?>
                        </div>
                        <?php unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>

                <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">Přihlášení</h2>

                <!-- Discord Login Button -->
                <div class="mb-6">
                    <a href="discord_register.php" class="block w-full bg-[#5865F2] text-white text-center py-3 px-4 rounded-lg hover:bg-[#4752C4] dark:bg-[#5865F2] dark:hover:bg-[#4752C4] transition-all duration-200 transform hover:scale-105 shadow-md">
                        <div class="flex items-center justify-center">
                            <i class="fab fa-discord text-xl mr-3"></i>
                            <span class="font-semibold">Přihlásit se přes Discord</span>
                        </div>
                    </a>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-2 text-center">
                        Rychlá registrace a přihlášení přes Discord
                    </p>
                </div>

                <!-- Divider -->
                <div class="relative mb-6">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-300"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="px-3 bg-white text-gray-500 font-medium">nebo klasické přihlášení</span>
                    </div>
                </div>

                <!-- Traditional Login Form -->
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="space-y-5">
                    <div>
                        <label for="username" class="block text-sm font-semibold text-gray-700 mb-2">Uživatelské jméno</label>
                        <div class="relative">
                            <i class="fas fa-user absolute left-3 top-3 text-gray-400"></i>
                            <input type="text" name="username" id="username" 
                                class="pl-10 w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 py-3 <?php echo (!empty($username_err)) ? 'border-red-500 bg-red-50' : ''; ?>"
                                placeholder="Zadejte uživatelské jméno"
                                value="<?php echo $username ?? ''; ?>">
                        </div>
                        <?php if (!empty($username_err)): ?>
                            <p class="mt-2 text-sm text-red-600 flex items-center">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                <?php echo $username_err; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">Heslo</label>
                        <div class="relative">
                            <i class="fas fa-lock absolute left-3 top-3 text-gray-400"></i>
                            <input type="password" name="password" id="password"
                                class="pl-10 w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 py-3 <?php echo (!empty($password_err)) ? 'border-red-500 bg-red-50' : ''; ?>"
                                placeholder="Zadejte heslo">
                        </div>
                        <?php if (!empty($password_err)): ?>
                            <p class="mt-2 text-sm text-red-600 flex items-center">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                <?php echo $password_err; ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="w-full bg-gradient-to-r from-blue-600 to-blue-700 text-white py-3 px-4 rounded-lg hover:from-blue-700 hover:to-blue-800 transition-all duration-200 transform hover:scale-105 shadow-md font-semibold">
                        <i class="fas fa-sign-in-alt mr-2"></i>
                        Přihlásit se
                    </button>
                </form>

                <!-- Additional Info -->
                <div class="mt-6 text-center text-sm text-gray-600">
                    <p>Nový uživatel? <a href="discord_register.php" class="text-blue-600 hover:text-blue-800 font-semibold">Zaregistrujte se přes Discord</a></p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
