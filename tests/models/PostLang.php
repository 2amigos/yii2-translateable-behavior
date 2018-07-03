<?php

namespace tests\models;

/**
 *
 * @property int $id
 * @property int $post_id
 * @property string $language
 * @property string $title
 * @property string $description
 *
 * @author Carsten Brandt <mail@cebe.cc>
 */
class PostLang extends ActiveRecord
{
    public static function tableName()
    {
        return 'post_lang';
    }
}
