<?php

namespace Modules\Flowmaker\Models;

use Modules\Flowmaker\Models\Node;
use Modules\Wpbox\Models\Contact as ModelsContact;

class Contact extends ModelsContact
{
    public function changeVariables($content){
        //Change the variables in the content
        $content=str_replace('{{contact_name}}',$this->name,$content);
        $content=str_replace('{{contact_phone}}',$this->phone,$content);
        $content=str_replace('{{contact_email}}',$this->email,$content);
        $content=str_replace('{{contact_last_message}}',$this->last_message,$content);

        //Replace the country
        $content=str_replace('{{contact_country}}',$this->country->name,$content);


        //Get the custom fields
        $fields=$this->fields;
        foreach ($fields as $key => $field) {
            $content=str_replace('{{'.$field->name.'}}',$field->pivot->value,$content);
        }
        
        
        return $content;
    }
    
    //Add contact state
    public function contactState()
    {
        return $this->hasMany(ContactState::class);
    }

    public function getContactState($flowId){
        $states = ContactState::where('contact_id', $this->id)->where('flow_id', $flowId)->get();
        $result = [];
        foreach($states as $state) {
            if (is_string($state->value)) {
                $decoded = json_decode($state->value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $state->value = $decoded;
                }
            }
            $result[] = $state;
        }
        return $result;
    }

    public function clearAllContactState($flowId){
        ContactState::where('contact_id', $this->id)->where('flow_id', $flowId)->delete();
    }

    public function clearContactState($flowId, $state){
        ContactState::where('contact_id', $this->id)->where('flow_id', $flowId)->where('state', $state)->delete();
    }

    public function setContactState($flowId, $state, $value){
        ContactState::updateOrCreate(
            [
                'contact_id' => $this->id,
                'flow_id' => $flowId,
                'state' => $state
            ],
            [
                'value' => $value
            ]
        );
    }

     //Update or set multiple contact states
     public function updateContactStates($flowId, $states){
        foreach($states as $state){
            $this->setContactState($flowId, $state['state'], $state['value']);
        }
    }

    public function getContactStateValue($flowId, $state){
        $contactState = ContactState::where('contact_id', $this->id)->where('flow_id', $flowId)->where('state', $state)->first();
        return $contactState ? $contactState->value : null;
    }
}