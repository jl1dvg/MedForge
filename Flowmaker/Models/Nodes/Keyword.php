<?php

namespace Modules\Flowmaker\Models\Nodes;

use Illuminate\Support\Facades\Log;

class Keyword extends Node
{

    public function process($message, $data)
    {
        Log::info('Processing message in keyword node', ['message' => $message, 'data' => $data]);

        // Get keywords from node data
        $keywords = $this->getDataAsArray()['keywords'] ?? [];
        
        // Check each keyword against the message
        foreach ($keywords as $keyword) {
            $value = $keyword['value'];
            $matchType = $keyword['matchType'];
            
            // Different matching logic based on matchType
            if ($matchType === 'exact') {
                if (strtolower($message) === strtolower($value)) {
                    Log::info('Keyword exact match found', ['keyword' => $keyword]);

                    //Continue with the flow
                    $nextNode = $this->getNextNodeId($keyword['id']);
                    if($nextNode){
                        $nextNode->process($message, $data);
                    }

                    return [
                        'success' => true,
                        //'nextNodeId' => $this->getNextNodeId($keyword['id'])
                    ];
                }
            } else if ($matchType === 'contains') {
                if (str_contains(strtolower($message), strtolower($value))) {

                    Log::info('Keyword contains match found', ['keyword' => $keyword]);

                    //Continue with the flow
                    $nextNode = $this->getNextNodeId($keyword['id']);
                    if($nextNode){
                        $nextNode->process($message, $data);
                    }
                }
            }
        }

        // No matches found
        return [
            'success' => false
        ];
    }

    protected function getNextNodeId($keywordId=null)
    {
        // Find the edge that connects from this node's keyword
        Log::info('Getting next node id', ['keywordId' => $keywordId]);
        Log::info('Outgoing edges', ['outgoingEdges' => $this->outgoingEdges]);
        foreach ($this->outgoingEdges as $edge) {
            if ($edge->getSourceHandle() === 'keyword-' . $keywordId) {
                return $edge->getTarget();
            }
        }
        return null;
    }
}
