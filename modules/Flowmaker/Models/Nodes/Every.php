<?php

namespace Modules\Flowmaker\Models\Nodes;

use Illuminate\Support\Facades\Log;

class Every extends Node
{
    public function process($message, $data)
    {
        Log::info('Processing message in every message node', ['message' => $message, 'data' => $data]);

        // Get every settings from node data
        $settings = $this->getDataAsArray()['settings'] ?? [];
        
        // Process all outgoing edges since this is an "Every" node
        foreach ($this->outgoingEdges as $edge) {
            $nextNode = $edge->getTarget();
            if ($nextNode) {
                $nextNode->process($message, $data);
            }
        }

        return [
            'success' => true
        ];
    }

    protected function getNextNodeId($param=null)
    {
        // Every node processes all outgoing edges, so we don't need to select just one
        // This method is included for compatibility with the Node parent class
        if (!empty($this->outgoingEdges)) {
            return $this->outgoingEdges[0]->getTarget();
        }
        return null;
    }
}
