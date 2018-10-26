<?php
/**
 * @copyright Copyright (c) 2013 2amigOS! Consulting Group LLC
 * @link http://2amigos.us
 * @license http://www.opensource.org/licenses/bsd-license.php New BSD License
 */

namespace dosamigos\translateable;

use Yii;
use yii\base\Behavior;
use yii\base\Event;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;

/**
 * TranslateBehavior Behavior. Allows to maintain translations of model.
 *
 * @property string $language the language to use for reading and storing translations.
 * @property string|array $fallbackLanguage the language or list of languages to use in case a translation is not available.
 * @property ActiveRecord $owner
 *
 * @author Antonio Ramirez <amigo.cobos@gmail.com>
 * @link http://www.ramirezcobos.com/
 * @link http://www.2amigos.us/
 * @package dosamigos\translate
 */
class TranslateableBehavior extends Behavior
{
    /**
     * @var string the name of the translations relation
     */
    public $relation = 'translations';

    /**
     * @var string the language field used in the related table. Determines the language to query | save.
     */
    public $languageField = 'language';

    /**
     * @var array the list of attributes to translate. You can add validation rules on the owner.
     */
    public $translationAttributes = [];

    /**
     * @var bool whether to skip saving translations if they are equal to the fallback.
     * If a model is saved in a different language with fields filled by the fallback translation
     * this translation will not be saved unless changes were made.
     * This helps to reduce duplicate entries in the database and allows save records even
     * if it has not been translated. Defaults to `false`, which means translations will always be saved.
     * @since 1.0.4
     */
    public $skipSavingDuplicateTranslation = false;

    /**
     * @var string the ActiveRecord event to perform deletion of related translation records if a record is deleted.
     * The default is `ActiveRecord::EVENT_AFTER_DELETE`.
     * You may set this to `false` to disable deletion and rely on DB foreign key cascade or implement your own method.
     * @since 1.0.5
     */
    public $deleteEvent = ActiveRecord::EVENT_AFTER_DELETE;

    const DELETE_ALL = 'all';
    const DELETE_LAST = 'last';

    /**
     * @var string this property allows to control whether an active record can be deleted when it has translation records attached.
     *
     * - `DELETE_ALL`: Allows the deletion of a record without restriction. All translations will be deleted too. (default)
     * - `DELETE_LAST`: Allows the deletion of a record only when it has a single translation attached.
     *   To delete a record, first all translations have to be removed until only one translation exists.
     *   This behavior can be useful in combination with permission management where permission restricts access to different
     *   languages of a record.
     *
     * This property will only be used when `$deleteEvent` is `ActiveRecord::EVENT_BEFORE_DELETE` as it needs to prevent
     * deletion of the record, which is only possible before deletion.
     * @since 1.0.5
     */
    public $restrictDeletion = self::DELETE_ALL;

    /**
     * @var ActiveRecord[] the models holding the translations.
     */
    private $_models = [];

    /**
     * @var string the language selected.
     */
    private $_language;

    /**
     * @var string the language selected.
     */
    private $_fallbackLanguage;


    /**
     * @inheritdoc
     */
    public function events()
    {
        $events = [
            ActiveRecord::EVENT_AFTER_FIND => 'afterFind',
            ActiveRecord::EVENT_AFTER_INSERT => 'afterInsert',
            ActiveRecord::EVENT_AFTER_UPDATE => 'afterUpdate',
        ];

        if ($this->deleteEvent) {
            $events[$this->deleteEvent] = 'afterDelete';
        }

        return $events;
    }

    /**
     * Make [[$translationAttributes]] writable
     */
    public function __set($name, $value)
    {
        if (in_array($name, $this->translationAttributes)) {
            if (is_array($value) and isset($value['translations'])) {
                foreach ($value['translations'] as $language => $translatedValue) {
                    $this->getTranslation($language)->$name = $translatedValue;
                }
            } else {
                $this->getTranslation()->$name = $value;
            }
        } else {
            parent::__set($name, $value);
        }
    }

    /**
     * Make [[$translationAttributes]] readable
     * @inheritdoc
     */
    public function __get($name)
    {
        if (!in_array($name, $this->translationAttributes) && !isset($this->_models[$name])) {
            return parent::__get($name);
        }

        if (isset($this->_models[$name])) {
            return $this->_models[$name];
        }

        return $this->getAttributeTranslation($name, $this->getLanguage())[0];
    }

