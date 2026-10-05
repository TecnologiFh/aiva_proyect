<?php

namespace App\Modules\Automation\Engine;

class VariableResolver
{
    public function resolve(string $template, array $context): string
    {
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_\.]+)\s*\}\}/', function ($matches) use ($context) {
            $path = explode('.', $matches[1]);
            $current = $context;

            foreach ($path as $key) {
                if (is_array($current) && array_key_exists($key, $current)) {
                    $current = $current[$key];
                } else {
                    return $matches[0]; // Retorna la variable intacta si no se encuentra
                }
            }

            return is_scalar($current) ? (string) $current : json_encode($current);
        }, $template);
    }
}
