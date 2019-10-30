<?php

namespace Venturecraft\Revisionable;

use FuquIo\LaravelDisks\ServiceProvider;
use Illuminate\Routing\Route as RouteInfo;

class RevisionableResolver{
    /**
     * @var \Illuminate\Config\Repository
     */
    private $binding_lookup;

    /**
     * @var RouteInfo
     */
    private $route;


    /**
     * Readable constructor.
     *
     * @param RouteInfo $route
     */
    public function __construct(RouteInfo $route){
        $this->route            = $route;
        $this->binding_lookup   = config(\Venturecraft\Revisionable\ServiceProvider::SHORT_NAME . '.revisionable-model-binding');
    }

    public function bind($revisionable){
        list($alias, $id)= explode('-', $revisionable);
        if(empty($this->binding_lookup[$alias])){
            abort(404, 'No binding instruction found.');
        }

        $class = $this->binding_lookup[$alias];
        return $class::findOrFail($id);
    }

}