    /**
     * Retrieve translation for an attribute.
     * @param string $attribute the attribute name.
     * @param string $language the desired translation language.
     * @return array first element is the translation, second element is the language.
     * Language may differ from `$language` when a fallback translation has been used.
     */
    private function getAttributeTranslation($attribute, $language)
    {
        $seen = [];
        do {
            $model = $this->getTranslation($language);
            $modelLanguage = $language;
            $fallbackLanguage = $this->getFallbackLanguage($language);
            $seen[$language] = true;
            if (isset($seen[$fallbackLanguage])) {
                // break infinite loop in fallback path
                return [$model->$attribute, $modelLanguage];
            }
            $language = $fallbackLanguage;
        } while($model->$attribute === null);
        return [$model->$attribute, $modelLanguage];
    }

    /**
     * Expose [[$translationAttributes]] writable
     * @inheritdoc
     */
    public function canSetProperty($name, $checkVars = true)
    {
        return in_array($name, $this->translationAttributes, true) ? true : parent::canSetProperty($name, $checkVars);
    }

    /**
     * Expose [[$translationAttributes]] readable
     * @inheritdoc
     */
    public function canGetProperty($name, $checkVars = true)
    {
        return in_array($name, $this->translationAttributes, true) ? true : parent::canGetProperty($name, $checkVars);
    }

    /**
     * @param \yii\base\Event $event
     */
    public function afterFind($event)
    {
        $this->populateTranslations();
        $this->getTranslation($this->getLanguage());
    }

    /**
     * @param \yii\base\Event $event
     */
    public function afterInsert($event)
    {
        $this->saveTranslation();
    }

    /**
     * @param \yii\base\Event $event
     */
    public function afterUpdate($event)
    {
        $this->saveTranslation();
    }

    /**
     * @param \yii\base\ModelEvent $event
     */
    public function afterDelete($event)
    {
        if ($this->deleteEvent === ActiveRecord::EVENT_BEFORE_DELETE && $this->restrictDeletion === self::DELETE_LAST) {
            // only allow deletion if this record has not more than one translation
            $count = count($this->owner->{$this->relation});
            if ($count > 1) {
                $event->isValid = false;
                $event->handled = true;
                return;
            }
        }
        foreach ($this->owner->{$this->relation} as $translation) {
            $translation->delete();
        }
    }

    /**
     * Sets current model's language
     *
     * @param $value
     */
    public function setLanguage($value)
    {
        $value = strtolower($value);
        if (!isset($this->_models[$value])) {
            $this->_models[$value] = $this->loadTranslation($value);
        }
        $this->_language = $value;
    }

    /**
     * Returns current models' language. If null, will return app's configured language.
     * @return string
     */
    public function getLanguage()
    {
        if ($this->_language === null) {
            $this->_language = strtolower(Yii::$app->language);
        }
        return $this->_language;
    }

    /**
     * Sets the model's fallback language.
     *
     * @param string|array|bool $value this can be a string, an array or boolean `false`.
     *
     * - An array represents a set of fallback languages where array keys are languages and array values
     *   are their corresponding fallback languages. If no fallback is defined for a language, the default
     *   fallback will be the first entry in the array.
     * - A string represents a single fallback language that applies to all languages.
     * - If `false` is specified, fallback languages are disabled.
     */
    public function setFallbackLanguage($value)
    {
        $this->_fallbackLanguage = $value;
    }

    /**
     * Returns current models' fallback language. If null, will return app's configured source language.
     * @return string
     */
    public function getFallbackLanguage($forLanguage = null)
    {
        if ($this->_fallbackLanguage === null) {
            $this->_fallbackLanguage = Yii::$app->sourceLanguage;
        }
        if ($forLanguage === null) {
            return $this->_fallbackLanguage;
        }
        if ($this->_fallbackLanguage === false) {
            return $forLanguage;
        }

        if (is_array($this->_fallbackLanguage)) {
            if (isset($this->_fallbackLanguage[$forLanguage])) {
                return $this->_fallbackLanguage[$forLanguage];
            }
            // check fallback de-DE -> de
            $fallbackLanguage = substr($forLanguage, 0, 2);
            if ($forLanguage !== $fallbackLanguage) {
                return $fallbackLanguage;
            }
            // when no fallback is available, use the first defined fallback
            return reset($this->_fallbackLanguage);
        }

        // check fallback de-DE -> de
        $fallbackLanguage = substr($forLanguage, 0, 2);
        if ($forLanguage !== $fallbackLanguage) {
            return $fallbackLanguage;
        }
        return $this->_fallbackLanguage;
    }

