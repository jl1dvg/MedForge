<?php

namespace Modules\Flowmaker\Models\Nodes;

use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Models\Contact;

class End extends Node
{
    public function process($message, $data)
    {
        Log::info('Processing message in end node, clearing contact state');
        $contact = Contact::find($data['contact_id']);
        $contact->clearAllContactState($this->flow_id);
        Log::info('Contact state cleared');

        return [
            'success' => true
        ];
    }

    protected function getNextNodeId($param=null)
    {
        // End node has no next node to process
        return null;
    }
}
