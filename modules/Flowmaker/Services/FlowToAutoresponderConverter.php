<?php

namespace Modules\Flowmaker\Services;

use Modules\WhatsApp\Support\AutoresponderFlow;

class FlowToAutoresponderConverter
{
    /**
     * @param array<string, mixed> $flowmakerData
     * @return array<string, mixed>
     */
    public function convert(array $flowmakerData, string $brand): array
    {
        $defaults = AutoresponderFlow::defaultConfig($brand);
        $flow = $defaults;

        $nodes = $this->normalizeNodes($flowmakerData['nodes'] ?? []);
        if (empty($nodes)) {
            return $flow;
        }

        $edges = $this->buildEdgesMap($flowmakerData['edges'] ?? []);

        $entryMessages = $this->buildEntryMessages($nodes, $edges);
        if (!empty($entryMessages)) {
            $flow['entry']['messages'] = $this->wrapMessages($entryMessages);
        }

        $options = $this->buildOptions($nodes, $edges);
        if (!empty($options)) {
            $flow['options'] = $options;
        }

        return $flow;
    }

    /**
     * @param array<int, mixed> $rawNodes
     * @return array<string, array<string, mixed>>
     */
    private function normalizeNodes(array $rawNodes): array
    {
        $nodes = [];
        foreach ($rawNodes as $rawNode) {
            if (is_object($rawNode)) {
                $rawNode = json_decode(json_encode($rawNode), true);
            }

            if (!is_array($rawNode)) {
                continue;
            }

            $id = (string) ($rawNode['id'] ?? '');
            if ($id === '') {
                continue;
            }

            $rawNode['data'] = is_array($rawNode['data'] ?? null) ? $rawNode['data'] : [];
            $rawNode['position'] = is_array($rawNode['position'] ?? null) ? $rawNode['position'] : [];
            $nodes[$id] = $rawNode;
        }

        return $nodes;
    }

    /**
     * @param array<int, mixed> $rawEdges
     * @return array<string, array<int, string>>
     */
    private function buildEdgesMap(array $rawEdges): array
    {
        $edges = [];
        foreach ($rawEdges as $edge) {
            if (is_object($edge)) {
                $edge = json_decode(json_encode($edge), true);
            }

            if (!is_array($edge)) {
                continue;
            }

            $source = (string) ($edge['source'] ?? '');
            $target = (string) ($edge['target'] ?? '');

            if ($source === '' || $target === '') {
                continue;
            }

            $edges[$source][] = $target;
        }

        return $edges;
    }

    /**
     * @param array<string, array<string, mixed>> $nodes
     * @param array<string, array<int, string>> $edges
     * @return array<int, string>
     */
    private function buildEntryMessages(array $nodes, array $edges): array
    {
        $startNode = $this->findStartNodeId($nodes);
        if ($startNode === null) {
            return [];
        }

        $messages = [];
        foreach ($edges[$startNode] ?? [] as $targetId) {
            $messages = array_merge($messages, $this->collectMessagesFromNode($targetId, $nodes, $edges));
        }

        return $messages;
    }

    /**
     * @param array<string, array<string, mixed>> $nodes
     * @return string|null
     */
    private function findStartNodeId(array $nodes): ?string
    {
        $candidates = array_filter($nodes, static function (array $node): bool {
            $type = $node['type'] ?? '';
            return in_array($type, ['keyword_trigger', 'incomingMessage'], true);
        });

        $startNodeId = null;
        $lowestX = INF;

        foreach ($candidates as $id => $node) {
            $position = $node['position'] ?? [];
            $x = $position['x'] ?? $position['X'] ?? null;
            $x = is_numeric($x) ? (float) $x : INF;

            if ($x < $lowestX) {
                $lowestX = $x;
                $startNodeId = (string) $id;
            }
        }

        if ($startNodeId === null && !empty($nodes)) {
            $startNodeId = (string) array_key_first($nodes);
        }

        return $startNodeId;
    }

