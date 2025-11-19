<?php

namespace Modules\Flowmaker\Models;

use Illuminate\Database\Eloquent\Model;
use App\Scopes\CompanyScope;
use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Models\Nodes\Edge;
use Modules\Flowmaker\Models\Nodes\Every;
use Modules\Flowmaker\Models\Nodes\Node;
use Modules\Flowmaker\Models\Nodes\End;
use Modules\Flowmaker\Models\Nodes\Keyword;
use Modules\Flowmaker\Models\Nodes\Media;
use Modules\Flowmaker\Models\Nodes\Message;
use Modules\Flowmaker\Models\Nodes\Template;
use Modules\Flowmaker\Models\Nodes\Branch;
use Modules\Flowmaker\Models\Nodes\Buttons;

class Flow extends Model
{
    protected $table = 'flows';
    public $guarded = [];

    // Define relationships with other models here
    //Has many replies
    public function replies(){
        return $this->hasMany('Modules\Wpbox\Models\Reply');
    }

    // Define any custom methods or scopes here
    protected static function booted(){
        static::addGlobalScope(new CompanyScope);

        static::creating(function ($model){
           $company_id=session('company_id',null);
            if($company_id){
                $model->company_id=$company_id;
            }
        });
    }

    public function processMessage($data){
        Log::info("================================");
        Log::info('Processing message in flow', ['flow' => $this->id, 'data' => $data]);

        /*
        {"flow":2,"data":{"Modules\\Wpbox\\Models\\Message":{"contact_id":1,"company_id":1,"value":"Daniel","header_image":"","header_document":"","header_video":"","header_audio":"","header_location":"","is_message_by_contact":true,"is_campign_messages":false,"status":1,"buttons":"[]","components":"","fb_message_id":"22","updated_at":"2025-04-18T11:16:42.000000Z","created_at":"2025-04-18T11:16:42.000000Z","id":42,"extra":null,"contact":{"id":1,"name":"Daniel Dimov","phone":"+38978203673","avatar":"https://secure.gravatar.com/avatar/e2909c35cdbad84bf2b6059fe7eab2444cd6bd9fbf8af59918a1d0f4901c8ad2?s=128","country_id":124,"company_id":1,"deleted_at":null,"created_at":"2025-04-17T20:23:24.000000Z","updated_at":"2025-04-18T11:15:59.000000Z","last_reply_at":"2025-04-18 11:15:58","last_client_reply_at":"2025-04-18 11:15:58","last_support_reply_at":"2025-04-18 11:08:43","last_message":"Daniel","is_last_message_by_contact":1,"has_chat":1,"resolved_chat":0,"user_id":null,"enabled_ai_bot":1,"subscribed":1,"email":"daniel@mobidonia.com","language":"none"}}}} 
        */
        try{

            $message = $data->value;
            Log::info("Message: ".$message);

            $contact = $data->contact_id;
            Log::info("Contact: ".$contact);

             //Get the Flow's data
            $flowData = json_decode($this->flow_data, false);

            Log::info('Flow data', ['flowData' => $flowData]);

            //Try to find the start node from contact state
            $contact = Contact::findOrFail($contact);
            $startNode = $contact->getContactStateValue($this->id, 'current_node');

            Log::info('Start node from contact state', ['startNode' => $startNode]);

            $graph = $this->makeGraph($flowData->nodes, $flowData->edges,$startNode);
            Log::info('Graph node '.$graph->id);

            //Process the graph
            $graph->process($message, $data);

        }catch(\Exception $e){
            Log::error("Error processing message in flow", ['error' => $e->getMessage()]);
        }

    }



    private function makeGraph($nodes, $edgesArray,$startNode){
        //Convert the nodes to objects
        $nodes = array_reduce($nodes, function($carry, $node) {
            //Convert the node to an array
            $nodeArray = (array)$node;
            if($nodeArray['type'] === 'keyword_trigger'){
                $theNewNode = new Keyword($nodeArray, []);
            }else if($nodeArray['type'] === 'message'){
                $theNewNode = new Message($nodeArray, []);
            }else if($nodeArray['type'] === 'incomingMessage'){
                $theNewNode = new Every($nodeArray, []);
            }else if($nodeArray['type'] === 'end'){
                $theNewNode = new End($nodeArray, []);
            }else if($nodeArray['type'] === 'image' || $nodeArray['type'] === 'video' || $nodeArray['type'] === 'pdf'){
                $theNewNode = new Media($nodeArray, []);
            }else if($nodeArray['type'] === 'template'){
                $theNewNode = new Template($nodeArray, []);
            }else if($nodeArray['type'] === 'branch'){
                $theNewNode = new Branch($nodeArray, []);
            }else if($nodeArray['type'] === 'quick_replies'){
                $theNewNode = new Buttons($nodeArray, []);
            }else{
                $theNewNode = new Node($nodeArray, []);
            }
            $theNewNode->flow_id=$this->id;
            $carry[$nodeArray['id']] = $theNewNode;
            return $carry;
        }, []);

        //Log::info('Nodes', ['nodes' => $nodes]);

    

        //Convert the edges to objects
        $edges = array_reduce($edgesArray, function($carry, $edge) {
            $edgeArray = (array)$edge;
            $carry[$edgeArray['id']] = new Edge($edgeArray);
            return $carry;
        }, []);

        //Make the graph, by looping through the edges, and assign the source and target nodes
        foreach ($edges as $edge) {
            try{
                $source = $nodes[$edge->getSourceId()];
                $target = $nodes[$edge->getTargetId()];

                //Add the edge to the source node
                $source->addOutgoingEdge($edge);

                //Add the edge to the target node
                $target->addIncomingEdge($edge);

                $edge->setSource($nodes[$edge->getSourceId()]);
                $edge->setTarget($nodes[$edge->getTargetId()]);
            }catch(\Exception $e){
                Log::error('Error adding edge to nodes', ['error' => $e->getMessage()]);
            }
        }

        //Return the graph, it is the first node
        if($startNode){
            Log::info('Using provided start node', ['startNode' => $startNode]);
            $nodes[$startNode]->isStartNode = true;
            return $nodes[$startNode];
        }else{
            $foundStartNode = $this->findStartNode($nodes);
            Log::info('Found start node based on position and type', ['startNode' => $foundStartNode->id]);
            $foundStartNode->isStartNode = true;
            return $foundStartNode;
        }
    }

    /**
     * Find the start node based on position and type
     * The start node should be the node with the lowest x position
     * and should be one of these types: keyword_trigger, incoming_message, opening_hours, template
     * 
     * @param array $nodes Array of nodes
     * @return Node The start node
     */
    private function findStartNode(array $nodes) {
        $validTypes = ['keyword_trigger','incoming_message'];
        $startNode = null;
        $lowestX = PHP_FLOAT_MAX;

        foreach ($nodes as $node) {
            // Skip if not a valid start node type
            if (!in_array($node->type, $validTypes)) {
                continue;
            }

            // Get the x position from the node's data
            $position = $node->position ?? (object)['x' => PHP_FLOAT_MAX];
            $x = $position->x ?? PHP_FLOAT_MAX;

            Log::info('Checking potential start node', [
                'id' => $node->id,
                'type' => $node->type,
                'x' => $x,
                'current_lowest_x' => $lowestX
            ]);

            if ($x < $lowestX || $x === $lowestX) {
                $lowestX = $x;
                $startNode = $node;
            }
        }

        if (!$startNode) {
            Log::error('No valid start node found! Using first node as fallback.');
            $startNode = reset($nodes);
        }

        return $startNode;
    }
}
