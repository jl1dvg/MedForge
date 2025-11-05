<?php

namespace Modules\Reporting\Services\Definitions;

use InvalidArgumentException;

class PdfTemplateRegistry
{
    /** @var array<string, PdfTemplateDefinitionInterface> */
    private array $definitions = [];

    /**
     * @param iterable<int, PdfTemplateDefinitionInterface> $definitions
     */
    public function __construct(iterable $definitions = [])
    {
        foreach ($definitions as $definition) {
            $this->register($definition);
        }
    }

    public static function fromConfig(?string $configPath = null): self
    {
        $path = $configPath ?? dirname(__DIR__) . '/Definitions/pdf-templates.php';

        if (!is_file($path)) {
            return new self();
        }

        $definitions = require $path;

        if ($definitions === null) {
            return new self();
        }

        if (!is_iterable($definitions)) {
            throw new InvalidArgumentException(sprintf('El archivo de configuración "%s" debe retornar un iterable.', $path));
        }

        return new self($definitions);
    }

    public function register(PdfTemplateDefinitionInterface $definition): void
    {
        $this->definitions[$definition->getIdentifier()] = $definition;
    }

    public function get(string $identifier): ?PdfTemplateDefinitionInterface
    {
        return $this->definitions[$identifier] ?? null;
    }

    /**
     * @return array<int, PdfTemplateDefinitionInterface>
     */
    public function all(): array
    {
        return array_values($this->definitions);
    }
}
