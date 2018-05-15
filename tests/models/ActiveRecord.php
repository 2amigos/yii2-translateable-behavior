<?php

namespace tests\models;

/**
 *
 *
 * @author Carsten Brandt <mail@cebe.cc>
 */
class ActiveRecord extends \yii\db\ActiveRecord
{
    public static $db;

    public static function getDb()
    {
        return static::$db;
    }
}
