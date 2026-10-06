<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Template PHP biasa dengan layout & section.
 * Di dalam template, $this adalah instance View:
 *   $this->set('title', '...');  $this->section('scripts'); ... $this->endSection();
 *   $this->partial('partials/x', [...]);   dan di layout: $this->get('title'), $this->yieldSection('scripts'), $content
 */
final class View
{
    private array $vars = [];
    private array $sections = [];
    private array $sectionStack = [];

    public static function render(string $view, array $data = [], ?string $layout = 'layouts/app'): string
    {
        $instance = new self();
        $content  = $instance->capture($view, $data);

        if ($layout === null) {
            return $content;
        }
        return $instance->capture($layout, $data + ['content' => $content]);
    }

    public function set(string $key, mixed $value): void
    {
        $this->vars[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->vars[$key] ?? $default;
    }

    public function partial(string $view, array $data = []): void
    {
        echo $this->capture($view, $data);
    }

    public function section(string $name): void
    {
        $this->sectionStack[] = $name;
        ob_start();
    }

    public function endSection(): void
    {
        $name = array_pop($this->sectionStack);
        if ($name === null) {
            throw new RuntimeException('endSection() tanpa section().');
        }
        $this->sections[$name] = (string) ob_get_clean();
    }

    public function yieldSection(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    private function capture(string $view, array $data): string
    {
        $file = BASE_PATH . '/app/views/' . $view . '.php';
        if (!is_file($file)) {
            throw new RuntimeException("View tidak ditemukan: {$view}");
        }
        unset($data['this']);
        $level = ob_get_level();
        ob_start();
        try {
            (function (string $__file, array $__data): void {
                extract($__data, EXTR_SKIP);
                require $__file;
            })->call($this, $file, $data);
        } catch (\Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