    /**
     * @param array<string, array<string, mixed>> $nodes
     * @param array<string, array<int, string>> $edges
     * @return array<int, array<string, mixed>>
     */
    private function buildOptions(array $nodes, array $edges): array
    {
        $options = [];

        foreach ($nodes as $id => $node) {
            if (($node['type'] ?? '') !== 'keyword_trigger') {
                continue;
            }

            $keywords = $this->extractKeywords($node);
            if (empty($keywords)) {
                continue;
            }

            $messages = [];
            foreach ($edges[$id] ?? [] as $targetId) {
                $messages = array_merge($messages, $this->collectMessagesFromNode($targetId, $nodes, $edges));
            }

            if (empty($messages)) {
                continue;
            }

            $options[] = [
                'id' => (string) $id,
                'title' => $this->resolveNodeLabel($node, $keywords),
                'description' => 'Generado automáticamente desde Flowmaker.',
                'keywords' => $keywords,
                'messages' => $this->wrapMessages($messages),
            ];
        }

        return $options;
    }

    /**
     * @param array<string, mixed> $node
     * @param array<int, string> $keywords
     */
    private function resolveNodeLabel(array $node, array $keywords): string
    {
        $label = $node['data']['label'] ?? $node['data']['name'] ?? null;
        if (is_string($label) && trim($label) !== '') {
            return trim($label);
        }

        $keyword = $keywords[0] ?? 'Opción';
        return 'Opción ' . $keyword;
    }

    /**
     * @param array<string, mixed> $node
     * @return array<int, string>
     */
    private function extractKeywords(array $node): array
    {
        $keywords = $node['data']['keywords'] ?? [];
        if (!is_array($keywords)) {
            return [];
        }

        $values = [];
        foreach ($keywords as $keyword) {
            if (is_object($keyword)) {
                $keyword = json_decode(json_encode($keyword), true);
            }

            if (!is_array($keyword)) {
                continue;
            }

            $value = trim((string) ($keyword['value'] ?? ''));
            if ($value !== '') {
                $values[] = mb_strtolower($value);
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param array<string, array<string, mixed>> $nodes
     * @param array<string, array<int, string>> $edges
     * @param array<string, bool> $visited
     * @return array<int, string>
     */
    private function collectMessagesFromNode(string $nodeId, array $nodes, array $edges, array $visited = [], int $depth = 0): array
    {
        if ($depth > 20 || isset($visited[$nodeId]) || !isset($nodes[$nodeId])) {
            return [];
        }

        $visited[$nodeId] = true;
        $node = $nodes[$nodeId];
        $messages = [];

        $type = $node['type'] ?? '';
        $data = $node['data'] ?? [];

        switch ($type) {
            case 'message':
                $text = $data['settings']['message'] ?? null;
                $sanitized = $this->sanitizeText($text);
                if ($sanitized !== '') {
                    $messages[] = $sanitized;
                }
                break;

            case 'template':
                $templateName = $data['settings']['templateName'] ?? $data['settings']['selectedTemplateId'] ?? '';
                $templateName = $this->sanitizeText($templateName);
                if ($templateName !== '') {
                    $messages[] = "[Plantilla {$templateName}]";
                } else {
                    $messages[] = '[Plantilla de WhatsApp]';
                }
                break;

            case 'image':
            case 'video':
            case 'pdf':
                $caption = $this->sanitizeText($data['settings']['caption'] ?? null);
                if ($caption !== '') {
                    $messages[] = $caption;
                } else {
                    $messages[] = '[Mensaje multimedia]';
                }
                break;

            default:
                break;
        }

        $targets = $edges[$nodeId] ?? [];
        if (count($targets) === 1) {
            $messages = array_merge(
                $messages,
                $this->collectMessagesFromNode($targets[0], $nodes, $edges, $visited, $depth + 1)
            );
        }

        return $messages;
    }

    /**
     * @param array<int, string> $messages
     * @return array<int, array<string, string>>
     */
    private function wrapMessages(array $messages): array
    {
        $wrapped = [];

        foreach ($messages as $message) {
            $sanitized = $this->sanitizeText($message);
            if ($sanitized === '') {
                continue;
            }

            $wrapped[] = [
                'type' => 'text',
                'body' => $sanitized,
            ];
        }

        if (empty($wrapped)) {
            $wrapped[] = [
                'type' => 'text',
                'body' => 'Mensaje automatizado.',
            ];
        }

        return $wrapped;
    }

    private function sanitizeText(?string $text): string
    {
        $text = is_string($text) ? strip_tags($text) : '';
        return trim($text);
    }
}
