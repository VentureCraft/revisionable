CHANGELOG
=========

1.1.0 (2013-08-07)
------------------

* Added support for namespaced models extending the revisionable model
* Added changelog, tried to backfill, some information will be missing... carry on

1.0.7 (2013-06-13)
------------------

* Added support for temporarily disabling a revisionable field with disableRevisionField
  See https://github.com/VentureCraft/revisionable/issues/15

1.0.6 (2013-04-17)
------------------

* Bug fix: Added checks to make sure $key and Auth::user() are present.
  See https://github.com/VentureCraft/revisionable/issues/13

1.0.4 (2013-04-16)
------------------

* Bug fix: Support for null or invalid foreign keys on revision items.
  See https://github.com/VentureCraft/revisionable/issues/11

1.0.3 (2013-04-09)
------------------

* Bug fix: Renamed migration file to follow the new laravel migration naming scheme
  See https://github.com/VentureCraft/revisionable/issues/10
