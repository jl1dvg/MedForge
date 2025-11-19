<?php

namespace Modules\Flowmaker\Models\Nodes;

use Illuminate\Support\Facades\Log;

class Edge
{
    protected Node $source;
    protected Node $target;
    protected $sourceId;
    protected $targetId;
    protected $sourceHandle;
    protected $targetHandle;
    protected $id;

    public function __construct($edgeData)
    {
       // $this->source = $edgeData['source'];
       // $this->target = $edgeData['target'];
        //Log::info('Edge data to construct', ['edgeData' => $edgeData]);
        $this->sourceId = $edgeData['source'];
        $this->targetId = $edgeData['target'];
        $this->sourceHandle = $edgeData['sourceHandle'] ?? null;
        $this->targetHandle = $edgeData['targetHandle'] ?? null;
        $this->id = $edgeData['id'];
    }

    public function getSourceId()
    {
        return $this->sourceId;
    }

    public function getTargetId() 
    {
        return $this->targetId;
    }

    public function getSource()
    {
        return $this->source;
    }

    public function getTarget()
    {
        return $this->target;
    }

    public function getSourceHandle()
    {
        return $this->sourceHandle;
    }

    public function getTargetHandle()
    {
        return $this->targetHandle;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setSource($source)
    {
        $this->source = $source;
    }

    public function setTarget($target)
    {
        $this->target = $target;
    }
}
