<?php

namespace Ubiquilife\Revisionable;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/*
 * This file is part of the Revisionable package
 *
 *
 */

/**
 * Class RevisionableTrait
 * @package Ubiquilife\Revisionable
 */
trait RevisionableTrait
{
    /**
     * @var array
     */
    private $originalData = array();

    /**
     * @var array
     */
    private $updatedData = array();

    /**
     * @var boolean
     */
    private $updating = false;

    /**
     * @var array
     */
    private $dontKeep = array();

    /**
     * @var array
     */
    private $doKeep = array();

    /**
     * Keeps the list of values that have been updated
     *
     * @var array
     */
    protected $dirtyData = array();

    /**
     * Ensure that the bootRevisionableTrait is called only
     * if the current installation is a laravel 4 installation
     * Laravel 5 will call bootRevisionableTrait() automatically
     */
    public static function boot()
    {
        parent::boot();

        if (!method_exists(get_called_class(), 'bootTraits')) {
            static::bootRevisionableTrait();
        }
    }

    /**
     * Create the event listeners for the saving and saved events
     * This lets us save revisions whenever a save is made, no matter the
     * http method.
     *
     */
    public static function bootRevisionableTrait()
    {
        static::saving(function ($model) {
            $model->preSave();
        });

        static::saved(function ($model) {
            $model->postSave();
        });

        static::created(function ($model) {
            $model->postCreate();
        });

        static::deleted(function ($model) {
            $model->preSave();
            $model->postDelete();
            $model->postForceDelete();
        });
    }

    /**
     * Type strings this model's revisions were recorded under before its
     * current class name - e.g. a model promoted from a shared package
     * namespace into an app's own namespace. revisionable_type is stamped
     * with get_class() at write time, so a class rename orphans every row
     * written under the old name; nothing rewrites history on a rename.
     * Empty by default - override on a model that has been renamed.
     *
     * @return array<string>
     */
    public function legacyRevisionableTypes(): array
    {
        return [];
    }

    /**
     * @return mixed
     */
    public function revisionHistory()
    {
        $relatedClass = get_class(Revisionable::newModel());
        $legacyTypes = $this->legacyRevisionableTypes();

        if (empty($legacyTypes)) {
            return $this->morphMany($relatedClass, 'revisionable');
        }

        $types = array_merge([$this->getMorphClass()], $legacyTypes);

        /** @var \Illuminate\Database\Eloquent\Relations\MorphMany $relation */
        $relation = \Illuminate\Database\Eloquent\Relations\Relation::noConstraints(
            fn () => $this->morphMany($relatedClass, 'revisionable')
        );

        return $relation->where(function ($query) use ($relation, $types) {
            $query->whereIn($relation->getMorphType(), $types)
                ->where($relation->getForeignKeyName(), $this->getKey());
        });
    }

    /**
     * Generates a list of the last $limit revisions made to any objects of the class it is being called from.
     *
     * @param int $limit
     * @param string $order
     * @return mixed
     */
    public static function classRevisionHistory($limit = 100, $order = 'desc')
    {
        $model = Revisionable::newModel();
        return $model->where('revisionable_type', get_called_class())
            ->orderBy('updated_at', $order)->limit($limit)->get();
    }

    /**
    * Invoked before a model is saved. Return false to abort the operation.
    *
    * @return bool
    */
    public function preSave()
    {
        if (!isset($this->revisionEnabled) || $this->revisionEnabled) {
            // if there's no revisionEnabled. Or if there is, if it's true

            $this->originalData = $this->original;
            $this->updatedData = $this->attributes;

            // we can only safely compare basic items,
            // so for now we drop any object based items, like DateTime
            foreach ($this->updatedData as $key => $val) {
                $castCheck = ['object', 'array'];
                if (isset($this->casts[$key]) && in_array(gettype($val), $castCheck) && in_array($this->casts[$key], $castCheck) && isset($this->originalData[$key])) {
                    // Sorts the keys of a JSON object due Normalization performed by MySQL
                    // So it doesn't set false flag if it is changed only order of key or whitespace after comma.
                    //
                    // Seeders and some cast paths leave the value as a PHP array
                    // rather than a JSON string. Handle both without crashing.
                    $updatedValue = is_array($this->updatedData[$key])
                        ? $this->updatedData[$key]
                        : json_decode($this->updatedData[$key], true);
                    $originalValue = is_array($this->originalData[$key])
                        ? $this->originalData[$key]
                        : json_decode($this->originalData[$key], true);

                    $this->updatedData[$key] = json_encode($this->sortJsonKeys($updatedValue));
                    $this->originalData[$key] = json_encode($originalValue);
                } else if (gettype($val) == 'object' && !method_exists($val, '__toString')) {
                    unset($this->originalData[$key]);
                    unset($this->updatedData[$key]);
                    array_push($this->dontKeep, $key);
                }
            }

            // the below is ugly, for sure, but it's required so we can save the standard model
            // then use the keep / dontkeep values for later, in the isRevisionable method
            $this->dontKeep = isset($this->dontKeepRevisionOf) ?
                array_merge($this->dontKeepRevisionOf, $this->dontKeep)
                : $this->dontKeep;

            $this->doKeep = isset($this->keepRevisionOf) ?
                array_merge($this->keepRevisionOf, $this->doKeep)
                : $this->doKeep;

            unset($this->attributes['dontKeepRevisionOf']);
            unset($this->attributes['keepRevisionOf']);

            $this->dirtyData = $this->getDirty();
            $this->updating = $this->exists;
        }
    }


