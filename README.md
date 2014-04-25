TranslateableBehavior for Yii2
==============================

This behavior has been inspired by the great work of Mikehaertl's
[Translatable Behavior](https://github.com/mikehaertl/translatable) for Yii 1.*.

It eases the translation of ActiveRecord's attributes as it maps theme from a translation table into the main record. It
also automatically loads application language by default.

Sample of use:

```
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
php composer.phar require "2amigos/yii2-translateable-behavior" "*"
```
or add

```json
"2amigos/yii2-translateable-behavior" : "*"
```

to the require section of your application's `composer.json` file.

Usage
----------

First you need to move all the attributes that require to be translated into a separated table. For example, imagine we
wish to keep translations of title and description from our tour entity. Our schema should result on the following:

```
    +--------------+        +--------------+        +-------------------+
    |     tour     |        |     tour     |        |      tour_lang    |
    +--------------+        +--------------+        +-------------------+
    |           id |        |           id |        |                id |
    |        title |  --->  |   created_at |   +    |           tour_id |
    |  description |        |   updated_at |        |             title |
    |   updated_at |        |   updated_at |        |          language |
    |   created_at |        +--------------+        |       description |
    +--------------+                                +-------------------+

```

After we have modified our schema, now we need to define a relation in our `ActiveRecord` object. The following example
assumes that we have already created a `TourLang` model (see the schema above):

```
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


> [![2amigOS!](http://www.gravatar.com/avatar/55363394d72945ff7ed312556ec041e0.png)](http://www.2amigos.us)  
<i>Web development has never been so fun!</i>  
[www.2amigos.us](http://www.2amigos.us)