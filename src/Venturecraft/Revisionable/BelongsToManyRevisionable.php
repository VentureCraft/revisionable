<?php

namespace Venturecraft\Revisionable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BelongsToManyRevisionable extends BelongsToMany
{
    /**
     * Sync the intermediate tables with a list of IDs or collection of models.
     *
     * @param  array  $ids
     * @param  bool   $detaching
     *
     * @return array
     */
    public function sync($ids, $detaching = true)
    {
        // @todo
        // Instead of using a pre and post sync, we can rely on the array
        // returned in $changes if this PR gets merged into Laravel
        // https://github.com/laravel/framework/pull/10100

        $this->parent->preSync($this->relationName);

        $changes = parent::sync($ids, $detaching);

        $this->parent->postSync($this->relationName, $ids);

        return $changes;
    }
}
