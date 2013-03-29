# Revisionable

Wouldn't it be nice to have a revision history for any model in your project, without having to do any work for it. By simply extending revisionable form your model, you can instantly have just that, and be able to display a history similar to this:

* Chris changed title from 'Something' to 'Something else'
* Chris changed category from 'News' to 'Breaking news'
* Matt changed category from 'Breaking news' to 'News'

So not only can you see a history of what happened, but who did what, so there's accountability.

Revisionable is a laravel package that allows you to keep a revision history for your models without thinking, extending the handy [Ardent](https://github.com/laravelbook/ardent) package. For some background and info, [see this article](http://www.chrisduell.com/blog/development/keeping-revisions-of-your-laravel-model-data/)
> Ardent gives you access to more control over the model, but only when you need it. [Check out its docs here](https://github.com/laravelbook/ardent)

## Installation

Revisionable is installable via [composer](http://getcomposer.org/doc/00-intro.md), the details are on [packagist, here.](https://packagist.org/packages/venturecraft/revisionable)

Add the following to the `require` section of your projects composer.json file:
```php
"venturecraft/revisionable": "dev-master",
```

Run composer update to download the package
```
php composer.phar update
```

Finally, you'll also need to run migration on the package
```
php artisan migrate --package=venturecraft/revisionable
```

## Docs

* [Effortless revision history](#intro)
* [Load revision history](#loadhistory)
* [Display history](#display)
* [Contributing](#contributing)

<a name="intro"></a>
## Effortless revision history

For any model that you want to keep a revision history for, include the revisionable namespace and extend revisionable instead of eloquent, e.g.,
```php
use Venturecraft\Revisionable\Revisionable;

class Article extends Revisionable { }
```

If needed, you can disable the revisioning by setting `$revisionEnabled` to false in your model. This can be handy if you want to temporarily disable revisioning, or if you want to create your own base model that extends revisionable, which all of your models extend, but you want to turn revisionable off for certain models.

```php
use Venturecraft\Revisionable\Revisionable;

class Article extends Revisionable
{
    protected $revisionEnabled = false;
}
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

### userResponsible()

Returns the User that was responsible for making the revision. A user model is returned, or null if there was no user recorded.

### fieldName()

Returns the name of the field that was updated, if the field that was updated was a foreign key (at this stage, it simply looks to see if the field has the suffix of `_id`) then the text before `_id` is returned. e.g., if the field was `plan_id`, then `plan` would be returned.

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

<a name="contributing"></a>
## Contributing

Contributions are encouraged and welcome; to keep things organised, all bugs and requests should be
opened in the github issues tab for the main project, at [venturecraft/revisionable/issues](https://github.com/venturecraft/revisionable/issues)

All pull requests should be made to the develop branch, so they can be tested before being merged into the master branch.