    /**
     * @return bool whether the current language has a native translation.
     * If `false` the property values use a fallback language.
     * This is always true for newly created records.
     */
    public function getIsFallbackTranslation()
    {
        if ($this->_fallbackLanguage === false) {
            return false;
        }

        $language = $this->getLanguage();
        if (!isset($this->_models[$language])) {
            $this->_models[$language] = $this->loadTranslation($language);
        }
        return $this->_models[$language]->isNewRecord;
    }

    /**
     * Saves current translation model
     * @return bool
     */
    public function saveTranslation()
    {
        $ret = true;

        foreach ($this->_models as $language => $model) {
            $dirty = $model->getDirtyAttributes();
            // we do not need to save anything, if nothing has changed or translation is equal to its fallback
            if (empty($dirty) || ($this->skipSavingDuplicateTranslation && $model->isNewRecord && $this->modelEqualsFallbackTranslation($model, $language))) {
                continue;
            }
            /** @var \yii\db\ActiveQuery $relation */
            $relation = $this->owner->getRelation($this->relation);
            $pks = $relation->link;

            foreach ($pks as $fk => $pk) {
                $model->$fk = $this->owner->$pk;
            }

            if (!$model->save()) {
                $ret = false;
            }
        }

        return $ret;
    }

    /**
     * Check whether translation model has relevant translation data.
     *
     * This will return false if any translation is set and different from
     * the fallback.
     *
     * This method is used to only store translations if they differ from the fallback.
     *
     * @param ActiveRecord $model
     * @param string $language
     * @return bool whether a translation model contains relevant translation data.
     */
    private function modelEqualsFallbackTranslation($model, $language)
    {
        $fallbackLanguage = $this->getFallbackLanguage($language);
        foreach($this->translationAttributes as $translationAttribute) {
            if (isset($model->$translationAttribute)) {
                list($translation, $transLanguage) = $this->getAttributeTranslation($translationAttribute, $fallbackLanguage);
                if ($transLanguage === $language || $model->$translationAttribute !== $translation) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Returns a related translation model
     *
     * @param string|null $language the language to return. If null, current sys language
     *
     * @return ActiveRecord
     */
    public function getTranslation($language = null)
    {
        if ($language === null) {
            $language = $this->getLanguage();
        }

        if (!isset($this->_models[$language])) {
            $this->_models[$language] = $this->loadTranslation($language);
        }

        return $this->_models[$language];
    }

    /**
     * Loads all specified languages. For example:
     *
     * ```
     * $model->loadTranslations("en-US");
     *
     * $model->loadTranslations(["en-US", "es-ES"]);
     *
     * ```
     *
     * @param string|array $languages
     */
    public function loadTranslations($languages)
    {
        $languages = (array)$languages;

        foreach ($languages as $language) {
            $this->getTranslation($language);
        }
    }

    /**
     * Loads a specific translation model
     *
     * @param string $language the language to return
     *
     * @return null|\yii\db\ActiveQuery|static
     */
    private function loadTranslation($language)
    {
        /** @var $translation ActiveRecord */
        $translation = null;
        /** @var \yii\db\ActiveQuery $relation */
        $relation = $this->owner->getRelation($this->relation);
        /** @var ActiveRecord $class */
        $class = $relation->modelClass;
        $oldAttributes = $this->owner->getOldAttributes();
        $searchFields = [$this->languageField => $language];

        foreach ($relation->link as $languageModelField => $mainModelField) {
            if (empty($oldAttributes)) {
                $searchFields[$languageModelField] = $this->owner->$mainModelField;
            } else {
                $searchFields[$languageModelField] = $oldAttributes[$mainModelField];
            }
        }

        $translation = $class::findOne($searchFields);

        if ($translation === null) {
            $translation = new $class;
            $translation->setAttributes($searchFields, false);
        }

        return $translation;
    }

    /**
     * Populates already loaded translations
     */
    private function populateTranslations()
    {
        //translations
        $aRelated = $this->owner->getRelatedRecords();
        if (isset($aRelated[$this->relation]) && $aRelated[$this->relation] != null) {
            if (is_array($aRelated[$this->relation])) {
                foreach ($aRelated[$this->relation] as $model) {
                    $this->_models[$model->getAttribute($this->languageField)] = $model;
                }
            } else {
                $model = $aRelated[$this->relation];
                $this->_models[$model->getAttribute($this->languageField)] = $model;
            }
        }
    }
} 
