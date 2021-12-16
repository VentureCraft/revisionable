<?php

namespace Venturecraft\Revisionable;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Exception;

/**
 * Revision.
 *
 * Base model to allow for revision history on
 * any model that extends this model
 *
 * (c) Venture Craft <http://www.venturecraft.com.au>
 */
class Revision extends Eloquent
{
    /**
     * @var string
     */
    public $table = 'revisions';

    /**
     * @var array
     */
    protected $revisionFormattedFields = array();

    /**
     * @var array
     */
    protected $guarded = [];

    /**
     * @param array $attributes
     */
    public function __construct(array $attributes = array())
    {
        parent::__construct($attributes);
    }

    /**
     * Revisionable.
     *
     * Grab the revision history for the model that is calling
     *
     * @return array revision history
     */
    public function revisionable()
    {
        return $this->morphTo();
    }

    /**
     * Field Name
     *
     * Returns the field that was updated, in the case that it's a foreign key
     * denoted by a suffix of "_id", then "_id" is simply stripped
     *
     * @return string field
     */
    public function fieldName()
    {
        if ($formatted = $this->formatFieldName($this->key)) {
            return $formatted;
        } elseif (strpos($this->key, '_id')) {
            return str_replace('_id', '', $this->key);
        } else {
            return $this->key;
        }
    }

    /**
     * Format field name.
     *
     * Allow overrides for field names.
     *
     * @param $key
     *
     * @return bool
     */
    private function formatFieldName($key)
    {
        $related_model = $this->getActualClassNameForMorph($this->revisionable_type);
        $related_model = new $related_model;
        $revisionFormattedFieldNames = $related_model->getRevisionFormattedFieldNames();

        if (isset($revisionFormattedFieldNames[$key])) {
            return $revisionFormattedFieldNames[$key];
        }

        return false;
    }

    /**
     * Old Value.
     *
     * Grab the old value of the field, if it was a foreign key
     * attempt to get an identifying name for the model.
     *
     * @return string old value
     */
    public function oldValue()
    {
        return $this->getValue('old');
    }


    /**
     * New Value.
     *
     * Grab the new value of the field, if it was a foreign key
     * attempt to get an identifying name for the model.
     *
     * @return string old value
     */
    public function newValue()
    {
        return $this->getValue('new');
    }

    /**
     * User Responsible.
     *
     * @return User user responsible for the change
     */
    public function userResponsible()
    {
        if (empty($this->user_id)) { return false; }
        if (class_exists($class = '\Cartalyst\Sentry\Facades\Laravel\Sentry')
            || class_exists($class = '\Cartalyst\Sentinel\Laravel\Facades\Sentinel')
        ) {
            return $class::findUserById($this->user_id);
        } else {
            $user_model = app('config')->get('auth.model');

            if (empty($user_model)) {
                $user_model = app('config')->get('auth.providers.users.model');
                if (empty($user_model)) {
                    return false;
                }
            }
            if (!class_exists($user_model)) {
                return false;
            }
            return $user_model::find($this->user_id);
        }
    }

    /**
     * Returns the object we have the history of
     *
     * @return Object|false
     */
    public function historyOf()
    {
        if (class_exists($class = $this->revisionable_type)) {
            return $class::find($this->revisionable_id);
        }

        return false;
    }

    /*
     * Examples:
    array(
        'public' => 'boolean:Yes|No',
        'minimum'  => 'string:Min: %s'
    )
     */
    /**
     * Format the value according to the $revisionFormattedFields array.
     *
     * @param  $key
     * @param  $value
     *
     * @return string formatted value
     */
    public function format($key, $value)
    {
        $related_model = $this->getActualClassNameForMorph($this->revisionable_type);
        $related_model = new $related_model;
        $revisionFormattedFields = $related_model->getRevisionFormattedFields();

        if (isset($revisionFormattedFields[$key])) {
            return FieldFormatter::format($key, $value, $revisionFormattedFields);
        } else {
            return $value;
        }
    }

    public function prepareForDatabase($revisionData)
    {
        $this->fill($revisionData);

        $this->name = $this->getRevisionableName();
        $this->description = $this->getRevisionableDescription();

        $this->save();
    }

