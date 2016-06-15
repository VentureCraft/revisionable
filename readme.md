<img src="http://venturecraft.com.au/wp-content/uploads/2015/09/REVISIONABLE.png" style="width: 100%" alt="Revisionable" />

[![Laravel 4.x](https://img.shields.io/badge/Laravel-4.x-brightgreen.svg?style=flat-square)](http://laravel.com)
[![Laravel 5.2](https://img.shields.io/badge/Laravel-5.x-brightgreen.svg?style=flat-square)](http://laravel.com)
[![Latest Version](https://img.shields.io/github/release/venturecraft/revisionable.svg?style=flat-square)](https://packagist.org/packages/venturecraft/revisionable)
[![Downloads](https://img.shields.io/packagist/dt/venturecraft/revisionable.svg?style=flat-square)](https://packagist.org/packages/venturecraft/revisionable)
[![License](http://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](https://tldrlegal.com/license/mit-license)

Wouldn't it be nice to have a revision history for any model in your project, without having to do any work for it. By simply extending revisionable from your model, you can instantly have just that, and be able to display a history similar to this:

* Chris changed title from 'Something' to 'Something else'
* Chris changed category from 'News' to 'Breaking news'
* Matt changed category from 'Breaking news' to 'News'

So not only can you see a history of what happened, but who did what, so there's accountability.

Revisionable is a laravel package that allows you to keep a revision history for your models without thinking. For some background and info, [see this article](http://www.chrisduell.com/blog/development/keeping-revisions-of-your-laravel-model-data/)

## Working with 3rd party Auth / Eloquent extensions

Revisionable has support for Auth powered by
* [**Sentry by Cartalyst**](https://cartalyst.com/manual/sentry).
* [**Sentinel by Cartalyst**](https://cartalyst.com/manual/sentinel).

Revisionable can also now be used [as a trait](#the-new-trait-based-implementation), so your models can continue to extend Eloquent, or any other class that extends Eloquent (like [Ardent](https://github.com/laravelbook/ardent)).

## Installation

Revisionable is installable via [composer](http://getcomposer.org/doc/00-intro.md), the details are on [packagist, here.](https://packagist.org/packages/venturecraft/revisionable)

Add the following to the `require` section of your projects composer.json file:

```php
"venturecraft/revisionable": "1.*",
```

Run composer update to download the package

```
php composer.phar update
```

Finally, you'll also need to run migration on the package (Laravel 5.x)

```
php artisan migrate --path=vendor/venturecraft/revisionable/src/migrations
```

For Laravel 4.x users:
```
php artisan migrate --package=venturecraft/revisionable
```

> If you're going to be migrating up and down completely a lot (using `migrate:refresh`), one thing you can do instead is to copy the migration file from the package to your `app/database` folder, and change the classname from `CreateRevisionsTable` to something like `CreateRevisionTable` (without the 's', otherwise you'll get an error saying there's a duplicate class)

> `cp vendor/venturecraft/revisionable/src/migrations/2013_04_09_062329_create_revisions_table.php app/database/migrations/`

## Docs

* [Implementation](#intro)
* [More control](#control)
* [Format output](#formatoutput)
* [Load revision history](#loadhistory)
* [Display history](#display)
* [Contributing](#contributing)
* [Having troubles?](#faq)

<a name="intro"></a>
## Implementation

### The new, trait based implementation

For any model that you want to keep a revision history for, include the revisionable namespace and use the `RevisionableTrait` in your model, e.g.,
If you are using another bootable trait the be sure to override the boot method in your model;

```php
namespace MyApp\Models;

class Article extends Eloquent {
    use \Venturecraft\Revisionable\RevisionableTrait;

    public static function boot()
    {
        parent::boot();
    }
}
```

> Being a trait, revisionable can now be used with the standard Eloquent model, or any class that extends Eloquent, like [Ardent](https://github.com/laravelbook/ardent) for example.

> Traits require PHP >= 5.4

### Legacy class based implementation

> The new trait based approach is backwards compatible with existing installations of Revisionable. You can still use the below installation instructions, which essentially is extending a wrapper for the trait.

For any model that you want to keep a revision history for, include the revisionable namespace and extend revisionable instead of eloquent, e.g.,

```php
use Venturecraft\Revisionable\Revisionable;

namespace MyApp\Models;

class Article extends Revisionable { }
```

Note that it also works with namespaced models.

### Implementation notes

If needed, you can disable the revisioning by setting `$revisionEnabled` to false in your model. This can be handy if you want to temporarily disable revisioning, or if you want to create your own base model that extends revisionable, which all of your models extend, but you want to turn revisionable off for certain models.

```php
namespace MyApp\Models;

class Article extends Eloquent {
    use Venturecraft\Revisionable\RevisionableTrait;

    protected $revisionEnabled = false;
}
```

You can also disable revisioning after X many revisions have been made by setting `$historyLimit` to the number of revisions you want to keep before stopping revisions.

```php
namespace MyApp\Models;

class Article extends Eloquent {
    use Venturecraft\Revisionable\RevisionableTrait;

    protected $revisionEnabled = true;
    protected $historyLimit = 500; //Stop tracking revisions after 500 changes have been made.
}
```
In order to maintain a limit on history, but instead of stopping tracking revisions if you want to remove old revisions, you can accommodate that feature by setting `$revisionCleanup`.

```php
namespace MyApp\Models;

class Article extends Eloquent {
    use Venturecraft\Revisionable\RevisionableTrait;

    protected $revisionEnabled = true;
    protected $revisionCleanup = true; //Remove old revisions (works only when used with $historyLimit)
    protected $historyLimit = 500; //Maintain a maximum of 500 changes at any point of time, while cleaning up old revisions.
}
```

### Storing soft deletes

By default, if your model supports soft deletes, revisionable will store this and any restores as updates on the model.

You can choose to ignore deletes and restores by adding `deleted_at` to your `$dontKeepRevisionOf` array.

To better format the output for `deleted_at` entries, you can use the `isEmpty` formatter (see <a href="#format-output">Format output</a> for an example of this.)

<a name="control"></a>

### Storing creations
By default the creation of a new model is not stored as a revision.
Only subsequent changes to a model is stored.

If you want to store the creation as a revision you can override this behavior by setting `revisionCreationsEnabled` to `true` by adding the following to your model:
```php
protected $revisionCreationsEnabled = true;
```

## More control

No doubt, there'll be cases where you don't want to store a revision history only for certain fields of the model, this is supported in two different ways. In your model you can either specifiy which fields you explicitly want to track and all other fields are ignored:

```php
protected $keepRevisionOf = array(
    'title'
);
```

Or, you can specify which fields you explicitly don't want to track. All other fields will be tracked.

```php
protected $dontKeepRevisionOf = array(
    'category_id'
);
```

> The `$keepRevisionOf` setting takes precendence over `$dontKeepRevisionOf`

### Events

Every time a model revision is created an event is fired. You can listen for `revisionable.created`,  
`revisionable.saved` or `revisionable.deleted`.

```php
// app/Providers/EventServiceProviders.php
public function boot(DispatcherContract $events)
{
    parent::boot($events);

    $events->listen('revisionable.*', function($model, $revisions) {
        // Do something with the revisions or the changed model. 
        dd($model, $revisions);
    });
}

```

<a name="formatoutput"></a>
## Format output

> You can continue (and are encouraged to) use `eloquent accessors` in your model to set the
output of your values, see the [laravel docs for more information on accessors](http://laravel.com/docs/eloquent-mutators#accessors-and-mutators)
> The below documentation is therefor deprecated

In cases where you want to have control over the format of the output of the values, for example a boolean field, you can set them in the `$revisionFormattedFields` array in your model. e.g.,

```php
protected $revisionFormattedFields = array(
    'title'  => 'string:<strong>%s</strong>',
    'public' => 'boolean:No|Yes',
    'modified' => 'datetime:m/d/Y g:i A',
    'deleted_at' => 'isEmpty:Active|Deleted'
);
```

You can also override the field name output using the `$revisionFormattedFieldNames` array in your model, e.g.,

```php
protected $revisionFormattedFieldNames = array(
    'title' => 'Title',
    'small_name' => 'Nickname',
    'deleted_at' => 'Deleted At'
);
```

This comes into play when you output the revision field name using `$revision->fieldName()`

### String
To format a string, simply prefix the value with `string:` and be sure to include `%s` (this is where the actual value will appear in the formatted response), e.g.,

```
string:<strong>%s</strong>
```

### Boolean
Booleans by default will display as a 0 or a 1, which is pretty bland and won't mean much to the end user, so this formatter can be used to output something a bit nicer. Prefix the value with `boolean:` and then add your false and true options separated by a pipe, e.g.,

```
boolean:No|Yes
```

### DateTime
DateTime by default will display as Y-m-d H:i:s. Prefix the value with `datetime:` and then add your datetime format, e.g.,

```
datetime:m/d/Y g:i A
```

### Is Empty
This piggy backs off boolean, but instead of testing for a true or false value, it checks if the value is either null or an empty string.

```
isEmpty:No|Yes
```

This can also accept `%s` if you'd like to output the value, something like the following will display 'Nothing' if the value is empty, or the actual value if something exists:

```
isEmpty:Nothing|%s
```

<a name="loadhistory"></a>
## Load revision history

To load the revision history for a given model, simply call the `revisionHistory` method on that model, e.g.,

```php
$article = Article::find($id);
$history = $article->revisionHistory;
```

<a name="display"></a>
## Displaying history

For the most part, the revision history will hold enough information to directly output a change history, however in the cases where a foreign key is updated we need to be able to do some mapping and display something nicer than `plan_id changed from 3 to 1`.

To help with this, there's a few helper methods to display more insightful information, so you can display something like `Chris changed plan from bronze to gold`.

The above would be the result from this:

```php
@foreach($account->revisionHistory as $history )
    <li>{{ $history->userResponsible()->first_name }} changed {{ $history->fieldName() }} from {{ $history->oldValue() }} to {{ $history->newValue() }}</li>
@endforeach
```

If you have enabled revisions of creations as well you can display it like this:
```php
@foreach($resource->revisionHistory as $history)
  @if($history->key == 'created_at' && !$history->old_value)
    <li>{{ $history->userResponsible()->first_name }} created this resource at {{ $history->newValue() }}</li>
  @else
    <li>{{ $history->userResponsible()->first_name }} changed {{ $history->fieldName() }} from {{ $history->oldValue() }} to {{ $history->newValue() }}</li>
  @endif
@endforeach
```

### userResponsible()

Returns the User that was responsible for making the revision. A user model is returned, or null if there was no user recorded.

The user model that is loaded depends on what you have set in your `config/auth.php` file for the `model` variable.

### fieldName()

Returns the name of the field that was updated, if the field that was updated was a foreign key (at this stage, it simply looks to see if the field has the suffix of `_id`) then the text before `_id` is returned. e.g., if the field was `plan_id`, then `plan` would be returned.

> Remember from above, that you can override the output of a field name with the `$revisionFormattedFieldNames` array in your model.

### identifiableName()

This is used when the value (old or new) is the id of a foreign key relationship.

By default, it simply returns the ID of the model that was updated. It is up to you to override this method in your own models to return something meaningful. e.g.,

```php
use Venturecraft\Revisionable\Revisionable;

class Article extends Revisionable
{
    public function identifiableName()
    {
        return $this->title;
    }
}
```

### oldValue() and newValue()

Get the value of the model before or after the update. If it was a foreign key, identifiableName() is called.

### Unknown or invalid foreign keys as revisions
In cases where the old or new version of a value is a foreign key that no longer exists, or indeed was null, there are two variables that you can set in your model to control the output in these situations:

```php
protected $revisionNullString = 'nothing';
protected $revisionUnknownString = 'unknown';
```

### disableRevisionField()
Sometimes temporarily disabling a revisionable field can come in handy, if you want to be able to save an update however don't need to keep a record of the changes.

```php
$object->disableRevisionField('title'); // Disables title
```

or:

```php
$object->disableRevisionField(array('title', 'content')); // Disables title and content
```

<a name="contributing"></a>
## Contributing

Contributions are encouraged and welcome; to keep things organised, all bugs and requests should be
opened in the GitHub issues tab for the main project, at [venturecraft/revisionable/issues](https://github.com/venturecraft/revisionable/issues)

All pull requests should be made to the develop branch, so they can be tested before being merged into the master branch.

<a name="faq"></a>
## Having troubles?

If you're having troubles with using this package, odds on someone else has already had the same problem. Two places you can look for common answers to your problems are:

* [StackOverflow revisionable tag](http://stackoverflow.com/questions/tagged/revisionable?sort=newest&pageSize=50)
* [GitHub Issues](https://github.com/VentureCraft/revisionable/issues?page=1&state=closed)

> If you do prefer posting your questions to the public on StackOverflow, please use the 'revisionable' tag.
