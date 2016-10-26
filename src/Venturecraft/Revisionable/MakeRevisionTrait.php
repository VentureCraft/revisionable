<?php namespace Venturecraft\Revisionable;

/*
 * This file is part of the Revisionable package by Venture Craft
 *
 * (c) Venture Craft <http://www.venturecraft.com.au>
 *
 */

/**
 * Trait MakeRevisionTrait
 * @package Venturecraft\Revisionable
 */
trait MakeRevisionTrait
{
    /**
     * @param $data
     * @return array
     */
    protected function makeRevision($data)
    {
        $default = [
            'revisionable_type' => $this->getMorphClass(),
            'revisionable_id'   => $this->getKey(),
            'key'               => null,
            'old_value'         => null,
            'new_value'         => null,
            'user_id'           => $this->getSystemUserId(),
            'created_at'        => new \DateTime(),
            'updated_at'        => new \DateTime(),
        ];

        return array_merge($default, $data);
    }
}
