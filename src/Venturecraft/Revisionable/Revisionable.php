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
    use RevisionableTrait;
}
