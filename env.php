<?php












function loadEnv($path) {
    static $loaded = false;
    if ($loaded) return;
    $loaded = true;

    if (!file_exists($path)) {
        
        
        die('Configuration error: .env file not found. Copy .env.example to .env and fill in your values.');
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;

        list($key, $value) = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);

        
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last  = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        if (!array_key_exists($key, $_ENV) && getenv($key) === false) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}










function writeEnvValues($path, array $updates) {
    if (!file_exists($path)) return false;

    $lines   = file($path, FILE_IGNORE_NEW_LINES);
    $seen    = array();
    $out     = array();

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || strpos($trimmed, '#') === 0 || strpos($trimmed, '=') === false) {
            $out[] = $line;
            continue;
        }
        list($key, ) = explode('=', $trimmed, 2);
        $key = trim($key);
        if (array_key_exists($key, $updates)) {
            $out[] = $key . '=' . envQuote($updates[$key]);
            $seen[$key] = true;
        } else {
            $out[] = $line;
        }
    }

    foreach ($updates as $key => $value) {
        if (!isset($seen[$key])) {
            $out[] = $key . '=' . envQuote($value);
        }
    }

    $written = @file_put_contents($path, implode(PHP_EOL, $out) . PHP_EOL);
    return $written !== false;
}

function envQuote($value) {
    $value = (string)$value;
    if ($value === '' || preg_match('/\s|#/', $value)) {
        return '"' . str_replace('"', '\\"', $value) . '"';
    }
    return $value;
}

function env($key, $default = null) {
    $value = getenv($key);
    if ($value === false) return $default;
    
    if (in_array(strtolower($value), array('true', '(true)'), true))  return true;
    if (in_array(strtolower($value), array('false', '(false)'), true)) return false;
    if ($value === '') return $default;
    return $value;
}

loadEnv(__DIR__ . '/.env');
