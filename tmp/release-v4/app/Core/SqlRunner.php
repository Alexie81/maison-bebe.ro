<?php
declare(strict_types=1);

namespace MaisonBebe\Core;

use PDO;
use RuntimeException;

final class SqlRunner
{
    public static function runFile(PDO $pdo, string $path): void
    {
        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new RuntimeException('Nu poate fi citit fișierul SQL: ' . $path);
        }
        foreach (self::split($sql) as $statement) {
            $pdo->exec($statement);
        }
    }

    public static function split(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $quote = null;
        $escaped = false;
        $length = strlen($sql);

        for ($index = 0; $index < $length; $index++) {
            $char = $sql[$index];
            $next = $index + 1 < $length ? $sql[$index + 1] : '';

            if ($quote === null && $char === '-' && $next === '-' && ($index + 2 >= $length || ctype_space($sql[$index + 2]))) {
                while ($index < $length && $sql[$index] !== "\n") {
                    $index++;
                }
                $buffer .= "\n";
                continue;
            }

            if ($quote === null && $char === '#') {
                while ($index < $length && $sql[$index] !== "\n") {
                    $index++;
                }
                $buffer .= "\n";
                continue;
            }

            if ($quote === null && $char === '/' && $next === '*') {
                $index += 2;
                while ($index + 1 < $length && !($sql[$index] === '*' && $sql[$index + 1] === '/')) {
                    $index++;
                }
                $index++;
                continue;
            }

            if ($quote !== null) {
                $buffer .= $char;
                if ($escaped) {
                    $escaped = false;
                    continue;
                }
                if ($char === '\\') {
                    $escaped = true;
                    continue;
                }
                if ($char === $quote) {
                    if ($next === $quote && $quote !== '`') {
                        $buffer .= $next;
                        $index++;
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $buffer .= $char;
                continue;
            }

            if ($char === ';') {
                $trimmed = trim($buffer);
                if ($trimmed !== '') {
                    $statements[] = $trimmed;
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $trimmed = trim($buffer);
        if ($trimmed !== '') {
            $statements[] = $trimmed;
        }
        return $statements;
    }
}

