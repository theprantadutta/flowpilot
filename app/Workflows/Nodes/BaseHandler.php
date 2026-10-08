<?php

namespace App\Workflows\Nodes;

use App\Workflows\Definition\ValidationScope;
use App\Workflows\Support\TemplateRenderer;

abstract class BaseHandler implements NodeHandler
{
    public function handles(array $config): array
    {
        return array_column($this->type()->handles(), 'id');
    }

    public function transactional(): bool
    {
        return true;
    }

    public function validate(array $config, ValidationScope $scope): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected static function text(array $config, string $key): string
    {
        $value = $config[$key] ?? null;

        return is_string($value) ? trim($value) : '';
    }

    /**
     * Placeholder problems in a template: unknown paths are reported so a typo
     * does not silently render as nothing.
     *
     * @return list<string>
     */
    protected static function templateErrors(string $template, ValidationScope $scope, string $what): array
    {
        $errors = [];

        foreach (TemplateRenderer::paths($template) as $path) {
            if (! $scope->knowsPath($path)) {
                $errors[] = "The {$what} uses {{ {$path} }}, which this workflow does not have.";
            }
        }

        return $errors;
    }
}
