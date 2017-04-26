<?php namespace Venturecraft\Revisionable;

use Illuminate\Database\Eloquent\Model as Eloquent;

/*
 * This file is part of the Revisionable package by Venture Craft
 *
 * (c) Venture Craft <http://www.venturecraft.com.au>
 *
 */

/**
 * Class Revisionable
 * @package Venturecraft\Revisionable
 */
class Revisionable extends Eloquent
{
    use CommonTrait;

    /**
     * Invoked before a model is saved. Return false to abort the operation.
     *
     * @return bool
     */

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
                    'revisionable_type'     => $this->getMorphClass(),
                    'revisionable_id'       => $this->getKey(),
                    'key'                   => $key,
                    'old_value'             => array_get($this->originalData, $key),
                    'new_value'             => $this->updatedData[$key],
                    'user_id'               => $this->getSystemUserId(),
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
                'revisionable_type' => $this->getMorphClass(),
                'revisionable_id' => $this->getKey(),
                'key' => self::CREATED_AT,
                'old_value' => null,
                'new_value' => $this->{self::CREATED_AT},
                'user_id' => $this->getSystemUserId(),
                'created_at' => new \DateTime(),
                'updated_at' => new \DateTime(),
            );

            $revision = new Revision;
            \DB::table($revision->getTable())->insert($revisions);
        }
    }

    /**
     * If softdeletes are enabled, store the deleted time
     */
    public function postDelete()
    {
        if ((!isset($this->revisionEnabled) || $this->revisionEnabled)
            && $this->isSoftDelete()
            && $this->isRevisionable($this->getDeletedAtColumn())) {
            $revisions[] = array(
                'revisionable_type' => $this->getMorphClass(),
                'revisionable_id' => $this->getKey(),
                'key' => $this->getDeletedAtColumn(),
                'old_value' => null,
                'new_value' => $this->{$this->getDeletedAtColumn()},
                'user_id' => $this->getSystemUserId(),
                'created_at' => new \DateTime(),
                'updated_at' => new \DateTime(),
            );
            $revision = new \Venturecraft\Revisionable\Revision;
            \DB::table($revision->getTable())->insert($revisions);
        }
    }
}
