# Translateable Behavior for Yii2

[![Latest Version](https://img.shields.io/github/tag/2amigos/yii2-translateable-behavior.svg?style=flat-square&label=release)](https://github.com/2amigos/yii2-translateable-behavior/tags)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Build Status](https://img.shields.io/travis/2amigos/yii2-translateable-behavior/master.svg?style=flat-square)](https://travis-ci.org/2amigos/yii2-translateable-behavior)
[![Coverage Status](https://img.shields.io/scrutinizer/coverage/g/2amigos/yii2-translateable-behavior.svg?style=flat-square)](https://scrutinizer-ci.com/g/2amigos/yii2-translateable-behavior/code-structure)
[![Quality Score](https://img.shields.io/scrutinizer/g/2amigos/yii2-translateable-behavior.svg?style=flat-square)](https://scrutinizer-ci.com/g/2amigos/yii2-translateable-behavior)
[![Total Downloads](https://img.shields.io/packagist/dt/2amigos/yii2-translateable-behavior.svg?style=flat-square)](https://packagist.org/packages/2amigos/yii2-translateable-behavior)

This behavior has been inspired by the great work of Mikehaertl's
[Translatable Behavior](https://github.com/mikehaertl/translatable) for Yii 1.*.

It eases the translation of ActiveRecord's attributes as it maps theme from a translation table into the main record. It
also automatically loads application language by default.

## Installation

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```bash
$ composer require 2amigos/yii2-translateable-behavior:~1.0
```

or add

```
"2amigos/yii2-translateable-behavior": "~1.0"
```

to the `require` section of your `composer.json` file.

## Usage

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

Example:

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

## Testing

```bash
$ ./vendor/bin/phpunit
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Credits

- [Antonio Ramirez](https://github.com/tonydspaniard)
- [Alexander Kochetov](https://github.com/creocoder)
- [All Contributors](https://github.com/2amigos/yii2-selectize-widget/graphs/contributors)

## License

The BSD License (BSD). Please see [License File](LICENSE.md) for more information.

<blockquote>
    <a href="http://www.2amigos.us"><img src="http://www.gravatar.com/avatar/55363394d72945ff7ed312556ec041e0.png"></a><br>
    <i>web development has never been so fun</i><br>
    <a href="http://www.2amigos.us">www.2amigos.us</a>
</blockquote>