    /**
     * Called after a model is successfully saved.
     *
     * @return void
     */
    public function postSave()
    {
        if (isset($this->historyLimit) && $this->revisionHistory()->count() >= $this->historyLimit) {
            $LimitReached = true;
        } else {
            $LimitReached = false;
        }
        if (isset($this->revisionCleanup)){
            $RevisionCleanup=$this->revisionCleanup;
        }else{
            $RevisionCleanup=false;
        }

        // check if the model already exists
        if (((!isset($this->revisionEnabled) || $this->revisionEnabled) && $this->updating) && (!$LimitReached || $RevisionCleanup)) {
            // if it does, it means we're updating

            $changes_to_record = $this->changedRevisionableFields();

            $revisions = array();

            foreach ($changes_to_record as $key => $change) {
                $original = array(
                    'id' => Str::uuid(),
                    'revisionable_type' => $this->getMorphClass(),
                    'revisionable_id' => $this->getKey(),
                    'key' => $key,
                    'old_value' => Arr::get($this->originalData, $key),
                    'new_value' => $this->updatedData[$key],
                    'user_id' => $this->getSystemUserId(),
                    'on_behalf_of_user_id' => $this->getOnBehalfOfUserId(),
                    'created_at' => new \DateTime(),
                    'updated_at' => new \DateTime(),
                );

                $revisions[] = array_merge($original, $this->getAdditionalFields());
            }

            if (count($revisions) > 0) {
                if($LimitReached && $RevisionCleanup){
                    $toDelete = $this->revisionHistory()->orderBy('id','asc')->limit(count($revisions))->get();
                    foreach($toDelete as $delete){
                        $delete->delete();
                    }
                }
                $revision = Revisionable::newModel();
                \DB::table($revision->getTable())->insert($revisions);
                \Event::dispatch('revisionable.saved', array('model' => $this, 'revisions' => $revisions));
            }
        }
    }

    /**
    * Called after record successfully created
    */
    public function postCreate()
    {

        // Check if we should store creations in our revision history
        // Set this value to true in your model if you want to
        if(empty($this->revisionCreationsEnabled))
        {
            // We should not store creations.
            return false;
        }

        if ((!isset($this->revisionEnabled) || $this->revisionEnabled))
        {
            $revisions[] = array(
                'id' => Str::uuid(),
                'revisionable_type' => $this->getMorphClass(),
                'revisionable_id' => $this->getKey(),
                'key' => self::CREATED_AT,
                'old_value' => null,
                'new_value' => $this->{self::CREATED_AT},
                'user_id' => $this->getSystemUserId(),
                'on_behalf_of_user_id' => $this->getOnBehalfOfUserId(),
                'created_at' => new \DateTime(),
                'updated_at' => new \DateTime(),
            );

            //Determine if there are any additional fields we'd like to add to our model contained in the config file, and
            //get them into an array.
            $revisions = array_merge($revisions[0], $this->getAdditionalFields());

            $revision = Revisionable::newModel();
            \DB::table($revision->getTable())->insert($revisions);
            \Event::dispatch('revisionable.created', array('model' => $this, 'revisions' => $revisions));
        }

    }

    /**
     * If softdeletes are enabled, store the deleted time
     */
    public function postDelete()
    {
        if ((!isset($this->revisionEnabled) || $this->revisionEnabled)
            && $this->isSoftDelete()
            && $this->isRevisionable($this->getDeletedAtColumn())
        ) {
            $revisions[] = array(
                'id' => Str::uuid(),
                'revisionable_type' => $this->getMorphClass(),
                'revisionable_id' => $this->getKey(),
                'key' => $this->getDeletedAtColumn(),
                'old_value' => null,
                'new_value' => $this->{$this->getDeletedAtColumn()},
                'user_id' => $this->getSystemUserId(),
                'on_behalf_of_user_id' => $this->getOnBehalfOfUserId(),
                'created_at' => new \DateTime(),
                'updated_at' => new \DateTime(),
            );

            //Since there is only one revision because it's deleted, let's just merge into revision[0]
            $revisions = array_merge($revisions[0], $this->getAdditionalFields());

            $revision = Revisionable::newModel();
            \DB::table($revision->getTable())->insert($revisions);
            \Event::dispatch('revisionable.deleted', array('model' => $this, 'revisions' => $revisions));
        }
    }

