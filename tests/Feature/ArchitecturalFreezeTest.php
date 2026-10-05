<?php

namespace Tests\Feature;

use Tests\TestCase;

class ArchitecturalFreezeTest extends TestCase
{
    private string $controllersPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controllersPath = base_path('app/Http/Controllers');
    }

    public function test_controllers_do_not_violate_core_freeze_rules()
    {
        if (!file_exists($this->controllersPath)) {
            $this->markTestSkipped('Controllers path does not exist');
        }

        $files = $this->getPhpFilesRecursive($this->controllersPath);
        $violations = [];

        $forbiddenMethods = ['save', 'update', 'delete', 'increment', 'decrement'];
        $forbiddenStaticMethods = ['create', 'update', 'delete', 'save'];
        $forbiddenProperties = ['status', 'current_stock'];

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $tokens = token_get_all($content);

            $count = count($tokens);
            for ($i = 0; $i < $count; $i++) {
                $token = $tokens[$i];

                // 1. Check method / property calls via -> (T_OBJECT_OPERATOR or T_NULLSAFE_OBJECT_OPERATOR)
                if (is_array($token) && ($token[0] === T_OBJECT_OPERATOR || (defined('T_NULLSAFE_OBJECT_OPERATOR') && $token[0] === T_NULLSAFE_OBJECT_OPERATOR))) {
                    $nextToken = $this->getNextNonWhitespaceToken($tokens, $i);
                    if ($nextToken && is_array($nextToken) && $nextToken[0] === T_STRING) {
                        $name = $nextToken[1];
                        if (in_array($name, $forbiddenMethods, true)) {
                            $violations[] = sprintf(
                                "File %s (Line %d): Forbidden method call ->%s() in Controller",
                                basename($file),
                                $nextToken[2],
                                $name
                            );
                        }
                        if (in_array($name, $forbiddenProperties, true)) {
                            // Check if next token is = (assignment)
                            $afterName = $this->getNextNonWhitespaceToken($tokens, $i + 2);
                            if ($afterName === '=') {
                                $violations[] = sprintf(
                                    "File %s (Line %d): Forbidden direct property mutation ->%s = ... in Controller",
                                    basename($file),
                                    $nextToken[2],
                                    $name
                                );
                            }
                        }
                    }
                }

                // 2. Check static method calls via :: (T_PAAMAYIM_NEKUDOTAYIM)
                if (is_array($token) && $token[0] === T_PAAMAYIM_NEKUDOTAYIM) {
                    $nextToken = $this->getNextNonWhitespaceToken($tokens, $i);
                    if ($nextToken && is_array($nextToken) && $nextToken[0] === T_STRING) {
                        $name = $nextToken[1];
                        if (in_array($name, $forbiddenStaticMethods, true)) {
                            $violations[] = sprintf(
                                "File %s (Line %d): Forbidden static Model call ::%s() in Controller",
                                basename($file),
                                $nextToken[2],
                                $name
                            );
                        }
                    }
                }

                // 3. Check direct instantiation of Services/Engines via `new` (T_NEW)
                if (is_array($token) && $token[0] === T_NEW) {
                    $nextToken = $this->getNextNonWhitespaceToken($tokens, $i);
                    if ($nextToken && is_array($nextToken) && $nextToken[0] === T_STRING) {
                        $className = $nextToken[1];
                        if (str_ends_with($className, 'Service') || str_ends_with($className, 'Engine') || str_ends_with($className, 'Action')) {
                            $violations[] = sprintf(
                                "File %s (Line %d): Forbidden direct instantiation 'new %s' in Controller. Use Dependency Injection.",
                                basename($file),
                                $nextToken[2],
                                $className
                            );
                        }
                    }
                }
            }
        }

        $this->assertEmpty(
            $violations,
            "Architectural Core Freeze Violations Found:\n" . implode("\n", $violations)
        );
    }

    private function getPhpFilesRecursive(string $dir): array
    {
        $files = [];
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $files = array_merge($files, $this->getPhpFilesRecursive($path));
            } elseif (str_ends_with($item, '.php')) {
                $files[] = $path;
            }
        }
        return $files;
    }

    private function getNextNonWhitespaceToken(array $tokens, int $currentIndex)
    {
        $count = count($tokens);
        for ($j = $currentIndex + 1; $j < $count; $j++) {
            $t = $tokens[$j];
            if (is_array($t) && ($t[0] === T_WHITESPACE || $t[0] === T_COMMENT || $t[0] === T_DOC_COMMENT)) {
                continue;
            }
            return $t;
        }
        return null;
    }
}
