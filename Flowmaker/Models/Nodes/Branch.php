<?php

namespace Modules\Flowmaker\Models\Nodes;

use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Models\Contact;

class Branch extends Node
{
    public function process($message, $data)
    {
        Log::info('Processing message in branch node', ['message' => $message, 'data' => $data]);

        // Get conditions from node data
        $conditions = $this->getDataAsArray()['settings']['conditions'] ?? [];
        
        // Get contact from data
        $contact = Contact::find($data->contact_id);
        
        // Check each condition - using AND operator
        $allConditionsMet = true;
        foreach ($conditions as $condition) {
            $variableId = $condition['variableId'];
            $operator = $condition['operator'];
            $value = $condition['value'];
            
            // Get the actual value from contact
            $actualValue = $contact->changeVariables('{{'.$variableId.'}}');
            Log::info('Actual value', ['actualValue' => $actualValue, 'variableId' => $variableId, 'operator' => $operator, 'value' => $value]);
            
            // Different matching logic based on operator
            switch ($operator) {
                case 'equals':
                    if (strtolower($actualValue) !== strtolower($value)) {
                        $allConditionsMet = false;
                    }
                    break;
                case 'contains':
                    if (!str_contains(strtolower($actualValue), strtolower($value))) {
                        $allConditionsMet = false;
                    }
                    break;
                case 'greater_than':
                    if (!is_numeric($actualValue) || !is_numeric($value) || floatval($actualValue) <= floatval($value)) {
                        $allConditionsMet = false;
                    }
                    break;
                case 'less_than':
                    if (!is_numeric($actualValue) || !is_numeric($value) || floatval($actualValue) >= floatval($value)) {
                        $allConditionsMet = false;
                    }
                    break;
                default:
                    $allConditionsMet = false;
                    break;
            }
            
            // If any condition fails, break early since we're using AND operator
            if (!$allConditionsMet) {
                Log::info('Condition failed', ['condition' => $condition]);
                break;
            }
        }

        // Get the appropriate next node based on condition result
        $nextNode = $this->getNextNodeId($allConditionsMet);
        if ($nextNode) {
            $nextNode->process($message, $data);
        }

        return [
            'success' => true
        ];
    }

    protected function getNextNodeId($isTrue=null)
    {
        // Find the edge that connects from this node based on true/false result
        $handleId = $isTrue ? 'true' : 'false';
        foreach ($this->outgoingEdges as $edge) {
            if (str_contains($edge->getSourceHandle(), $handleId)) {
                return $edge->getTarget();
            }
        }
        return null;
    }
}
