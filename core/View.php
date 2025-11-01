<?php

namespace Core;

use InvalidArgumentException;

class View
{
    /**
     * Renderiza una vista utilizando el layout principal.
     *
     * @param string $view Ruta absoluta o relativa al archivo de vista.
     * @param array $data  Variables que estarán disponibles en la vista.
     * @param string|null $layout Ruta del layout a utilizar. Usa el layout global por defecto.
     */
    public static function render(string $view, array $data = [], string|false|null $layout = null): void
    {
        $resolvedView = self::resolvePath($view, false);
        $resolvedLayout = match ($layout) {
            false => false,
            null => VIEW_PATH . '/layout.php',
            default => self::resolvePath($layout, true),
        };

        if (!is_file($resolvedView)) {
            throw new InvalidArgumentException(sprintf('La vista "%s" no existe.', $view));
        }

        if ($resolvedLayout !== false && $resolvedLayout !== null && !is_file($resolvedLayout)) {
            throw new InvalidArgumentException(sprintf('El layout "%s" no existe.', $layout));
        }

        $variables = array_merge($data, [
            'viewPath' => $resolvedView,
            'pageTitle' => $data['pageTitle'] ?? $data['title'] ?? null,
            'bodyClass' => $data['bodyClass'] ?? null,
        ]);

        extract($variables, EXTR_SKIP);

        if ($resolvedLayout) {
            include $resolvedLayout;
            return;
        }

        include $resolvedView;
    }

    private static function resolvePath(string $path, bool $isLayout): string
    {
        if ($path === '') {
            return $path;
        }

        if ($path[0] === '/' || str_starts_with($path, BASE_PATH)) {
            return $path;
        }

        $baseDir = $isLayout ? VIEW_PATH : BASE_PATH;
        $candidate = $baseDir . '/' . ltrim($path, '/');

        return $candidate;
    }
}
