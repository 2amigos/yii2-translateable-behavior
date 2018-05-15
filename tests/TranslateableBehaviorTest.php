<?php

namespace tests;

use PHPUnit\Framework\TestCase;
use tests\models\ActiveRecord;
use tests\models\Post;
use tests\models\PostLang;
use yii\db\Connection;
use yii\db\Expression;

/**
 *
 *
 * @author Carsten Brandt <mail@cebe.cc>
 */
class TranslateableBehaviorTest extends TestCase
{
    public function setUp()
    {
        ActiveRecord::$db = new Connection([
            'dsn' => 'mysql:host=localhost;dbname=yiitest',
            'username' => 'yiitest',
            'password' => 'yiitest',
            'schemaCache' => false,
            'charset' => 'utf8',
        ]);
        Post::resetTable();

        new \yii\console\Application([
            'id' => 'test',
            'language' => 'en',
            'basePath' => __DIR__,
        ]);

        parent::setUp();
    }

    protected function populateData()
    {
        ActiveRecord::getDb()->createCommand()->insert(Post::tableName(), [
            'id' => 1,
            'created_at' => new Expression('NOW()'),
        ])->execute();
        ActiveRecord::getDb()->createCommand()->insert(PostLang::tableName(), [
            'post_id' => 1,
            'language' => 'en',
            'title' => 'Example',
            'description' => 'Example description',
        ])->execute();
        ActiveRecord::getDb()->createCommand()->insert(PostLang::tableName(), [
            'post_id' => 1,
            'language' => 'de',
            'title' => 'Beispiel',
            'description' => 'Beispiel Beschreibung',
        ])->execute();
    }

    public function testTranslation()
    {
        $this->populateData();

        $post = Post::find()->where(['id' => 1])->one();

        $this->assertEquals('Example', $post->title);
        $this->assertEquals('Example description', $post->description);

        $post->language = 'de';

        $this->assertEquals('Beispiel', $post->title);
        $this->assertEquals('Beispiel Beschreibung', $post->description);
    }

    public function testSaveTranslation()
    {
        $this->populateData();

        $post = Post::find()->where(['id' => 1])->one();

        $post->language = 'ru';
        $post->title = 'пример';
        $post->description = 'Примерное описание';

        $this->assertEquals('пример', $post->title);
        $this->assertEquals('Примерное описание', $post->description);

        $post->save(false);

        $this->assertEquals('пример', $post->title);
        $this->assertEquals('Примерное описание', $post->description);

        $post = Post::find()->where(['id' => 1])->one();
        $post->language = 'ru';

        $this->assertEquals('пример', $post->title);
        $this->assertEquals('Примерное описание', $post->description);
    }

    public function testSaveTranslationNewRecord()
    {
        $this->populateData();

        $post = new Post();

        $post->title = 'Post1';
        $post->description = 'Post1 Description';

        $this->assertEquals('Post1', $post->title);
        $this->assertEquals('Post1 Description', $post->description);

        $post->save(false);

        $post = Post::find()->where(['id' => $post->id])->one();

        $this->assertEquals('Post1', $post->title);
        $this->assertEquals('Post1 Description', $post->description);
    }

}
