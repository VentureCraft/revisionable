<?php namespace Venturecraft\Revisionable;
/*
 * This file is part of the Revisionable package by Venture Craft
 *
 * (c) Venture Craft <http://www.venturecraft.com.au>
 *
 */

interface RevisionableInterface
{

    /**
     * Create the event listeners for the saving and saved events
     */
    public static function boot()
    
    /**
     * Get revision history
     */
    public function revisionHistory()

    /**
     * Invoked before a model is saved. Return false to abort the operation.
     */
    public function preSave()
    
    /**
     * Called after a model is successfully saved.
     */
    public function postSave()
    
    /**
     * Attempt to find the user id of the currently logged in user
     **/
    private function getUserId()

    /**
     * Get all of the changes that have been made, that are also supposed
     * to have their changes recorded
     */
    private function changedRevisionableFields()

    /**
     * Check if this field should have a revision kept
     *
     * @param  string $key
     */
    private function isRevisionable($key)

    /**
     *
     */
    public function getRevisionFormattedFields()

    /**
     * Identifiable Name
     * When displaying revision history, when a foreigh key is updated
     * instead of displaying the ID, you can choose to display a string
     * of your choice, just override this method in your model
     * By default, it will fall back to the models ID.
     */
    public function identifiableName()
    
    /**
     * Revision null String
     * When displaying revision history, when a foreigh key is updated
     * instead of displaying the ID, you can choose to display a string
     * of your choice, just override this method in your model
     * By default, it will fall back to the models ID.
     */
    public function getRevisionNullString()

    /**
     * Revision unknown string
     * When displaying revision history, if the revisions value
     * cant be figured out, this is used instead.
     * It can be overridden.
     */
    public function getRevisionUnknownString()

    /**
     * Disable a revisionable field temporarily
     * 
     * @param mixed $field
     */
    public function disableRevisionField($field)
}
