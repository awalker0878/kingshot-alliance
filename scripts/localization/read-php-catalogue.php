<?php

declare(strict_types=1);

// Read trusted repository catalogue arrays with PHP itself. This preserves PHP
// escaping and nested keys; regex literal order is not a message identity.
try {
    $catalogue = require $argv[1];
    if (! is_array($catalogue)) {
        throw new RuntimeException('Expected a PHP catalogue array');
    }
    $result = [];
    $walk = function (array $node, string $prefix) use (&$walk, &$result): void {
        foreach ($node as $key => $value) {
            $identity = $prefix.'.'.$key;
            if (is_array($value)) {
                $walk($value, $identity);
            } elseif (is_string($value)) {
                if (array_key_exists($identity, $result)) {
                    throw new RuntimeException('Ambiguous PHP catalogue key: '.$identity);
                }
                $result[$identity] = $value;
            } else {
                throw new RuntimeException('Non-string PHP catalogue value: '.$identity);
            }
        }
    };
    $walk($catalogue, $argv[2]);
    echo json_encode((object) $result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage().PHP_EOL);
    exit(1);
}