    /**
     * If forcedeletes are enabled, set the value created_at of model to null
     *
     * @return void|bool
     */
    public function postForceDelete()
    {
        if (empty($this->revisionForceDeleteEnabled)) {
            return false;
        }

        if ((!isset($this->revisionEnabled) || $this->revisionEnabled)
            && (($this->isSoftDelete() && $this->isForceDeleting()) || !$this->isSoftDelete())) {

            $revisions[] = array(
                'revisionable_type' => $this->getMorphClass(),
                'revisionable_id' => $this->getKey(),
                'key' => self::CREATED_AT,
                'old_value' => $this->{self::CREATED_AT},
                'new_value' => null,
                'user_id' => $this->getSystemUserId(),
                'on_behalf_of_user_id' => $this->getOnBehalfOfUserId(),
                'created_at' => new \DateTime(),
                'updated_at' => new \DateTime(),
            );

            $revision = Revisionable::newModel();
            \DB::table($revision->getTable())->insert($revisions);
            \Event::dispatch('revisionable.deleted', array('model' => $this, 'revisions' => $revisions));
        }
    }

    /**
     * Attempt to find the user id of the currently logged in user.
     * Supports Cartalyst Sentry/Sentinel based authentication, stock
     * Auth, and the Ubiquilife platform's agent-delegation pattern:
     * when an OAuth application has `agent_user_id` set, the request
     * pipeline (ResolveAgentActor middleware) swaps the actor to the
     * agent identity and stashes the human caller as on-behalf-of.
     * ActorResolver returns the agent's id here, so revisions attribute
     * correctly to the agent.
     **/
    public function getSystemUserId()
    {
        try {
            // Prefer ActorResolver (Ubiquilife platform agent delegation).
            // Fall back transparently when the binding isn't registered
            // (eg. revisionable used standalone outside Ubiquilife).
            try {
                if (class_exists('\Ubiquilife\Appbase\Services\Agent\ActorResolver')
                    && function_exists('app')
                ) {
                    $resolver = app('\Ubiquilife\Appbase\Services\Agent\ActorResolver');
                    if ($resolver && method_exists($resolver, 'actorId')) {
                        $actorId = $resolver->actorId();
                        if ($actorId !== null) {
                            return $actorId;
                        }
                    }
                }
            } catch (\Throwable $resolverError) {
                // Resolver not bound or container unavailable. Fall through
                // to legacy auth lookups below.
            }

            if (class_exists($class = '\SleepingOwl\AdminAuth\Facades\AdminAuth')
                || class_exists($class = '\Cartalyst\Sentry\Facades\Laravel\Sentry')
                || class_exists($class = '\Cartalyst\Sentinel\Laravel\Facades\Sentinel')
            ) {
                return ($class::check()) ? $class::getUser()->id : null;
            } elseif (function_exists('backpack_auth') && backpack_auth()->check()) {
                return backpack_user()->id;
            } elseif (\Auth::check()) {
                return \Auth::user()->getAuthIdentifier();
            }
        } catch (\Exception $e) {
            return null;
        }

        return null;
    }

    /**
     * Ubiquilife agent-delegation companion to getSystemUserId(). Returns
     * the human user id when an agent is acting on their behalf;
     * otherwise null. The `on_behalf_of_user_id` revisions column was
     * added in laravel-appbase migration
     * 2026_05_17_140300_add_on_behalf_of_to_revisions_table.
     */
    public function getOnBehalfOfUserId()
    {
        try {
            if (! class_exists('\Ubiquilife\Appbase\Services\Agent\ActorResolver')
                || ! function_exists('app')
            ) {
                return null;
            }
            $resolver = app('\Ubiquilife\Appbase\Services\Agent\ActorResolver');
            if ($resolver && method_exists($resolver, 'onBehalfOfId')) {
                return $resolver->onBehalfOfId();
            }
        } catch (\Throwable) {
            // resolver not bound; not a delegated request
        }
        return null;
    }


    public function getAdditionalFields()
    {
        $additional = [];
        //Determine if there are any additional fields we'd like to add to our model contained in the config file, and
        //get them into an array.
        $fields = config('revisionable.additional_fields', []);
        foreach($fields as $field) {
            if(Arr::has($this->originalData, $field)) {
                $additional[$field]  =  Arr::get($this->originalData, $field);
            }
        }

        return $additional;
    }

