<?php

namespace Venturecraft\Revisionable;

use DB;
use App;
use DateTime;
use Spira\Model\Collection\Collection;
use Illuminate\Support\Facades\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait RevisionableTrait
{
    /**
     * @var array
     */
    private $originalData = array();

    /**
     * @var array
     */
    private $syncData = array();

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
     * Register a deleting a child model event with the dispatcher.
     *
     * @param  \Closure|string  $callback
     * @param  int  $priority
     *
     * @return void
     */
    public static function deleteChild($callback, $priority = 0)
    {
        static::registerModelEvent('deleteChild', $callback, $priority);
    }

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
     * Create the event listeners for model events.
     *
     * @return  void
     */
    public static function bootRevisionableTrait()
    {
        static::deleteChild(function ($model, $childModel, $relation) {
            $model->postDeleteChild($childModel, $relation);

            return true;
        });

        static::saving(function ($model) {
            $model->preSave();
        });

        static::saved(function ($model) {
            $model->postSave();
        });

        static::deleted(function ($model) {

            $model->preSave();
            $model->postDelete();
        });
    }

    /**
     * Defines the polymorphic relationship
     *
     * @return mixed
     */
    public function revisionHistory()
    {
        return $this->morphMany('\Venturecraft\Revisionable\Revision', 'revisionable');
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
        return \Venturecraft\Revisionable\Revision::where('revisionable_type', get_called_class())
            ->orderBy('updated_at', $order)->limit($limit)->get();
    }

    /**
     * Define a one-to-many relationship that can track revisions.
     *
     * @param  string  $related
     * @param  string  $relation
     * @param  string  $foreignKey
     * @param  string  $localKey
     *
     * @return HasManyRevisionable
     */
    public function hasManyRevisionable($related, $relation, $foreignKey = null, $localKey = null)
    {
        $foreignKey = $foreignKey ?: $this->getForeignKey();

        $instance = new $related;

        $localKey = $localKey ?: $this->getKeyName();

        return new HasManyRevisionable($instance->newQuery(), $this, $instance->getTable().'.'.$foreignKey, $localKey, $relation);
    }

    /**
     * Define a many-to-many relationship that can track revisions.
     *
     * @param  string  $related
     * @param  string  $table
     * @param  string  $foreignKey
     * @param  string  $otherKey
     * @param  string  $relation
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function belongsToManyRevisionable($related, $table = null, $foreignKey = null, $otherKey = null, $relation = null)
    {
        // If no relationship name was passed, we will pull backtraces to get the
        // name of the calling function. We will use that function name as the
        // title of this relation since that is a great convention to apply.
        if (is_null($relation)) {
            $relation = $this->getBelongsToManyCaller();
        }

        // First, we'll need to determine the foreign key and "other key" for the
        // relationship. Once we have determined the keys we'll make the query
        // instances as well as the relationship instances we need for this.
        $foreignKey = $foreignKey ?: $this->getForeignKey();

        $instance = new $related;

        $otherKey = $otherKey ?: $instance->getForeignKey();

        // If no table name was provided, we can guess it by concatenating the two
        // models using underscores in alphabetical order. The two model names
        // are transformed to snake case from their default CamelCase also.
        if (is_null($table)) {
            $table = $this->joiningTable($related);
        }

        // Now we're ready to create a new query builder for the related model and
        // the relationship instances for the relation. The relations will set
        // appropriate query constraint and entirely manages the hydrations.
        $query = $instance->newQuery();

        return new BelongsToManyRevisionable($query, $this, $table, $foreignKey, $otherKey, $relation);
    }

    /**
     * Invoked before a save child operation is performed.
     *
     * @param  string $relation
     * @param  Model  $model
     *
     * @return void
     */
    public function preSaveChild($relation, Model $model)
    {
        if ($this->isRevisionEnabled()
            && $this->isRelationRevisionable($relation)
        ) {
            $id = $model->{$model->getKeyName()};
            $model = $model->find($id);

            $this->originalData = $model ? $model->toJson() : null;
        }
    }

    /**
     * Invoked after a save child operation is successfully performed.
     *
     * @param  string $relation
     * @param  Model  $model
     *
     * @return void
     */
    public function postSaveChild($relation, Model $model)
    {
        if ($this->isRevisionEnabled()
            && (!$this->isLimitReached() || $this->isRevisionCleanup())
        ) {
            $revision = $this->prepareRevision(
                $relation,
                $this->originalData,
                $model->toJson()
            );

            $this->cleanupRevisions();

            $this->dbInsert($revision);
        }
    }

    /**
     * Called after a child model is deleted.
     *
     * @param  string    $key
     * @param  BaseModel $model
     *
     * @return void
     */
    public function postDeleteChild(Model $model, $relation)
    {
        if ($this->isRevisionEnabled()) {
            $revision = $this->prepareRevision($relation, $model->toJson(), null);

            $this->cleanupRevisions();

            $this->dbInsert($revision);
        }
    }

    /**
     * Invoked before a model is synced.
     *
     * @param  string $key
     *
     * @return void
     */
    public function preSync($relation)
    {
        if ($this->isRevisionEnabled()
            && $this->isRelationRevisionable($relation)
        ) {
            // Get only the IDs from the relationship
            $ids = array_keys($this->$relation->modelKeys());

            // And store them under the relationship name
            $this->syncData = [$relation => $ids];
        }
    }

    /**
     * Called after a model is successfully synced.
     *
     * @param  string $key
     * @param  array  $ids
     *
     * @return void
     */
    public function postSync($key, array $ids)
    {
        if (($this->isRevisionEnabled())
            && (!$this->isLimitReached() || $this->isRevisionCleanup())
            && array_key_exists($key, $this->syncData)
        ) {
            $revision = $this->prepareRevision($key, json_encode(array_get($this->syncData, $key)), json_encode($ids));

            $this->cleanupRevisions();

            $this->dbInsert($revision);
        }
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
                if (gettype($val) == 'object' && !method_exists($val, '__toString')) {
                    unset($this->originalData[$key]);
                    unset($this->updatedData[$key]);
                    array_push($this->dontKeep, $key);
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
        if (isset($this->historyLimit) && $this->revisionHistory()->count() >= $this->historyLimit) {
            $LimitReached = true;
        } else {
            $LimitReached = false;
        }
        if (isset($this->revisionCleanup)) {
            $RevisionCleanup=$this->revisionCleanup;
        } else {
            $RevisionCleanup=false;
        }

        // check if the model already exists
        if (((!isset($this->revisionEnabled) || $this->revisionEnabled) && $this->updating) && (!$LimitReached || $RevisionCleanup)) {
            // if it does, it means we're updating

            $changes_to_record = $this->changedRevisionableFields();

            $revisions = array();

            foreach ($changes_to_record as $key => $change) {
                $revisions[] = array(
                    'revisionable_type' => get_class($this),
                    'revisionable_id' => $this->getKey(),
                    'key' => $key,
                    'old_value' => array_get($this->originalData, $key),
                    'new_value' => $this->updatedData[$key],
                    'user_id' => $this->getUserId(),
                    'created_at' => new \DateTime(),
                    'updated_at' => new \DateTime(),
                );
            }

            if (count($revisions) > 0) {
                if ($LimitReached && $RevisionCleanup) {
                    $toDelete = $this->revisionHistory()->orderBy('id', 'asc')->limit(count($revisions))->get();
                    foreach ($toDelete as $delete) {
                        $delete->delete();
                    }
                }
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
            && $this->isSoftDelete()
            && $this->isRevisionable('deleted_at')
        ) {
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
     * Insert revisions in database.
     *
     * We use the database query builder instead of Eloquent to make insert
     * queries spanning multiple records more effecient.
     *
     * @param  array $revisions
     *
     * @return void
     */
    protected function dbInsert(array $revisions)
    {
        $revision = new Revision;
        $table = $revision->getTable();

        DB::table($table)->insert($revisions);
    }

    /**
     * Attempt to find the user id of the currently logged in user.
     *
     * @return string|null
     */
    private function getUserId()
    {
        if ($user = Request::user()) {
            return $user->user_id;
        }

        return null;
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
     * Prepare a revision entry for DB insertion.
     *
     * @param  string $key
     * @param  mixed  $oldValue
     * @param  mixed  $newValue
     *
     * @return array
     */
    protected function prepareRevision($key, $oldValue, $newValue)
    {
        return [
            'revisionable_type' => get_class($this),
            'revisionable_id' => $this->getKey(),
            'key' => $key,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'user_id' => $this->getUserId(),
            'created_at' => new DateTime(),
            'updated_at' => new DateTime(),
        ];
    }

    /**
     * Remove old revisions when the limit is reached if limit is enabled.
     *
     * @param  integer $count
     *
     * @return void
     */
    protected function cleanupRevisions($count = 1)
    {
        if ($this->isLimitReached() && $this->isRevisionCleanup()) {
            $toDelete = $this->revisionHistory()
                ->orderBy('created_at', 'asc')
                ->limit($count)
                ->get();

            foreach ($toDelete as $delete) {
                $delete->delete();
            }
        }
    }

    /**
     * Determines if revisions are enabled.
     *
     * @return  boolean
     */
    protected function isRevisionEnabled()
    {
        return !isset($this->revisionEnabled) || $this->revisionEnabled;
    }

    /**
     * Determines if revision limit for model is reached.
     *
     * @return boolean
     */
    protected function isLimitReached()
    {
        return isset($this->historyLimit)
               && ($this->revisionHistory()->count() >= $this->historyLimit);
    }

    /**
     * Determines if old revisions shall be removed.
     *
     * @return boolean
     */
    protected function isRevisionCleanup()
    {
        if (isset($this->revisionCleanup)) {
            return $this->revisionCleanup;
        }

        return false;
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
     * Determines if the relationship is revisionable.
     *
     * @param  string $relationship
     *
     * @return boolean
     */
    protected function isRelationRevisionable($relation)
    {
        if (isset($this->keepRevisionOf)) {
            return in_array($relation, $this->keepRevisionOf);
        }

        if (isset($this->dontKeepRevisionOf)) {
            return !in_array($relation, $this->dontKeepRevisionOf);
        }

        return true;
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
     * When displaying revision history, when a foreigh key is updated
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
     * When displaying revision history, when a foreigh key is updated
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
}
