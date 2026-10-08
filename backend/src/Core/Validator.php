<?php
declare(strict_types=1);
namespace App\Core;

final class Validator {
    private static function len(string $v): int {
        return function_exists('mb_strlen') ? mb_strlen($v, 'UTF-8') : strlen($v);
    }
    /**
     * Reglas: 'required|email|min:3|max:120|numeric|in:a,b|int'
     * @return array{valid:bool, errors:array}
     */
    public static function validate(array $data, array $rules): array {
        $errors = [];
        foreach ($rules as $field => $ruleStr) {
            $value = $data[$field] ?? null;
            foreach (explode('|', (string)$ruleStr) as $rule) {
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
                if ($name === 'required' && ($value === null || $value === '')) {
                    $errors[$field][] = 'Campo obligatorio.';
                } elseif ($value !== null && $value !== '') {
                    if ($name === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) $errors[$field][] = 'Email no válido.';
                    if ($name === 'min' && self::len((string)$value) < (int)$param) $errors[$field][] = "Mínimo {$param} caracteres.";
                    if ($name === 'max' && self::len((string)$value) > (int)$param) $errors[$field][] = "Máximo {$param} caracteres.";
                    if ($name === 'numeric' && !is_numeric($value)) $errors[$field][] = 'Debe ser numérico.';
                    if ($name === 'int' && filter_var($value, FILTER_VALIDATE_INT) === false) $errors[$field][] = 'Debe ser entero.';
                    if ($name === 'in' && !in_array((string)$value, explode(',', (string)$param), true)) $errors[$field][] = 'Valor no permitido.';
                }
            }
        }
        return ['valid' => $errors === [], 'errors' => $errors];
    }
}
