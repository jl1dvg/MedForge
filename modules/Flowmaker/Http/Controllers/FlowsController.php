<?php

namespace Modules\Flowmaker\Http\Controllers;

use Modules\Flowmaker\Models\Flow;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Wpbox\Models\Reply;

class FlowsController extends Controller
{
    /**
     * Provide class.
     */
    private $provider = Flow::class;

    /**
     * Web RoutePath for the name of the routes.
     */
    private $webroute_path = 'flows.';

    /**
     * View path.
     */
    private $view_path = 'flowmaker::flows.';

    /**
     * Parameter name.
     */
    private $parameter_name = 'flow';

    /**
     * Title of this crud.
     */
    private $title = 'flow';

    /**
     * Title of this crud in plural.
     */
    private $titlePlural = 'flows';

    private function getFields($class='col-md-4')
    {
        $fields=[];
        
        //Add name field
        $fields[0]=['class'=>$class, 'ftype'=>'input', 'name'=>'Name', 'id'=>'name', 'placeholder'=>'Enter name', 'required'=>true];

        //Return fields
        return $fields;
    }


    private function getFilterFields(){
        $fields=$this->getFields('col-md-3', 'qr');
        return $fields;
    }

    /**
     * Auth checker functin for the crud.
     */
    private function authChecker()
    {
        $this->ownerAndStaffOnly();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $this->authChecker();

        $items=$this->provider::orderBy('id', 'desc');
        $items=$items->paginate(config('settings.paginate'));

        
        

        //Regular, bot ant template based bot
        $setup=[
            'usefilter'=>null,
            'title'=>__('Flows'),
            'action_link2'=>route($this->webroute_path.'create'),
            'action_name2'=>__('Create flow'),
            'items'=>$items,
            'item_names'=>$this->titlePlural,
            'webroute_path'=>$this->webroute_path,
            'fields'=>$this->getFields('col-md-3'),
            'filterFields'=>$this->getFilterFields(),
            'custom_table'=>true,
            'parameter_name'=>$this->parameter_name,
            'parameters'=>count($_GET) != 0,
            'hidePaging'=>false,
        ];
        return view($this->view_path.'index', ['setup' => $setup]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $this->authChecker();

        
        $fields = $this->getFields( 'col-md-6', );

        return view('general.form', ['setup' => [
            'title'=>__('crud.new_item', ['item'=>__('Flow')]),
            'action_link'=>route($this->webroute_path.'index',['type'=>'qr']),
            'action_name'=>__('crud.back'),
            'iscontent'=>true,
            'action'=>route($this->webroute_path.'store')
        ],
        'fields'=>$fields ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->authChecker();
        
        //Create new field
        $field = $this->provider::create([
            'name' => $request->name,
        ]);
        $field->save();

        return redirect()->route($this->webroute_path.'index')->withStatus(__('crud.item_has_been_added', ['item'=>__($this->title)]));
    }

    

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Contact  $contacts
     * @return \Illuminate\Http\Response
     */
    public function edit(Flow $flow)
    {
        $this->authChecker();

        $fields = $this->getFields('col-md-6');
        $fields[0]['value'] = $flow->name;
       

    
        $parameter = [];
        $parameter[$this->parameter_name] = $flow->id;

        return view($this->view_path.'edit', ['setup' => [
            'title'=>__('crud.edit_item_name', ['item'=>__($this->title), 'name'=>$flow->name]),
            'action_link'=>route($this->webroute_path.'index'),
            'action_name'=>__('crud.back'),
            'iscontent'=>true,
            'isupdate'=>true,
            'action'=>route($this->webroute_path.'update', $parameter),
        ],
        'fields'=>$fields, ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Contact  $contacts
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $this->authChecker();
        $item = $this->provider::findOrFail($id);
        $item->name = $request->name;
        $item->update();

        return redirect()->route($this->webroute_path.'index')->withStatus(__('crud.item_has_been_updated', ['item'=>__($this->title)]));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Contact  $contacts
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $this->authChecker();
        $item = $this->provider::findOrFail($id);

        //Delete all replies to this item
        //Set all next_reply_id to null
        Reply::where('flow_id', $item->id)->orderBy('id', 'desc')->get()->each(function($reply) {
            $reply->next_reply_id = null;
            $reply->update();
        });
        Reply::where('flow_id', $item->id)->delete();
        $item->delete();
        return redirect()->route($this->webroute_path.'index')->withStatus(__('crud.item_has_been_removed', ['item'=>__($this->title)]));
    }
    
}