    public function getRevisionableName()
    {
        if (Auth::check()) {
            return Auth::user()->name;
        }

        return 'SYSTEM';
    }

    public function getRevisionableDescription()
    {
        if ($this->old_value) {
            return "Changed {$this->key} from {$this->getValue('old')} to {$this->getValue('new')}";
        }

        return "Initialised {$this->key} with {$this->getValue('new')}";
    }

    /**
     * Responsible for actually doing the grunt work for getting the
     * old or new value for the revision.
     *
     * @param string $which old or new
     *
     * @return string value
     */
    private function getValue($which = 'new')
    {
        $which_value = $which . '_value';

        // First find the main model that was updated
        $main_model = $this->revisionable_type;
        // Load it, WITH the related model
        if (class_exists($main_model)) {
            $main_model = new $main_model();

            if ($this->isRelated()) {
                $related_model = $this->getRelatedModel();

                // Now we can find out the namespace of of related model
                if (!method_exists($main_model, $related_model)) {
                    $related_model = Str::camel($related_model); // for cases like published_status_id
                    if (!method_exists($main_model, $related_model)) {
                        throw new Exception('Relation ' . $related_model . ' does not exist for ' . get_class($main_model));
                    }
                }
                $related_class = $main_model->$related_model()->getRelated();

                // Finally, now that we know the namespace of the related model
                // we can load it, to find the information we so desire
                $item = $related_class::find($this->$which_value);

                if (is_null($this->$which_value) || $this->$which_value == '') {
                    $item = new $related_class();

                    if (method_exists($item, 'getRevisionNullString')) {
                        return $item->getRevisionNullString();
                    } else {
                        return 'Nothing';
                    }
                }

                if (!$item) {
                    $item = new $related_class();
                    if (method_exists($item, 'getRevisionUnknownString')) {
                        return $this->format($this->key, $item->getRevisionUnknownString());
                    } else {
                        return 'Unknown';
                    }
                }

                // Check if model use RevisionableTrait
                if (method_exists($item, 'identifiableName')) {
                    // see if there's an available mutator
                    $mutator = 'get' . Str::studly($this->key) . 'Attribute';
                    if (method_exists($item, $mutator)) {
                        return $this->format($item->$mutator($this->key), $item->identifiableName());
                    }

                    return $this->format($this->key, $item->identifiableName());
                }
            }

            // if there was an issue
            // or, if it's a normal value

            $mutator = 'get' . Str::studly($this->key) . 'Attribute';
            if (method_exists($main_model, $mutator)) {
                return $this->format($this->key, $main_model->$mutator($this->$which_value));
            }
        }

        return $this->format($this->key, $this->$which_value);
    }

    /**
     * Return true if the key is for a related model.
     *
     * @return bool
     */
    private function isRelated()
    {
        $isRelated = false;
        $idSuffix  = '_id';
        $pos       = strrpos($this->key, $idSuffix);

        if (
            $pos !== false
            && strlen($this->key) - strlen($idSuffix) === $pos
        ) {
            $isRelated = true;
        }

        // Apex Overridden code
        $related_model = $this->getActualClassNameForMorph($this->revisionable_type);
        $related_model = new $related_model();
        if (is_array($related_model->revisionableRelationshipMap) && array_key_exists($this->key, $related_model->revisionableRelationshipMap)) {
            return true;
        }

        // Stupid function assumes any key with _id at the end is a foreign key, such as "cap_id" for example
        if (!method_exists($related_model, str_replace('_id', '', $this->key))) {
            return false;
        }

        // End Apex Overridden code

        return $isRelated;
    }

    /**
     * Return the name of the related model.
     *
     * @return string
     */
    private function getRelatedModel()
    {
        $idSuffix = '_id';

        // Apex Overridden code
        $related_model = $this->getActualClassNameForMorph($this->revisionable_type);
        $related_model = new $related_model();
        if (is_array($related_model->revisionableRelationshipMap) && array_key_exists($this->key, $related_model->revisionableRelationshipMap)) {
            return array_get($related_model->revisionableRelationshipMap, $this->key);
        }
        // End Apex Overridden code

        return substr($this->key, 0, strlen($this->key) - strlen($idSuffix));
    }
}
