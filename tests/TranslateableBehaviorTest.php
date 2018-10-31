<?php

namespace tests;

use dosamigos\translateable\TranslateableBehavior;
use PHPUnit\Framework\TestCase;
use tests\models\ActiveRecord;
use tests\models\Post;
use tests\models\PostLang;
use yii\db\Connection;
use yii\db\Expression;
use yii\db\Query;

/**
 *
 * @covers \dosamigos\translateable\TranslateableBehavior
 *
 * @author Carsten Brandt <mail@cebe.cc>
 */
class TranslateableBehaviorTest extends TestCase
{
    public function setUp()
    {
        ActiveRecord::$db = new Connection([
//            'dsn' => 'mysql:host=localhost;dbname=yiitest',
            'dsn' => 'sqlite::memory:',
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
            'created_at' => time(),
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

    public function testTranslationModelAccess()
    {
        $this->populateData();

        $post = Post::find()->where(['id' => 1])->one();
        $post->loadTranslations(['de', 'en']);
        $this->assertEquals('Example', $post->getBehavior('translate')->en->title);
        $this->assertEquals('Example description', $post->getBehavior('translate')->en->description);
        $this->assertEquals('Beispiel', $post->getBehavior('translate')->de->title);
        $this->assertEquals('Beispiel Beschreibung', $post->getBehavior('translate')->de->description);
    }

    public function testTranslationLocaleFallback()
    {
        $this->populateData();

        $post = Post::find()->where(['id' => 1])->one();

        $post->language = 'de';
        $this->assertEquals('Beispiel', $post->title);
        $this->assertEquals('Beispiel Beschreibung', $post->description);
        $this->assertFalse($post->isFallbackTranslation);

        $post->language = 'de-AT';
        $this->assertEquals('Beispiel', $post->title);
        $this->assertEquals('Beispiel Beschreibung', $post->description);
        $this->assertTrue($post->isFallbackTranslation);

        $post = new Post();
        $post->language = 'en';
        $post->title = 'January';
        $post->language = 'de';
        $post->title = 'Januar';
        $post->language = 'de-AT';
        $post->title = 'Jänner';
        $post->save();

        $post = Post::find()->where(['id' => $post->id])->one();

        $post->language = 'de';
        $this->assertEquals('Januar', $post->title);
        $this->assertFalse($post->isFallbackTranslation);

        $post->language = 'de-AT';
        $this->assertEquals('Jänner', $post->title);
        $this->assertFalse($post->isFallbackTranslation);

        $post->language = 'ru';
        $this->assertEquals('January', $post->title);
        $this->assertTrue($post->isFallbackTranslation);
        $post->language = 'ru-RU';
        $this->assertEquals('January', $post->title);
        $this->assertTrue($post->isFallbackTranslation);
    }

    public function testSaveTranslationIndirect()
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

    public function testSaveMultipleTranslations()
    {
        $this->populateData();

        $post = Post::find()->where(['id' => 1])->one();

        $post->title = [
            'translations' => [
                'en' => 'Example 1',
                'ru' => 'пример 1',
            ]
        ];
        $post->save(false);

        $post = Post::find()->where(['id' => 1])->one();
        $this->assertEquals('Example 1', $post->title);
        $post->language = 'ru';
        $this->assertEquals('пример 1', $post->title);
    }

    public function testSaveTranslationDirect()
    {
        $this->populateData();

        $post = Post::find()->where(['id' => 1])->one();

        $post->language = 'ru';
        $post->title = 'пример';
        $post->description = 'Примерное описание';

        $this->assertEquals('пример', $post->title);
        $this->assertEquals('Примерное описание', $post->description);

        $post->saveTranslation();

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

    public function testFallbackLanguage()
    {
        $behavior = new TranslateableBehavior();

        $this->assertEquals('en-US', $behavior->getFallbackLanguage());
        $this->assertEquals('en', $behavior->getFallbackLanguage('en-US'));

        $this->assertEquals('de', $behavior->getFallbackLanguage('de-DE'));
        $this->assertEquals('en-US', $behavior->getFallbackLanguage('de'));

        $behavior->setFallbackLanguage('ru');
        $this->assertEquals('ru', $behavior->getFallbackLanguage());
        $this->assertEquals('ru', $behavior->getFallbackLanguage('ru'));

        $behavior->setFallbackLanguage('ru-RU');
        $this->assertEquals('ru-RU', $behavior->getFallbackLanguage());
        $this->assertEquals('ru', $behavior->getFallbackLanguage('ru-RU'));

        $behavior->setFallbackLanguage($a = [
            'de-DE' => 'de-AT',
            'de' => 'en',
            'uk' => 'ru',
        ]);
        $this->assertEquals($a, $behavior->getFallbackLanguage());
        $this->assertEquals('de-AT', $behavior->getFallbackLanguage('de-DE'));
        $this->assertEquals('en', $behavior->getFallbackLanguage('de'));
        $this->assertEquals('uk', $behavior->getFallbackLanguage('uk-UA'));
        $this->assertEquals('ru', $behavior->getFallbackLanguage('uk'));
        $this->assertEquals('en', $behavior->getFallbackLanguage('en-GB'));
        
        // a non-specified language
        $this->assertEquals('de-AT', $behavior->getFallbackLanguage('ru'));

        $behavior->setFallbackLanguage(false);
        $this->assertFalse($behavior->getFallbackLanguage());
        $this->assertEquals('de-DE', $behavior->getFallbackLanguage('de-DE'));
        $this->assertEquals('de', $behavior->getFallbackLanguage('de'));
        $this->assertEquals('uk-UA', $behavior->getFallbackLanguage('uk-UA'));
        $this->assertEquals('uk', $behavior->getFallbackLanguage('uk'));
        $this->assertEquals('en-GB', $behavior->getFallbackLanguage('en-GB'));
    }

    public function testFallbackTranslation()
    {
        $post = new Post();
        $post->language = 'en';
        $post->title = 'January';
        $post->description = 'First month of the Year.';
        $post->language = 'de-AT';
        $post->title = 'Jänner';
        $post->language = 'de';
        $post->title = 'Januar';
        $post->language = 'ru';
        $post->title = 'январь';
        $post->language = 'uk';
        $post->description = 'Перший місяць року.';
        $post->save(false);

        $post = Post::find()->where(['id' => $post->id])->one();
        $post->fallbackLanguage = [
            'de' => 'en',
            'uk' => 'ru',
        ];
        $post->language = 'de-AT';
        $this->assertEquals('Jänner', $post->title);
        $this->assertEquals('First month of the Year.', $post->description);
        $post->language = 'de-CH';
        $this->assertEquals('Januar', $post->title);
        $this->assertEquals('First month of the Year.', $post->description);
        $post->language = 'de';
        $this->assertEquals('Januar', $post->title);
        $this->assertEquals('First month of the Year.', $post->description);
        $post->language = 'en';
        $this->assertEquals('January', $post->title);
        $this->assertEquals('First month of the Year.', $post->description);
        $post->language = 'fr';
        $this->assertEquals('January', $post->title);
        $this->assertEquals('First month of the Year.', $post->description);
        $post->language = 'uk-UA';
        $this->assertEquals('январь', $post->title);
        $this->assertEquals('Перший місяць року.', $post->description);
        $post->language = 'uk';
        $this->assertEquals('январь', $post->title);
        $this->assertEquals('Перший місяць року.', $post->description);

        $post = Post::find()->where(['id' => $post->id])->one();
        $post->fallbackLanguage = 'en';
        $post->language = 'de';
        $this->assertEquals('Januar', $post->title);
        $this->assertEquals('First month of the Year.', $post->description);
    }

    public function testDuplicateFallbackTranslation()
    {
        $post = new Post();
        $post->skipSavingDuplicateTranslation = false;
        $post->fallbackLanguage = [
            'de' => 'en',
        ];
        $post->language = 'en';
        $post->title = 'Januar'; // intentionally wrong
        $post->description = 'First month of the Year.';
        $post->language = 'de';
        $post->title = 'Januar';
        $post->description = 'Erster Monat im Jahr.';
        $post->save(false);

        // fix the english typo ;)
        $post = Post::find()->where(['id' => $post->id])->one();
        $post->skipSavingDuplicateTranslation = false;
        $post->fallbackLanguage = [
            'de' => 'en',
        ];
        $post->language = 'en';
        $post->title = 'January';
        $post->save(false);

        $post = Post::find()->where(['id' => $post->id])->one();
        $post->skipSavingDuplicateTranslation = false;
        $post->fallbackLanguage = [
            'de' => 'en',
        ];
        $post->language = 'de-AT';
        $this->assertEquals('Januar', $post->title);
        $this->assertEquals('Erster Monat im Jahr.', $post->description);
        $post->language = 'de-CH';
        $this->assertEquals('Januar', $post->title);
        $this->assertEquals('Erster Monat im Jahr.', $post->description);
        $post->language = 'de';
        $this->assertEquals('Januar', $post->title);
        $this->assertEquals('Erster Monat im Jahr.', $post->description);
        $post->language = 'en';
        $this->assertEquals('January', $post->title);
        $this->assertEquals('First month of the Year.', $post->description);
    }

    public function testSkipDuplicateFallbackTranslation()
    {
        $post = new Post();
        $post->skipSavingDuplicateTranslation = true;
        $post->fallbackLanguage = [
            'de' => 'en',
        ];
        $post->language = 'en';
        $post->title = 'Januar'; // intentionally wrong
        $post->description = 'First month of the Year.';
        $post->language = 'de';
        $post->title = 'Januar';
        $post->save(false);

        $post = Post::find()->where(['id' => $post->id])->one();
        $post->skipSavingDuplicateTranslation = true;
        $post->fallbackLanguage = [
            'de' => 'en',
        ];
        $post->language = 'en';
        $post->title = 'January';
        $post->save(false);

        $post = Post::find()->where(['id' => $post->id])->one();
        $post->skipSavingDuplicateTranslation = true;
        $post->fallbackLanguage = [
            'de' => 'en',
        ];
        $post->language = 'de-AT';
        $this->assertEquals('January', $post->title);
        $post->language = 'de-CH';
        $this->assertEquals('January', $post->title);
        $post->language = 'de';
        $this->assertEquals('January', $post->title);
        $post->language = 'en';
        $this->assertEquals('January', $post->title);

    }

    public function testSkipDuplicateFallbackTranslationEmptyEdgeCase()
    {

        $fallbackLanguages = [
            'de' => 'en',
            'ru' => 'en',
            'fr' => 'en',
        ];

        // When saving a model for the first time, the translation must
        // be saved even if all attributes have "empty" values ("",0,zero,....).
        $post = new Post();
        $post->skipSavingDuplicateTranslation = true;
        $post->fallbackLanguage = $fallbackLanguages;
        $post->language = 'en';
        $post->title = ''; // intentionally empty
        $post->description = 0;
        $post->save(false);

        $post = Post::find()->where(['id' => $post->id])->one();
        $this->assertNotNull($post);
        $postLangCount = PostLang::find()->where(['post_id' => $post->id])->count();
        $this->assertEquals(1, $postLangCount);
        $postLang = PostLang::find()->where(['post_id' => $post->id])->one();
        $this->assertEquals('en', $postLang->language);
        $post->language = 'en';
        $this->assertEquals('', $post->title);

        // if we don't change anything (typesave)
        // no additional translation should be saved
        $post = Post::find()->where(['id' => $post->id])->one();
        $post->skipSavingDuplicateTranslation = true;
        $post->fallbackLanguage = $fallbackLanguages;
        $post->language = 'en';
        $post->title = '';
        $post->description = 0;
        $post->language = 'de';
        $post->title = '';
        $post->description = 0;
        $post->save(false);
        $post = Post::find()->where(['id' => $post->id])->one();
        $this->assertNotNull($post);
        $postLangCount = PostLang::find()->where(['post_id' => $post->id])->count();
        $this->assertEquals(1, $postLangCount);
        $postLang = PostLang::find()->where(['post_id' => $post->id])->one();
        $this->assertEquals('en', $postLang->language);
        $post->language = 'en';
        $this->assertEquals('', $post->title);
        $post->language = 'de';
        $this->assertEquals('', $post->title);


        // if we change any type (empty string to int 0 in this case)
        // additional translation must be saved
        $post = Post::find()->where(['id' => $post->id])->one();
        $post->skipSavingDuplicateTranslation = true;
        $post->fallbackLanguage = $fallbackLanguages;
        $post->language = 'en';
        $post->title = '';
        $post->description = 0;
        $post->language = 'ru';
        $post->title = 0; // changed empty string to int 0
        $post->description = 0;
        $post->save(false);
        $post = Post::find()->where(['id' => $post->id])->one();
        $this->assertNotNull($post);
        $postLangCount = PostLang::find()->where(['post_id' => $post->id])->count();
        $this->assertEquals(2, $postLangCount);
        $post->language = 'en';
        $this->assertEquals('', $post->title);
        $post->language = 'de';
        $this->assertEquals('', $post->title);
        $post->language = 'ru';
        $this->assertEquals(0, $post->title);

        // if we change any values (title in this case)
        // additional translation must be saved
        $post = Post::find()->where(['id' => $post->id])->one();
        $this->assertNotNull($post);
        $postLangCount = PostLang::find()->where(['post_id' => $post->id])->count();
        $this->assertEquals(2, $postLangCount);
        $post = Post::find()->where(['id' => $post->id])->one();
        $post->skipSavingDuplicateTranslation = true;
        $post->fallbackLanguage = $fallbackLanguages;
        $post->language = 'en';
        $post->title = '';
        $post->description = 0;
        $post->language = 'fr';
        $post->title = 'fr title';
        $post->description = 0;
        $post->save(false);
        $post = Post::find()->where(['id' => $post->id])->one();
        $this->assertNotNull($post);
        $postLangCount = PostLang::find()->where(['post_id' => $post->id])->count();
        $this->assertEquals(3, $postLangCount);
        $post->language = 'en';
        $this->assertEquals('', $post->title);
        $post->language = 'de';
        $this->assertEquals('', $post->title);
        $post->language = 'ru';
        $this->assertEquals(0, $post->title);
        $post->language = 'fr';
        $this->assertEquals('fr title', $post->title);

    }

    public function testFallbackloop()
    {
        $this->populateData();

        $post = Post::find()->where(['id' => 1])->one();
        $post->fallbackLanguage = [
            'fr' => 'ru',
            'ru' => 'fr',
        ];
        $post->language = 'fr';
        $this->assertNull($post->title);
        $this->assertNull($post->description);

        $post->language = 'ru';
        $this->assertNull($post->title);
        $this->assertNull($post->description);
    }

    public function testNoFallbackTranslation()
    {
        $post = new Post();
        $post->language = 'en';
        $post->title = 'January';
        $post->description = 'January';
        $post->save(false);

        $post = Post::find()->where(['id' => $post->id])->one();
        $post->fallbackLanguage = [
            'uk' => 'ru',
        ];
        $post->language = 'de';
        $this->assertNull($post->title);
        $this->assertNull($post->description);
    }

    public function testNoFallbackTranslation2()
    {
        $post = new Post();
        $post->language = 'en';
        $post->title = 'January';
        $post->description = 'January';
        $post->save(false);

        $post = Post::find()->where(['id' => $post->id])->one();
        $post->fallbackLanguage = 'ru';
        $post->language = 'de';
        $this->assertNull($post->title);
        $this->assertNull($post->description);
    }

    public function testFallbackTranslationDisabled()
    {
        $post = new Post();
        $post->language = 'en';
        $post->title = 'January';
        $post->description = 'January';
        $post->save(false);

        $post = Post::find()->where(['id' => $post->id])->one();
        $post->fallbackLanguage = false;
        $post->language = 'en';
        $this->assertEquals('January', $post->title);
        $this->assertEquals('January', $post->description);
        $this->assertFalse($post->isFallbackTranslation);
        $post->language = 'en-GB';
        $this->assertNull($post->title);
        $this->assertNull($post->description);
        $this->assertFalse($post->isFallbackTranslation);
        $post->language = 'de';
        $this->assertNull($post->title);
        $this->assertNull($post->description);
        $this->assertFalse($post->isFallbackTranslation);
        $post->language = 'de-DE';
        $this->assertNull($post->title);
        $this->assertNull($post->description);
        $this->assertFalse($post->isFallbackTranslation);
    }

    public function testDelete()
    {
        $this->populateData();
        $post = Post::find()->where(['id' => 1])->one();
        $post->delete();
        $this->assertEquals(0, (new Query)->from('post')->count('*', ActiveRecord::getDb()));
        $this->assertEquals(0, (new Query)->from('post_lang')->count('*', ActiveRecord::getDb()));
    }

    public function testEagerLoading()
    {
        $this->populateData();

        $posts = Post::find()->where(['id' => 1])->with('translations')->all();
        $this->assertCount(1, $posts);
        $post = reset($posts);

        $this->assertEquals('Example', $post->title);
        $this->assertEquals('Example description', $post->description);

        $post->language = 'de';

        $this->assertEquals('Beispiel', $post->title);
        $this->assertEquals('Beispiel Beschreibung', $post->description);
    }

    // TODO test composite PK
}
