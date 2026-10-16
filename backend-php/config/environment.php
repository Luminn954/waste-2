<?php
declare(strict_types=1);

/** Load local SMARTWASTE_* settings without overriding process environment. */
function loadSmartWasteEnvironment(): void
{
    $envFile = dirname(__DIR__) . '/.env';
    if (!is_file($envFile) || !is_readable($envFile)) {
        return;
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim(preg_replace('/^\xEF\xBB\xBF/', '', $line) ?? $line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $separator = strpos($line, '=');
        if ($separator === false) {
            continue;
        }

        $name = trim(substr($line, 0, $separator));
        if (!preg_match('/\ASMARTWASTE_[A-Z0-9_]+\z/', $name)) {
            continue;
        }

        $value = trim(substr($line, $separator + 1));
        if (strlen($value) >= 2) {
            $quote = $value[0];
            if (($quote === '"' || $quote === "'") && substr($value, -1) === $quote) {
                $value = substr($value, 1, -1);
            }
        }

        if (getenv($name) === false) {
            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
        }
    }
}

loadSmartWasteEnvironment();
