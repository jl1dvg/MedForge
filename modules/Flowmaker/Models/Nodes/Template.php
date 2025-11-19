<?php

namespace Modules\Flowmaker\Models\Nodes;

use App\Models\Company;
use Illuminate\Support\Facades\Log;
use Modules\Wpbox\Models\Campaign;
use Modules\Flowmaker\Models\Contact;
use Modules\Wpbox\Models\Template as ModelsTemplate;
use Illuminate\Support\Facades\Http;

class Template extends Node
{
    public function listenForReply($message, $data)
    {
        Log::info('Listening for reply in template node', ['message' => $message]);

        // Get the template settings
        $settings = $this->getDataAsArray()['settings'];
        $templateID = $settings['selectedTemplateId'];
        $template = ModelsTemplate::find($templateID);

        // Get template components
        $templateComponents = json_decode($template->components, true);
        
        $nextNode=null;
        if ($templateComponents) {
            foreach ($templateComponents as $component) {
                if ($component['type'] === 'BUTTONS') {
                    foreach ($component['buttons'] as $index => $button) {
                        if (strtolower($button['text']) === strtolower($message)) {
                            Log::info('Button match found', ['button' => $button, 'message' => $message, 'index' => $index]);
                            $nextNode = $this->getNextNodeId("quick-reply-".$index);
                        }
                    }
                }
            }
        }

        // Clear the current node from the contact state
        $contact = Contact::find($data['contact_id']);
        Log::info("clear current node from contact state for contact ".$contact->id." and flow ".$this->flow_id);
        $contact->clearContactState($this->flow_id, 'current_node');
        Log::info("current node cleared");

        // Get the next node and process it
        //else node
        $elseNode = $this->getNextNodeId("else");
    

        if ($nextNode != null) {
            Log::info('Next node found, process it');
            $nextNode->process($message, $data);
        } else if ($elseNode != null) {
            Log::info('No next node found, go with else case');
            $elseNode->process($message, $data);
        } else {
            Log::info('No next node found, Else node not found');
        }
    }

    public function process($message, $data)
    {
        Log::info('Processing message in template node', ['message' => $message, 'data' => $data]);

        if ($this->isStartNode) {
            // In this case we need to listen for a reply
            $this->listenForReply($message, $data);
            return [
                'success' => true
            ];
        }

        $contact = Contact::find($data['contact_id']);

        //Template
        $settings=$this->getDataAsArray()['settings'];
        $templateID=$settings['selectedTemplateId'];
        $template = ModelsTemplate::find($templateID);

        //Make an api call to our api to send the template message
        $components = [];

        Log::info('Template, settings,message,data,flow_id', ['template' => $template,'settings' => $settings,'message' => $message,'data' => $data,'flow_id' => $this->flow_id]);
        
        // Process parameters for the API call
        if (isset($settings['parameters'])) {
            $bodyParameters = [];
            $headerParameters = [];
            
            foreach ($settings['parameters'] as $key => $value) {
                $parts = explode('_', $key);
                $type = strtolower($parts[0]);
                
                if ($type === 'header') {
                    $headerParameters[] = [
                        'type' => 'text',
                        'text' => $contact->changeVariables($value)
                    ];
                } else if ($type === 'body') {
                    $bodyParameters[] = [
                        'type' => 'text',
                        'text' => $contact->changeVariables($value)
                    ];
                } else {
                    // For the old format where we just have numbers as keys
                    $bodyParameters[] = [
                        'type' => 'text',
                        'text' => $contact->changeVariables($value)
                    ];
                }
            }
            
            // Add header component if we have header parameters
            if (!empty($headerParameters)) {
                $components[] = [
                    'type' => 'header',
                    'parameters' => $headerParameters
                ];
            }
            
            // Add body component if we have body parameters
            if (!empty($bodyParameters)) {
                $components[] = [
                    'type' => 'body',
                    'parameters' => $bodyParameters
                ];
            }
        }

        //When there is a component of type HEADER and the format is IMAGE, we need to assign the fileUrl from settings
        $templateComponents=json_decode($template->components,true);
        if ($templateComponents) {
            foreach ($templateComponents as $component) {
                if ($component['type'] === 'HEADER' && $component['format'] === 'IMAGE' && isset($settings['fileUrl'])) {
                    $components[] = [
                        'type' => 'header',
                        'parameters' => [
                            [
                                'type' => 'image',
                                'image' => [
                                    'link' => $settings['fileUrl']
                                ]
                            ]
                        ]
                    ];
                }
                if ($component['type'] === 'HEADER' && $component['format'] === 'DOCUMENT' && isset($settings['fileUrl'])) {
                    $components[] = [
                        'type' => 'header',
                        'parameters' => [
                            [
                                'type' => 'document',
                                'document' => [
                                    'link' => $settings['fileUrl']
                                ]
                            ]
                        ]
                    ];
                }
                if ($component['type'] === 'HEADER' && $component['format'] === 'VIDEO' && isset($settings['videoUrl'])) {
                    $components[] = [
                        'type' => 'header',
                        'parameters' => [
                            [
                                'type' => 'video',  
                                'video' => [
                                    'link' => $settings['videoUrl']
                                ]
                            ]
                        ]
                    ];
                }
            }
        }

        //Get the token from the company
        $company=Company::find($contact->company_id);
        $token=$company->getConfig('plain_token','');

        // Prepare the API request payload
        $payload = [
            'token' => $token,
            'phone' => $contact->phone,
            'template_name' => $template->name,
            'template_language' => $template->language ?? 'en',
            'components' => $components
        ];
        Log::info('Payload', ['payload' => $payload]);

        // Make the API call
        try {
            $response = Http::post(config('app.url').'/api/wpbox/sendtemplatemessage', $payload);
            Log::info('Template message API response', ['response' => $response->json()]);
            
            if (!$response->successful()) {
                Log::error('Failed to send template message', ['error' => $response->body()]);
            }else{
               //Now set the user state
               //Get the always node
               $alwaysNode=$this->getNextNodeId('always');
               if($alwaysNode!=null){
                $alwaysNode->process($message, $data);
               }else{
                Log::info('No always node found, we will wait on reply to our buttons');
                $contact->setContactState($this->flow_id, 'current_node', $this->id);
               }
               
              
            }
        } catch (\Exception $e) {
            Log::error('Error sending template message', ['error' => $e->getMessage()]);
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
