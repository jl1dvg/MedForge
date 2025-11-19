<?php

namespace Modules\Flowmaker\Models\Nodes;

use App\Models\Company;
use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Models\Contact;
use Illuminate\Support\Facades\Http;

class Buttons extends Node
{
    public function listenForReply($message, $data){
       Log::info('Listening for reply in buttons node');

       //Get extra data
       $extraData = $data->extra;
       $node=null;
       $elseNode = $this->getNextNodeId("else");
       if($extraData!=null || $extraData!=''){
            Log::info('Extra data found', ['extraData' => $extraData]);

            $settings = $this->getDataAsArray()['settings'];

            $activeButtons = $settings['activeButtons'] ?? 0;

           

            for ($i = 1; $i <= $activeButtons; $i++) {
                $buttonKey = "button{$i}";
                if (isset($settings[$buttonKey]) && $settings[$buttonKey] !== null) {
                    $btnID= "button-{$i}_id{$this->id}_flow{$this->flow_id}";
                    if($btnID==$extraData){
                        Log::info('Button ID found', ['btnID' => $btnID]);

                        //Get the first 8 characters of the btnID
                        $btnIDsub = substr($btnID, 0, 8);
                        Log::info('Button ID sub', ['btnIDsub' => $btnIDsub]);

                        //Get node with handle $btnID
                        $node = $this->getNextNodeId($btnIDsub);
                        if($node!=null){
                            Log::info('Next node found', ['node' => $node]);
                        }else{
                            Log::info('Node not found, go with else case');
                            $node = $elseNode;
                            if($node!=null){
                                Log::info('Else node found', ['node' => $node]);
                            }else{
                                Log::info('Else node not found');
                            }
                        }
                    }
                }
            }
        
       }else{
            Log::info('No extra data found');
       }

      

       //Clear the current node from the contact state
       $contact = Contact::find($data['contact_id']);
       Log::info("clear current node from contact state for contact ".$contact->id." and flow ".$this->flow_id);
       $contact->clearContactState($this->flow_id, 'current_node');
       Log::info("current node cleared");

       if($node!=null){
            Log::info('Node found, process it');
                $node->process($message, $data);
        }else if($elseNode!=null){
                Log::info('Node not found, go with else case');
                $elseNode->process($message, $data);
        }else {
                Log::info('Node not found, Else node not found');
        }

       
       
    }
    public function process($message, $data)
    {
        Log::info('Processing message in buttons node', ['message' => $message, 'data' => $data]);
        if($this->isStartNode){
            //In this case we need to listen for a reply
            $this->listenForReply($message, $data);
            return [
                'success' => true
            ];
        }
        $contact = Contact::find($data['contact_id']);

        //Get settings
        $settings = $this->getDataAsArray()['settings'];

        //Process the message content with variables
        $header = $contact->changeVariables($settings['header'] ?? '');
        $body = $contact->changeVariables($settings['body'] ?? '');
        $footer = $contact->changeVariables($settings['footer'] ?? '');

        //Prepare buttons array
        $buttons = [];
        $activeButtons = $settings['activeButtons'] ?? 0;

        for ($i = 1; $i <= $activeButtons; $i++) {
            $buttonKey = "button{$i}";
            if (isset($settings[$buttonKey]) && $settings[$buttonKey] !== null) {
                $buttons[] = [
                    'id' => "button-{$i}_id{$this->id}_flow{$this->flow_id}",
                    'title' => $contact->changeVariables($settings[$buttonKey])
                ];
            }
        }

        //Get the token from the company
        $company = Company::find($contact->company_id);
        $token = $company->getConfig('plain_token', '');

        // Prepare the API request payload
        $payload = [
            'token' => $token,
            'phone' => $contact->phone,
            'message' => $body,
            'header' => $header,
            'footer' => $footer,
            'buttons' => $buttons
        ];

        Log::info('Button message payload', ['payload' => $payload]);

        // Make the API call
        try {
            $response = Http::post(config('app.url').'/api/wpbox/sendmessage', $payload);
            Log::info('Button message API response', ['response' => $response->json()]);
            
            if (!$response->successful()) {
                Log::error('Failed to send button message', ['error' => $response->body()]);
            } else {
                //Set the user state
                $contact->setContactState($this->flow_id, 'current_node', $this->id);
            }
        } catch (\Exception $e) {
            Log::error('Error sending button message', ['error' => $e->getMessage()]);
        }

        return [
            'success' => true
        ];
    }

    protected function getNextNodeId($handleId = null)
    {
        // Find the edge that connects from this node based on true/false result
        foreach ($this->outgoingEdges as $edge) {
            if (str_contains($edge->getSourceHandle(), $handleId)) {
                return $edge->getTarget();
            }
        }
        return null;
    }
}
