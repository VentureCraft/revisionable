<?php namespace Venturecraft\Revisionable;

/*
 * This file is part of the Revisionable package by Venture Craft
 *
 * (c) Venture Craft <http://www.venturecraft.com.au>
 *
 */

use Illuminate\Support\ServiceProvider;

class Revisionable extends \Eloquent
{

    private $originalData;
    private $updatedData;
    private $updating;

    protected $revisionEnabled = true;

    /**
     * A list of fields that should have
     * revisions kept for the model.
     */
    protected $keepRevisionOf = array();

    /**
     * A list of fields that should be ignored when keeping
     * revisions of the model.
     */
    protected $dontKeepRevisionOf = array();


    /**
     * Keeps the list of values that have been updated
     * @var array
     */
    protected $dirty = array();

    public function revisionHistory()
    {
        return $this->morphMany('\Venturecraft\Revisionable\Revision', 'revisionable');
    }


    /**
     * Create the event listeners for the saving and saved events
     * This lets us save revisions whenever a save is made, no matter the
     * http method.
     *
     */
    public function save(array $options = array())
    {

        $this->beforeSave();
        $saved = parent::save($options);
        if ($saved) {
            $this->afterSave();
        }

        return $saved;

    }


    /**
     * Invoked before a model is saved. Return false to abort the operation.
     *
     * @return bool
     */
    public function beforeSave()
    {

        if ($this->revisionEnabled) {
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

            $this->dirty = $this->getDirty();
            $this->updating = $this->exists;
        }

    }


    /**
     * Called after a model is successfully saved.
     *
     * @return void
     */
    public function afterSave()
    {
        // check if the model already exists
        if ($this->revisionEnabled AND $this->updating) {
            // if it does, it means we're updating

            $changes_to_record = $this->changedRevisionableFields();

            foreach ($changes_to_record as $key => $change) {

                $revision                    = new Revision();
                $revision->revisionable_type = get_class($this);
                $revision->revisionable_id   = $this->id;
                $revision->key               = $key;
                $revision->old_value         = $this->originalData[$key];
                $revision->new_value         = $this->updatedData[$key];
                $revision->user_id           = \Auth::user()->id;
                $revision->save();

            }

        }

    }


    /**
     * Get all of the changes that have been made, that are also supposed
     * to have their changes recorded
     * @return array fields with new data, that should be recorded
     */
    private function changedRevisionableFields()
    {

        $changes_to_record = array();
        foreach ($this->dirty as $key => $value) {
            if ($this->isRevisionable($key)) {
                $changes_to_record[$key] = $value;
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
        if (in_array($key, $this->keepRevisionOf)) return true;
        if (in_array($key, $this->dontKeepRevisionOf)) return false;

        return empty($this->keepRevisionOf);
    }


    public function getRevisionFormattedFields()
    {
        return $this->revisionFormattedFields;
    }

}
