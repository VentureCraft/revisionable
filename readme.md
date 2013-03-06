# Revisionable

Revisionable is a laravel package that allows you to keep a revision history for your models without thinking, extending the handy [Ardent](https://github.com/laravelbook/ardent) package.

## Installation

Revisionable is installable via [composer](http://getcomposer.org/doc/00-intro.md), the details are on [packagist, here.](https://packagist.org/packages/venturecraft/revisionable)

Add the following to the `require` section of your projects composer.json file:
```
"venturecraft/revisionable": "dev-master",
```

## Effortless revision history

For any model that you want to keep a revision history for, include the revisionable namespace and extend revisionable instead of eloquent, e.g., 
````
use Venturecraft\Revisionable\Revisionable;

class Article extends Revisionable { }
````

For any models you don't wish to keep a revision history for, we recommend extending Ardent (which is also installed when revisionable is installed via composer), e.g., 

````
use laravelbook\Ardent

class Comment extends Ardent { }
````

Ardent gives you access to more control over the model, but only when you need it. [Check out its docs here](https://github.com/laravelbook/ardent)


### Load revision history

To load the revision history for a given model, simply call the `revisionHistory` method on that model, e.g., 

````
$article = Article::find($id);
$history = $article->revisionHistory;
````


## Contributing

Contributions are encouraged and welcome; to keep things organised, all bugs and requests should be
opened in the github issues tab for the main project, at [venturecraft/revisionable/issues](https://github.com/venturecraft/revisionable/issues)

All pull requests should be made to the develop branch, so they can be tested before being merged into the master branch.
