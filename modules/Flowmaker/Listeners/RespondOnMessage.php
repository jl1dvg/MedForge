<?php

namespace Modules\Flowmaker\Listeners;

use App\Models\Company;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Models\Flow;
use Modules\Wpbox\Models\Reply;

class RespondOnMessage
{

    public function handleMessageByContact($event){
        try {
            $contact=$event->message->contact;
            $message=$event->message;
            if($contact->enabled_ai_bot&&!$message->bot_has_replied){
            
                //Based on the contact company, find this company firs active AI Bot
                $company_id= $contact->company_id;


                //Get the company
                $company=Company::findOrFail($company_id);

                Log::info("Message received in flowmaker");

                //Get all the flow from the company
                $flows=Flow::where('company_id',$company_id)->get();

                //Loop through the flows and check if the message matches the flow
                foreach($flows as $flow){
                  Log::info("Flow: ".$flow->name);
                  $flow->processMessage($message);
                  Log::info("Flow processed");
                }
                
                
    
            }
        } catch (\Throwable $th) {
           
        }
       
        


    }



    public function subscribe($events)
    {
        $events->listen(
            'Modules\Wpbox\Events\ContactReplies',
            [RespondOnMessage::class, 'handleMessageByContact']
        );
    }

}
