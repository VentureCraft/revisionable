<?php namespace Venturecraft\Revisionable;

/**
 * Revision
 *
 * Base model to allow for revision history on
 * any model that extends this model
 *
 * (c) Venture Craft <http://www.venturecraft.com.au>
 */

class Revision extends \Eloquent
{

    public $table = 'revisions';

    protected $revisionFormattedFields = array();

    protected $revisionNullString = 'nothing';

    protected $revisionUnknownString = 'unknown';


    public function __construct(array $attributes = array())
    {
        parent::__construct($attributes);
    }


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
     * Field Name
     * Returns the field that was updated, in the case that it's a foreighn key
     * denoted by a suffic of "_id", then "_id" is simply stripped
     * @return string field
     */
    public function fieldName()
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

        if (is_null($this->old_value) OR $this->old_value == '') {
            return $this->revisionNullString;
        }

        try {

            if (strpos($this->key, '_id')) {

                $related_model = str_replace('_id', '', $this->key);

                // First find the main model that was updated
                $main_model = $this->revisionable_type;
                // Load it, WITH the related model
                if( !class_exists($main_model) ) {
                    throw new \Exception('Class ' . $main_model . ' does not exist');
                }

                $main_model = new $main_model;

                // Now we can find out the namespace of of related model
                if (! method_exists($main_model, $related_model)) {
                    throw new \Exception('Relation ' . $related_model . ' does not exist for ' . $main_model);
                }
                $related_class = $main_model->$related_model()->getRelated();

                // Finally, now that we know the namespace of the related model
                // we can load it, to find the information we so desire
                $item  = $related_class::find($this->old_value);

                if (!$item) {
                    return $this->format($this->key, $this->revisionUnknownString);
                }

                return $this->format($this->key, $item->identifiableName());
            }
        } catch (\Exception $e) {
            // Just a failsafe, in the case the data setup isn't as expected
            // Nothing to do here.
            \Log::info('Revisionable: ' . $e);
        }

        // if there was an issue
        // or, if it's a normal value
        return $this->format($this->key, $this->old_value);

    }


    /**
     * New Value
     * Grab the new value of the field, if it was a foreign key
     * attempt to get an identifying name for the model
     * @return string old value
     */
    public function newValue()
    {

        if (is_null($this->new_value) OR $this->new_value == '') {
            return $this->revisionNullString;
        }

        try {
            if (strpos($this->key, '_id')) {

                $related_model = str_replace('_id', '', $this->key);

                // First find the main model that was updated
                $main_model = $this->revisionable_type;
                // Load it, WITH the related model
                if( !class_exists($main_model) ) {
                    throw new \Exception('Class ' . $main_model . ' does not exist');
                }

                $main_model = new $main_model;

                // Now we can find out the namespace of of related model
                if (! method_exists($main_model, $related_model)) {
                    throw new \Exception('Relation ' . $related_model . ' does not exist for ' . $main_model);
                }
                $related_class = $main_model->$related_model()->getRelated();

                // Finally, now that we know the namespace of the related model
                // we can load it, to find the information we so desire
                $item  = $related_class::find($this->new_value);

                if (!$item) {
                    return $this->format($this->key, $this->revisionUnknownString);
                }

                return $this->format($this->key, $item->identifiableName());
            }
        }
        catch (\Exception $e) {
            // Just a failsafe, in the case the data setup isn't as expected
            // Nothing to do here.
            \Log::info('Revisionable: ' . $e);
        }

        // if there was an issue
        // or, if it's a normal value
        return $this->format($this->key, $this->new_value);

    }


    /**
     * User Responsible
     * @return User user responsible for the change
     */
    public function userResponsible()
    {
        return \User::find($this->user_id);
    }


    /*
     * Egzamples:
    array(
        'public' => 'boolean:Yes|No',
        'minimum'  => 'string:Min: %s'
    )
     */
    /**
     * Format the value according to the $revisionFormattedFields array
     *
     * @param  $key
     * @param  $value
     *
     * @return string formated value
     */
    public function format($key, $value)
    {
        $related_model                   = $this->revisionable_type;
        $related_model                   = new $related_model;
        $revisionFormattedFields = $related_model->getRevisionFormattedFields();

        if (isset($revisionFormattedFields[$key])) {
            return FieldFormatter::format($key, $value, $revisionFormattedFields);
        } else {
            return $value;
        }
    }


}
