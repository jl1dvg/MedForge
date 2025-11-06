<?php

namespace Modules\Reporting\Services\Definitions;

use InvalidArgumentException;

class SolicitudTemplateRegistry
{
    /** @var array<string, SolicitudTemplateDefinitionInterface> */
    private array $definitions = [];

    /** @var list<SolicitudTemplateDefinitionInterface> */
    private array $primary = [];

    /** @var list<SolicitudTemplateDefinitionInterface> */
    private array $fallback = [];

    /**
     * @param iterable<int, SolicitudTemplateDefinitionInterface> $definitions
     */
    public function __construct(iterable $definitions = [])
    {
        foreach ($definitions as $definition) {
            $this->register($definition);
        }
    }

    public static function fromConfig(?string $configPath = null): self
    {
        $path = $configPath ?? __DIR__ . '/solicitud-templates.php';

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

    public function register(SolicitudTemplateDefinitionInterface $definition): void
    {
        $this->definitions[$definition->getIdentifier()] = $definition;

        if ($definition->isFallback()) {
            $this->fallback[] = $definition;
            return;
        }

        $this->primary[] = $definition;
    }

    public function get(string $identifier): ?SolicitudTemplateDefinitionInterface
    {
        return $this->definitions[$identifier] ?? null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function resolve(array $data): ?SolicitudTemplateDefinitionInterface
    {
        foreach ($this->primary as $definition) {
            if ($definition->matches($data)) {
                return $definition;
            }
        }

        foreach ($this->fallback as $definition) {
            if ($definition->matches($data)) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * @return array<int, SolicitudTemplateDefinitionInterface>
     */
    public function all(): array
    {
        return array_merge($this->primary, $this->fallback);
    }
}

