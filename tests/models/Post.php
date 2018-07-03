<?php

namespace tests\models;

use dosamigos\translateable\TranslateableBehavior;
use yii\behaviors\TimestampBehavior;

/**
 *
 *
 * @property int $id
 * @property string $title
 * @property string $description
 * @property string $created_at
 * @property string $updated_at
 *
 * @author Carsten Brandt <mail@cebe.cc>
 */
class Post extends ActiveRecord
{
    public static function tableName()
    {
        return 'post';
    }

    public function behaviors()
    {
        return [
            'translate' => [
                'class' => TranslateableBehavior::className(),
                // in case you named your relation differently, you can setup its relation name attribute
                // 'relation' => 'translations',
                // in case you named the language column differently on your translation schema
                // 'languageField' => 'language',
                'translationAttributes' => [
                    'title', 'description'
                ]
            ],
            'timestamp' => TimestampBehavior::className(),
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getTranslations()
    {
        return $this->hasMany(PostLang::className(), ['post_id' => 'id']);
    }

    public static function resetTable()
    {
        $tablename = static::tableName();
        $translationTablename = PostLang::tableName();
        $db = static::getDb();

        $translationTable = $db->getSchema()->getTableSchema($translationTablename, true);
        if ($translationTable) {
            $db->createCommand()->dropTable($translationTablename)->execute();
        }
        $table = $db->getSchema()->getTableSchema($tablename, true);
        if ($table) {
            $db->createCommand()->dropTable($tablename)->execute();
        }

        $tableOptions = $db->driverName === 'mysql' ? 'CHARACTER SET utf8 COLLATE utf8_bin' : '';

        $db->createCommand()->createTable($tablename, [
            'id' => 'pk',
            'created_at' => 'integer NOT NULL',
            'updated_at' => 'integer',
        ], $tableOptions)->execute();
        $db->createCommand()->createTable($translationTablename, [
            'id' => 'pk',
            "{$tablename}_id" => 'integer NOT NULL',
            'language' => 'string(10) NOT NULL',
            'title' => 'string',
            'description' => 'string',
        ], $tableOptions)->execute();
        if ($db->driverName !== 'sqlite') {
            $db->createCommand()->addForeignKey(
                "{$translationTablename}_post_fk",
                $translationTablename,
                "{$tablename}_id",
                $tablename,
                'id',
                'CASCADE',
                'CASCADE'
            )->execute();
        }

    }
}
