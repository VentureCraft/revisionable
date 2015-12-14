<?php

namespace Venturecraft\Revisionable;

use Illuminate\Support\ServiceProvider;

/*
 * This file is part of the Revisionable package by Venture Craft
 *
 * (c) Venture Craft <http://www.venturecraft.com.au>
 *
 */

/**
 * Class RevisionableServiceProvider
 *
 * @package Venturecraft\Revisionable
 */
class RevisionableServiceProvider extends ServiceProvider
{
  /**
   * Perform post-registration booting of services.
   *
   * @return void
   */
  public function boot()
  {
    $this->publishes(
      [
        __DIR__ . '/../../migrations' => database_path('migrations')
      ],
      'migrations'
    );
  }

  /**
   * Register the service provider.
   *
   * @return void
   */
  public function register()
  {
  }
}
