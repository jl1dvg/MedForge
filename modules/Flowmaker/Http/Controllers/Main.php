<?php

namespace Modules\Flowmaker\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Modules\Contacts\Models\Field;
use Modules\Flowmaker\Models\Flow;
use Modules\Wpbox\Models\Reply;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Wpbox\Models\Template;

class Main extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function edit(Flow $flow)
    {
        //Get the company custom fields
        $customFields=Field::where('company_id',$flow->company_id)->get();

        $variables=[
            ["label" => "Contact Name", "value" => "contact_name", "category" => "Contact"],
            ["label" => "Contact Phone", "value" => "contact_phone", "category" => "Contact"],
            ["label" => "Email", "value" => "contact_email", "category" => "Contact"],
            ["label" => "Country", "value" => "contact_country", "category" => "Contact"],
            ["label" => "Last Message", "value" => "contact_last_message", "category" => "Contact"]
        ];

        //Now add the custom fields to the variables
        foreach ($customFields as $customField) {
            $variables[]=[
                "label" => $customField->name,
                "value" => $customField->name,
                "category" => "Custom Field"
            ];
        }

        //Get the company templates
        $templates=Template::where('company_id',$flow->company_id)->get();
        //Loop throught all the templates anc convert the components from string to object
        foreach($templates as $template){
            $template->components=json_decode($template->components);
        }

        $data=[
            'flow'=>$flow,
            'variables'=>$variables,
            'templates'=>$templates
        ];
        return view('flowmaker::index')->with('data', json_encode($data));
    }
   

    public function script()
    {
        // Find the first .js file in the public/build/assets directory
        $files = glob(__DIR__.'/../../public/build/assets/*.js');
        
        if (empty($files)) {
            abort(404, 'JavaScript file not found');
        }
        
        try {
            $script = file_get_contents($files[0]);
            return response($script)->header('Content-Type', 'application/javascript');
        } catch (\Exception $e) {
            abort(500, 'Error loading JavaScript file');
        }
    }

    //CSS
    public function css()
    {
        $files = glob(__DIR__.'/../../public/build/assets/*.css');
        
        if (empty($files)) {
            abort(404, 'CSS file not found');
        }
        
        try {
            $css = file_get_contents($files[0]);
            return response($css)->header('Content-Type', 'text/css');
        } catch (\Exception $e) {
            abort(500, 'Error loading CSS file');
        }
    }

    public function updateFlow(Request $request, Flow $flow)
    {
        //Set the flow data
        $flow->flow_data = $request->all();

        //Respond ok
        $flow->save();
        return response()->json(['status'=>'ok']);
    }

    /**
     * Upload media files (images, videos, PDFs)
     * @param Request $request
     * @return Response
     */
    public function uploadMedia(Request $request)
    {
        try {
            $type = $request->input('type');
            Log::info('Upload media', ['type' => $type]);
            // Validate request
            $request->validate([
                'file' => 'required|file|max:50000', // Max 50MB
                'type' => 'required|in:image,video,pdf,document',
            ]);

            // Get the file and type
            $file = $request->file('file');
          

            // Set validation rules based on type
            switch ($type) {
                case 'image':
                    $request->validate([
                        'file' => 'mimes:jpeg,png,jpg,gif,webp|max:10000', // Max 10MB for images
                    ]);
                    $directory = 'flowmaker/images';
                    break;
                case 'video':
                    $request->validate([
                        'file' => 'mimes:mp4,webm,ogg,avi,mov|max:50000', // Max 50MB for videos
                    ]);
                    $directory = 'flowmaker/videos';
                    break;
                case 'pdf':
                case 'document':
                    $request->validate([
                        'file' => 'mimes:pdf|max:20000', // Max 20MB for PDFs
                    ]);
                    $directory = 'flowmaker/pdfs';
                    break;
                default:
                    return response()->json(['error' => 'Invalid file type'], 400);
            }

            // Generate unique filename
            $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();

            // Store the file
            $laravel_file_resource=$file;
            //$path = $file->storeAs($directory, $fileName, 'public');

            if (config('settings.use_s3_as_storage', false)) {
                //S3 - store per company
                $path = $laravel_file_resource->storePublicly('uploads/companies', 's3');
    
                $full_url= Storage::disk('s3')->url($path);
            } else {
                $path = $laravel_file_resource->store($directory, 'public_uploads');
                $url = config('app.url').'/uploads/'.$path;
    
                $full_url= preg_replace('#(https?:\/\/[^\/]+)\/\/#', '$1/', $url);
            }


            // Return the media URL
            return response()->json([
                'status' => 'success',
                'url' => $full_url,
                'type' => $type
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
