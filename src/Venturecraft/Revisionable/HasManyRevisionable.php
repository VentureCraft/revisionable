<?php

namespace Venturecraft\Revisionable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HasManyRevisionable extends HasMany
{
    /**
     * Name of the relationship.
     *
     * @var string
     */
    protected $relation;

    /**
     * Create a new has one or many relationship instance.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Model  $parent
     * @param  string  $foreignKey
     * @param  string  $localKey
     * @param  string  $relation
     */
    public function __construct(Builder $query, Model $parent, $foreignKey, $localKey, $relation)
    {
        $this->relation = $relation;

        parent::__construct($query, $parent, $foreignKey, $localKey);
    }

    /**
     * Attach a model instance to the parent model and trigger revisions.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function save(Model $model)
    {
        $this->parent->preSaveChild($this->relation, $model);

        if ($model = parent::save($model)) {
            $this->parent->postSaveChild($this->relation, $model);
        }

        return $model;
    }
}
