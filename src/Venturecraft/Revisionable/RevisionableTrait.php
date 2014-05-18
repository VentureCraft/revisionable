<?php namespace Venturecraft\Revisionable;

/*
 * This file is part of the Revisionable package by Venture Craft
 *
 * (c) Venture Craft <http://www.venturecraft.com.au>
 *
 */

trait RevisionableTrait
{

    private $originalData;
    private $updatedData;
    private $updating;
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

        static::saving(function($model)
        {
            $model->preSave();
        });

        static::saved(function($model)
        {
            $model->postSave();
        });

        static::deleted(function($model)
        {
            $model->preSave();
            $model->postDelete();
        });

    }


    public function revisionHistory()
    {
        return $this->morphMany('\Venturecraft\Revisionable\Revision', 'revisionable');
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

        // check if the model already exists
        if ((!isset($this->revisionEnabled) || $this->revisionEnabled) && $this->updating) {
            // if it does, it means we're updating

            $changes_to_record = $this->changedRevisionableFields();

            $revisions = array();

            foreach ($changes_to_record as $key => $change) {

                $revisions[] = array(
                    'revisionable_type'     => get_class($this),
                    'revisionable_id'       => $this->getKey(),
                    'key'                   => $key,
                    'old_value'             => array_get($this->originalData, $key),
                    'new_value'             => $this->updatedData[$key],
                    'user_id'               => $this->getUserId(),
                    'created_at'            => new \DateTime(),
                    'updated_at'            => new \DateTime(),
                );

            }

            if (count($revisions) > 0) {
                $revision = new Revision;
                \DB::table($revision->getTable())->insert($revisions);
            }

        }

    }


    /**
     * If softdeletes are enabled, store the deleted time
     */
    public function postDelete()
    {
        if ((!isset($this->revisionEnabled) || $this->revisionEnabled)
            && $this->softDelete
            && $this->isRevisionable('deleted_at')) {
            $revisions[] = array(
                'revisionable_type' => get_class($this),
                'revisionable_id' => $this->getKey(),
                'key' => 'deleted_at',
                'old_value' => null,
                'new_value' => $this->deleted_at,
                'user_id' => $this->getUserId(),
                'created_at' => new \DateTime(),
                'updated_at' => new \DateTime(),
            );
            $revision = new \Venturecraft\Revisionable\Revision;
            \DB::table($revision->getTable())->insert($revisions);
        }
    }


    /**
     * Attempt to find the user id of the currently logged in user
     * Supports Sentry based authentication, as well as stock Auth
     **/
    private function getUserId()
    {

        try {
            if (class_exists('Sentry') && \Sentry::check()) {
                $user = \Sentry::getUser();
                return $user->id;
            } else if (\Auth::check()) {
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
                if(!isset($this->originalData[$key]) || $this->originalData[$key] != $this->updatedData[$key]) {
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
     * @param  string $key
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
        if(is_array($field)) {
            foreach ($field as $one_field) {
                $this->disableRevisionField($one_field);
            }
        }
        else {
            $donts = $this->dontKeepRevisionOf;
            $donts[] = $field;
            $this->dontKeepRevisionOf = $donts;
            unset($donts);
        }

    }
}
