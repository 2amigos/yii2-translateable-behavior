TranslateableBehavior for Yii2
==============================

This behavior has been inspired by the great work of Mikehaertl's
[Translatable Behavior](https://github.com/mikehaertl/translatable) for Yii 1.*.

It eases the translation of ActiveRecord's attributes as it maps theme from a translation table into the main record. It
also automatically loads application language by default.

Sample of use:

```php
<?php

// create a record
$tour = new Tour;

$tour->title = "English title";

// save both the new Tour and a related translation record with the title
$tour->save();


// change language
$tour->language = 'fr';

$tour->title = "French title";

// save translation only
$tour->saveTranslation();

```

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require "2amigos/yii2-translateable-behavior"
```
or add

```json
"2amigos/yii2-translateable-behavior" : "~0.1"
```

to the require section of your application's `composer.json` file.

Usage
-----

### Preparation

First you need to move all the attributes that require to be translated into a separated table. For example, imagine we
wish to keep translations of title and description from our tour entity. Our schema should result on the following:

```
    +--------------+        +--------------+        +-------------------+
    |     tour     |        |     tour     |        |      tour_lang    |
    +--------------+        +--------------+        +-------------------+
    |           id |        |           id |        |                id |
    |        title |  --->  |   created_at |   +    |           tour_id |
    |  description |        |   updated_at |        |          language |
    |   created_at |        +--------------+        |             title |
    |   updated_at |                                |       description |
    +--------------+                                +-------------------+

```

After we have modified our schema, now we need to define a relation in our `ActiveRecord` object. The following example
assumes that we have already created a `TourLang` model (see the schema above):

```php
/**
* @return \yii\db\ActiveQuery
*/
public function getTranslations()
{
    return $this->hasMany(TourLang::className(), ['tour_id' => 'id']);
}
```

Finally, we need to attach our behavior.

```
use dosamigos\translateable\TranslateableBehavior;

\\ ...

public function behaviors()
{
    return [
        'trans' => [ // name it the way you want
            'class' => TranslateableBehavior::className(),
            // in case you named your relation differently, you can setup its relation name attribute
            // 'relation' => 'translations',
            // in case you named the language column differently on your translation schema
            // 'languageField' => 'language',
            'translationAttributes' => [
                'title', 'description'
            ]
        ],
    ];
}
```

### Basic Usage


```php
// create a record
$tour = new Tour;
$tour->title = "English title";

// save both the new Tour and a related translation record with the title
$tour->save();


// change language
$tour->language = 'fr';

$tour->title = "French title";

// save fr translation only
$tour->saveTranslation();
```

You may also set multiple translations directly:

```php
$tour = new Tour;
$tour->title = [
  'translations' => [
     'en' => "English title",
     'de' => "Deutscher Titel",
  ],
];

// save both the new Tour and a related translation record with the title
$tour->save();
```

### Fallback language

In case no translation is available for a specific language the behavior allows to specify a fallback translation to load instead.
By default the fallback will use the application source language. It can be configured by setting the `fallbackLanguage` property of the behavior.

Fallback language can be configured to be a single language or per language:

```php
// use english as fallback for all languages when no translation is available
'fallbackLanguage' => 'en',
// alternatively:
'fallbackLanguage' => [
    'de' => 'en', // fall back to English if German translation is missing
    'uk' => 'ru', // fall back to Russian if no Ukrainian translation is available
],
```

Additionally to the configurable fallback a fallback to non-localized language is applied automatically.
E.g. if no translation exists for `de-AT` (German in Austria) the translation will fall back to `de`.
The fallback goes further if `de` is not found using the `fallbackLanguage` configuration, so from the example
above it will then try `en`.

When the fallback is defined in array format and no fallback can be found for a language, the first fallback is returned.

You may disable the fallback mechanism by setting `fallbackLanguage` to `false`.

If you want to configure fallback languages globally, you can do so by configuring the `TranslateableBehavior` class
in Yii DI container:

```php
Yii::$container->set('dosamigos\translateable\TranslateableBehavior', ['fallbackLanguage' => 'de']);
```


### Deleting translations

By default, when an active record is deleted, translation records are deleted in the `afterSave` event.
However some database scenarios require different configuration, in case foreign keys restrict the deletion of records.

You may configure `'deleteEvent'` to be either `ActiveRecord::EVENT_BEFORE_DELETE` or `ActiveRecord::EVENT_AFTER_DELETE` to
control on which event the deletion of records should be performed.
You may set `'deleteEvent'` to `false` to disable deletion and rely on DB foreign key cascade or implement your own method.

When using the Translateablebehavior in an ActiveRecord you should enable [transactions()](https://www.yiiframework.com/doc/api/2.0/yii-db-activerecord#transactions()-detail)
for the delete operation.

> [![2amigOS!](http://www.gravatar.com/avatar/55363394d72945ff7ed312556ec041e0.png)](http://www.2amigos.us)  
<i>Web development has never been so fun!</i>  
[www.2amigos.us](http://www.2amigos.us)