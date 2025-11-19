<?php

namespace Modules\Flowmaker\Models\Nodes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;


class Node extends Model
{
   
    public $edges;
    public $incomingEdges;
    public $outgoingEdges;
    public $data;
    public $id;
    public $type;
    public $isStartNode;

    public function __construct($nodeData, $edges)
    {
       // Log::info('Node data to construct', ['nodeData' => $nodeData]);
        $this->data = $nodeData['data'] ?? [];
        $this->edges = $edges;
        $this->id = $nodeData['id'];
        $this->type = $nodeData['type'];
        $this->incomingEdges = [];
        $this->outgoingEdges = [];
        $this->isStartNode = false;
    }

    public function getDataAsArray()
    {
        //Std to array
        return json_decode(json_encode($this->data), true);
    }

    public function addIncomingEdge($edge)
    {
        $this->incomingEdges[] = $edge;
    }

    public function addOutgoingEdge($edge)
    {
        $this->outgoingEdges[] = $edge;
    }

    /**
     * Process the message and return next node information
     * 
     * @param string $message The message to process
     * @param array $data Additional data needed for processing
     * @return array Processing result with success status and next node ID
     */
     public function process($message, $data){
       Log::info('Processing message in normal node', ['message' => $message, 'data' => $data]);
     }

    /**
     * Get the next connected node ID
     */
    protected function getNextNodeId($param=null)
    {
        if (empty($this->edges)) {
            return null;
        }

        // Find edge that connects from this node
        foreach ($this->edges as $edge) {
            if ($edge['source'] === $this->id) {
                return $edge['target'];
            }
        }

        return null;
    }

    //Serialize the node
    public function serialize()
    {
        return json_encode([
            'id' => $this->id,
            'type' => $this->type
        ]);
    }

    public function getMediaUrl($mediaUrl){
        // Check if the mediaUrl already has /storage/ in it
        if (strpos($mediaUrl, '/storage/') === 0) {
            // If it starts with /storage/, just add the base URL
            return url($mediaUrl);
        } else {
            // Otherwise use Storage::url which adds /storage/ prefix
            return url(Storage::url($mediaUrl));
        }
    }
}
