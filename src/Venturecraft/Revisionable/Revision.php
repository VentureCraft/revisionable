<?php namespace Venturecraft\Revisionable;

/**
 * Revision
 *
 * Base model to allow for revision history on
 * any model that extends this model
 *
 * (c) Venture Craft <http://www.venturecraft.com.au>
 */

class Revision extends \Eloquent {


	public $table = 'revisions';


    /**
     * Revisionable
     * Grab the revision history for the model that is calling
     * @return array revision history
     */
    public function revisionable()
    {
        return $this->morphTo();
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
        return $this->id;
    }


    /**
     * Item Field
     * Returns the field that was updated, in the case that it's a foreighn key
     * denoted by a suffic of "_id", then "_id" is simply stripped
     * @return string field
     */
    public function itemField()
    {
        if (strpos($this->key, '_id')) {
            return str_replace('_id', '', $this->key);
        } else {
            return $this->key;
        }
    }



    /**
     * Old Value
     * Grab the old value of the field, if it was a foreign key
     * attempt to get an identifying name for the model
     * @return string old value
     */
    public function oldValue()
    {
        try {
            if (strpos($this->key, '_id')) {
                $model = str_replace('_id', '', $this->key);
                $item = $model::find($this->old_value);
                return $item->identifiableName();
            }
        } catch (Exception $e) {
            // Just a failsafe, in the case the data setup isn't as expected
            // Nothing to do here.
        }

        // if there was an issue
        // or, if it's a normal value
        return $this->old_value;

    }



    /**
     * New Value
     * Grab the new value of the field, if it was a foreign key
     * attempt to get an identifying name for the model
     * @return string old value
     */
    public function newValue()
    {
        try {
            if (strpos($this->key, '_id')) {
                $model = str_replace('_id', '', $this->key);
                $item = $model::find($this->new_value);
                return $item->identifiableName();
            }
        } catch (Exception $e) {
            // Just a failsafe, in the case the data setup isn't as expected
            // Nothing to do here.
        }

        // if there was an issue
        // or, if it's a normal value
        return $this->new_value;

    }


    /**
     * User Responsible
     * @return User user responsible for the change
     */
    public function userResponsible()
    {
        return \User::find($this->user_id);
    }



}
