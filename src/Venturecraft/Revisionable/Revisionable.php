<?php namespace Venturecraft\Revisionable;

use Illuminate\Database\Eloquent\Model as Eloquent;

/*
 * This file is part of the Revisionable package by Venture Craft
 *
 * (c) Venture Craft <http://www.venturecraft.com.au>
 *
 */

class Revisionable extends Eloquent
{

    private $originalData;
    private $updatedData;
    private $dontKeep = array();
    private $doKeep = array();

    /**
     * Keeps the list of values that have been updated
     * @var array
     */
    protected $dirtyData = array();

    /**
     * Create the event listeners for the saving and saved events
     * This lets us save revisions whenever a save is made, no matter the
     * http method.
     *
     */
    public static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            $model->preUpdate();
        });
        
        static::created(function ($model) {
            $model->postCreate();
            $model->postUpdate();
        });
        
        static::updating(function ($model) {
            $model->preUpdate();
        });

        static::updated(function ($model) {
            $model->postUpdate();
        });

        static::deleted(function ($model) {
            $model->preSave();
            $model->postDelete();
        });

    }

    public function revisionHistory()
    {
        return $this->morphMany('\Venturecraft\Revisionable\Revision', 'revisionable');
    }

/**
     * Invoked before a model is updated. Return false to abort the operation.
     *
     * @return bool
     */
    public function preUpdate()
    {

        if (!isset($this->revisionEnabled) || $this->revisionEnabled) {
            // if there's no revisionEnabled. Or if there is, if it's true

            $this->originalData = $this->original;
            $this->updatedData  = $this->attributes;

            // we can only safely compare basic items,
            // so for now we drop any object based items, like DateTime
            foreach ($this->updatedData as $key => $val) {
                if (gettype($val) == 'object') {
                    unset($this->originalData[$key]);
                    unset($this->updatedData[$key]);
                }
            }

            // the below is ugly, for sure, but it's required so we can save the standard model
            // then use the keep / dontkeep values for later, in the isRevisionable method
            $this->dontKeep = isset($this->dontKeepRevisionOf) ?
                $this->dontKeepRevisionOf + $this->dontKeep
                : $this->dontKeep;

            $this->doKeep = isset($this->keepRevisionOf) ?
                $this->keepRevisionOf + $this->doKeep
                : $this->doKeep;

            unset($this->attributes['dontKeepRevisionOf']);
            unset($this->attributes['keepRevisionOf']);

            $this->dirtyData = $this->getDirty();

        }

    }


    /**
     * Called after a model is successfully updated.
     *
     * @return void
     */
    public function postUpdate()
    {
        $changes_to_record = $this->changedRevisionableFields();

        $revisions = array();

        foreach ($changes_to_record as $key => $change) {
            $old_value = array_get($this->originalData, $key);
            $new_value = $this->updatedData[$key];
             
            if($old_value == "" && $new_value == "")
            {
                //Both old val and new val is empty, nothing has changed at all
                //Skip this iteration then
                continue;
            }
            else
            {
                /*
                 * Verify action
                 */
                // check if inserting
                if($old_value == "" && $new_value != "")
                {
                    $action = Revision::INSERT;
                }
                //check if updating
                if($old_value != "" && $new_value != "")
                {
                    $action = Revision::UPDATE;
                }
                //check if deleting
                if($old_value != "" && $new_value == "")
                {
                    $action = Revision::DELETE;
                }

                $revisions[] = array(
                    'revisionable_type'     => get_class($this),
                    'revisionable_id'       => $this->getKey(),
                    'key'                   => $key,
                    'old_value'             => $old_value,
                    'new_value'             => $new_value,
                    'action'                => $action,
                    'user_id'               => $this->getUserId(),
                    'created_at'            => new \DateTime(),
                    'updated_at'            => new \DateTime(),
                );
            }
        }
        
        if (!empty($revisions)) {
            $this->saveRevision($revisions);
        }
    }


    /**
     * If softdeletes are enabled, store the deleted time
     */
    public function postDelete()
    {
        if ((!isset($this->revisionEnabled) || $this->revisionEnabled)
            && $this->softDelete) {
            $revisions[] = array(
                'revisionable_type' => get_class($this),
                'revisionable_id' => $this->getKey(),
                'key' => 'deleted_at',
                'old_value' => null,
                'new_value' => null,
                'action'    => Revision::DELETE,
                'user_id' => $this->getUserId(),
                'created_at' => new \DateTime(),
                'updated_at' => new \DateTime(),
            );
            
            $this->saveRevision($revisions);
        }
    }
    
    /*
     * If saveCreateRevisions is enabled, save Creates of new model in the database
     */
    public function postCreate()
    {
        if((!isset($this->revisionEnabled) || $this->revisionEnabled) && (isset($this->saveCreateRevision) && $this->saveCreateRevision))
        {
            $revisions[] = array(
                'revisionable_type' => get_class($this),
                'revisionable_id' => $this->getKey(),
                'key' => null,
                'old_value' => null,
                'new_value' => null,
                'action'    => Revision::CREATE,
                'user_id' => $this->getUserId(),
                'created_at' => new \DateTime(),
                'updated_at' => new \DateTime(),
            );
            
            $this->saveRevision($revisions);            
        }
    }    

    /**
     * Attempt to find the user id of the currently logged in user
     * Supports Sentry based authentication, as well as stock Auth
     **/
    private function getUserId()
    {

        try {
            if (class_exists($class = '\Cartalyst\Sentry\Facades\Laravel\Sentry') && $class::check()) {
                $user = $class::getUser();

                return $user->id;
            } elseif (\Auth::check()) {
                return \Auth::user()->getAuthIdentifier();
            }
        } catch (\Exception $e) {
            return null;
        }

        return null;
    }

    /**
     * Get all of the changes that have been made, that are also supposed
     * to have their changes recorded
     * @return array fields with new data, that should be recorded
     */
    private function changedRevisionableFields()
    {

        $changes_to_record = array();
        foreach ($this->dirtyData as $key => $value) {
            // check that the field is revisionable, and double check
            // that it's actually new data in case dirty is, well, clean
            if ($this->isRevisionable($key) && !is_array($value)) {
                if (!isset($this->originalData[$key]) || $this->originalData[$key] != $this->updatedData[$key]) {
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
     * @return boolean
     */
    private function isRevisionable($key)
    {

        // If the field is explicitly revisionable, then return true.
        // If it's explicitly not revisionable, return false.
        // Otherwise, if neither condition is met, only return true if
        // we aren't specifying revisionable fields.
        if (isset($this->doKeep) && in_array($key, $this->doKeep)) return true;
        if (isset($this->dontKeep) && in_array($key, $this->dontKeep)) return false;
        return empty($this->doKeep);
    }

    public function getRevisionFormattedFields()
    {
        return $this->revisionFormattedFields;
    }

    public function getRevisionFormattedFieldNames()
    {
        return $this->revisionFormattedFieldNames;
    }
    
    public function getRevisionClassName()
    {
        return $this->revisionClassName;
    }    
    
    public function getRevisionPrimaryIdentifier()
    {
        return $this->revisionPrimaryIdentifier;
    }    

    /**
     * Identifiable Name
     * When displaying revision history, when a foreigh key is updated
     * instead of displaying the ID, you can choose to display a string
     * of your choice, just override this method in your model
     * By default, it will fall back to the models ID.
     * @return string an identifying name for the model
     */
    public function identifiableName()
    {
        return $this->getKey();
    }

    /**
     * Revision Unknown String
     * When displaying revision history, when a foreigh key is updated
     * instead of displaying the ID, you can choose to display a string
     * of your choice, just override this method in your model
     * By default, it will fall back to the models ID.
     * @return string an identifying name for the model
     */
    public function getRevisionNullString()
    {
        return isset($this->revisionNullString)?$this->revisionNullString:'nothing';
    }

    /**
     * No revision string
     * When displaying revision history, if the revisions value
     * cant be figured out, this is used instead.
     * It can be overridden.
     * @return string an identifying name for the model
     */
    public function getRevisionUnknownString()
    {
        return isset($this->revisionUnknownString)?$this->revisionUnknownString:'unknown';
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
    
    /*
     * Helper Function: saves revision data to revision table
     * 
     * @param array $revisions
     * 
     * @return bool;
     */
    private function saveRevision($revisions)
    {
        $revision = new \Venturecraft\Revisionable\Revision;
        return \DB::table($revision->getTable())->insert($revisions);        
    }    
}
