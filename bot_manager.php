<?php
function is_bot_running() {
    exec("ps aux | grep '[p]ython.*discord_bot/main.py'", $output, $return_var);
    return count($output) > 0;
}

function start_bot() {
    if (!is_bot_running()) {
        $pythonPath = "python";  // nebo "python3" podle systému
        $botScript = __DIR__ . "/discord_bot/main.py";
        $logFile = __DIR__ . "/discord_bot/bot.log";
        
        // Spustit bot na pozadí a přesměrovat výstup do logu
        exec("cd " . __DIR__ . "/discord_bot && $pythonPath main.py > $logFile 2>&1 &");
        
        // Počkat chvíli a ověřit, že bot běží
        sleep(2);
        return is_bot_running();
    }
    return true;
}

function stop_bot() {
    exec("pkill -f 'python.*discord_bot/main.py'");
    return !is_bot_running();
}

// Pokud je skript spuštěn přímo
if (php_sapi_name() === 'cli') {
    $action = isset($argv[1]) ? $argv[1] : '';
    
    switch($action) {
        case 'start':
            echo start_bot() ? "Bot byl úspěšně spuštěn.\n" : "Nepodařilo se spustit bota.\n";
            break;
        case 'stop':
            echo stop_bot() ? "Bot byl úspěšně zastaven.\n" : "Nepodařilo se zastavit bota.\n";
            break;
        case 'status':
            echo is_bot_running() ? "Bot běží.\n" : "Bot neběží.\n";
            break;
        default:
            echo "Použití: php bot_manager.php [start|stop|status]\n";
    }
}
?>