    /**
     * Get all of the changes that have been made, that are also supposed
     * to have their changes recorded
     *
     * @return array fields with new data, that should be recorded
     */
    private function changedRevisionableFields()
    {
        $changes_to_record = array();
        foreach ($this->dirtyData as $key => $value) {
            // check that the field is revisionable, and double check
            // that it's actually new data in case dirty is, well, clean
            if ($this->isRevisionable($key) && !is_array($value)) {
                if (!array_key_exists($key, $this->originalData) || $this->originalData[$key] != $this->updatedData[$key]) {
                    $changes_to_record[$key] = $value;
                }
            } else {
                // we don't need these any more, and they could
                // contain a lot of data, so lets trash them.
                unset($this->updatedData[$key]);
                unset($this->originalData[$key]);
            }
        }

        return $changes_to_record;
    }

    /**
     * Check if this field should have a revision kept
     *
     * @param string $key
     *
     * @return bool
     */
    private function isRevisionable($key)
    {

        // If the field is explicitly revisionable, then return true.
        // If it's explicitly not revisionable, return false.
        // Otherwise, if neither condition is met, only return true if
        // we aren't specifying revisionable fields.
        if (isset($this->doKeep) && in_array($key, $this->doKeep)) {
            return true;
        }
        if (isset($this->dontKeep) && in_array($key, $this->dontKeep)) {
            return false;
        }

        return empty($this->doKeep);
    }

    /**
     * Check if soft deletes are currently enabled on this model
     *
     * @return bool
     */
    private function isSoftDelete()
    {
        // check flag variable used in laravel 4.2+
        if (isset($this->forceDeleting)) {
            return !$this->forceDeleting;
        }

        // otherwise, look for flag used in older versions
        if (isset($this->softDelete)) {
            return $this->softDelete;
        }

        return false;
    }

    /**
     * @return mixed
     */
    public function getRevisionFormattedFields()
    {
        return $this->revisionFormattedFields;
    }

    /**
     * @return mixed
     */
    public function getRevisionFormattedFieldNames()
    {
        return $this->revisionFormattedFieldNames;
    }

    /**
     * Identifiable Name
     * When displaying revision history, when a foreign key is updated
     * instead of displaying the ID, you can choose to display a string
     * of your choice, just override this method in your model
     * By default, it will fall back to the models ID.
     *
     * @return string an identifying name for the model
     */
    public function identifiableName()
    {
        return $this->getKey();
    }

    /**
     * Revision Unknown String
     * When displaying revision history, when a foreign key is updated
     * instead of displaying the ID, you can choose to display a string
     * of your choice, just override this method in your model
     * By default, it will fall back to the models ID.
     *
     * @return string an identifying name for the model
     */
    public function getRevisionNullString()
    {
        return isset($this->revisionNullString) ? $this->revisionNullString : 'nothing';
    }

    /**
     * No revision string
     * When displaying revision history, if the revisions value
     * cant be figured out, this is used instead.
     * It can be overridden.
     *
     * @return string an identifying name for the model
     */
    public function getRevisionUnknownString()
    {
        return isset($this->revisionUnknownString) ? $this->revisionUnknownString : 'unknown';
    }

    /**
     * Disable a revisionable field temporarily
     * Need to do the adding to array longhanded, as there's a
     * PHP bug https://bugs.php.net/bug.php?id=42030
     *
     * @param mixed $field
     *
     * @return void
     */
    public function disableRevisionField($field)
    {
        if (!isset($this->dontKeepRevisionOf)) {
            $this->dontKeepRevisionOf = array();
        }
        if (is_array($field)) {
            foreach ($field as $one_field) {
                $this->disableRevisionField($one_field);
            }
        } else {
            $donts = $this->dontKeepRevisionOf;
            $donts[] = $field;
            $this->dontKeepRevisionOf = $donts;
            unset($donts);
        }
    }

    /**
     * Sorts the keys of a JSON object
     *
     * Normalization performed by MySQL and
     * discards extra whitespace between keys, values, or elements
     * in the original JSON document.
     * To make lookups more efficient, it sorts the keys of a JSON object.
     *
     * @param mixed $attribute
     *
     * @return mixed
     */
    private function sortJsonKeys($attribute)
    {
        if(empty($attribute)) return $attribute;

        foreach ($attribute as $key=>$value) {
            if(is_array($value) || is_object($value)){
                $value = $this->sortJsonKeys($value);
            } else {
                continue;
            }

            ksort($value);
            $attribute[$key] = $value;
        }

        return $attribute;
    }
}
