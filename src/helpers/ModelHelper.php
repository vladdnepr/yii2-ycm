<?php

namespace vladdnepr\ycm\helpers;

use vladdnepr\ycm\Module;
use yii\db\ActiveRecord;
use yii\db\Query;
use yii\helpers\ArrayHelper;

class ModelHelper
{
    protected static $label_column_default = ['title', 'name', 'id'];

    /**
     * @param ActiveRecord $model
     * @param $relation_name
     * @return null|\yii\db\ActiveQuery|\yii\db\ActiveQueryInterface
     */
    public static function getRelation(ActiveRecord $model, $relation_name)
    {
        $relation = null;

        foreach (explode('.', $relation_name) as $relation_subname) {
            $relation = $model->getRelation($relation_subname);
            $model = new $relation->modelClass;
        }

        return $relation;
    }

    public static function getMultiLangAttributes(ActiveRecord $model, $attribute)
    {
        $attributes = null;

        if (property_exists($model, Module::MODEL_MULTILANG_PROPERTY_NAME)
            && in_array($attribute, $model->{Module::MODEL_MULTILANG_PROPERTY_NAME})
        ) {
            $attributes = [];
            foreach ($model->attributes() as $model_attribute) {
                if (strpos($model_attribute, $attribute) === 0 // If start with original name
                    && strlen($model_attribute) - strlen($attribute) == 3 // and contain delimiter and 2-char lang code
                ) {
                    $attributes[] = $model_attribute;
                }
            }
        }

        return $attributes;
    }

    /**
     * Get an array of attribute choice values.
     * The variable or method name needs ​​to be: attributeChoices.
     *
     * @param \yii\db\ActiveRecord $model Model
     * @param string $attribute Model attribute
     * @return array
     */
    public static function getAttributeChoices($model, $attribute)
    {
        $data = [];
        $choicesName = (string) $attribute . 'Choices';
        if (method_exists($model, $choicesName) && is_array($model->$choicesName())) {
            $data = $model->$choicesName();
        } elseif (isset($model->$choicesName) && is_array($model->$choicesName)) {
            $data = $model->$choicesName;
        }
        return $data;
    }

    /**
     * Get an array of relation choice values.
     * The variable or method name needs ​​to be: attributeChoices.
     *
     * @param \yii\db\ActiveRecord $model Model
     * @param string $attribute Model attribute
     * @return array
     */
    public static function getRelationChoices($model, $attribute)
    {
        $data = self::getAttributeChoices($model, $attribute);

        if (!$data) {
            /* @var ActiveRecord $relation_model */
            $relation = $model->getRelation($attribute);
            $relation_model = new $relation->modelClass;

            $title_column_name = self::getLabelColumnName($relation_model);

            $data = ArrayHelper::map(
                $relation_model->find()->orderBy($title_column_name . ' ASC')->all(),
                self::getPkColumnName($relation_model),
                $title_column_name
            );

        }

        return $data;
    }

    public static function getLabelRelationValue(ActiveRecord $model, $relation)
    {
        $values = [];

        if (is_array($model->$relation)) {
            foreach ($model->$relation as $relation_model) {
                $values[] = self::getLabelColumnValue($relation_model);
            }
        } elseif ($relation_model = $model->$relation) {
            $values[] = self::getLabelColumnValue($relation_model);
        }

        // Bugfix in editable. if empty - it pass array(0) to HtmlHelper and fail
        return $values ? implode(', ', $values) : '(not set)';
    }

    public static function getEnumChoices(ActiveRecord $model, $attribute)
    {
        $values = [];

        if (($columnSchema = $model->getTableSchema()->getColumn($attribute)) && $columnSchema->enumValues) {
            $values = array_combine(
                array_values($columnSchema->enumValues),
                array_map('ucfirst', $columnSchema->enumValues)
            );
        }

        return $values;
    }

    public static function getBooleanChoices(ActiveRecord $model, $attribute)
    {
        return [
            0 => 'No',
            1 => 'Yes'
        ];
    }

    /**
     * Get label column name
     * @param ActiveRecord $model
     * @return mixed
     */
    public static function getLabelColumnName(ActiveRecord $model)
    {
        $available_names = array_intersect(static::$label_column_default, $model->getTableSchema()->getColumnNames());
        return reset($available_names);
    }

    /**
     * Get label column value
     * @param ActiveRecord $model
     * @return array|null
     */
    public static function getLabelColumnValue(ActiveRecord $model)
    {
        return $model->{self::getLabelColumnName($model)};
    }

    /**
     * Get PK column name
     * @param ActiveRecord $model
     * @return mixed
     */
    public static function getPkColumnName(ActiveRecord $model)
    {
        return $model->getTableSchema()->primaryKey[0];
    }

    /**
     * Get PK column value
     * @param ActiveRecord $model
     * @return mixed
     * @throws \yii\base\InvalidConfigException
     */
    public static function getPkColumnValue(ActiveRecord $model)
    {
        return $model->{self::getPkColumnName($model)};
    }

    /**
     * Find model by label value
     * @param ActiveRecord $model
     * @param string $label
     * @return null|static
     */
    public static function findByLabel(ActiveRecord $model, $label)
    {
        /* @var ActiveRecord $this */
        return $model->findOne([self::getLabelColumnName($model) => $label]);
    }

    /**
     * Find choices by label value
     * @param ActiveRecord $model
     * @param string $label
     * @param int $limit
     * @return array
     */
    public static function findChoicesByLabel(ActiveRecord $model, $label, $limit = 20)
    {
        /* @var ActiveRecord $this */
        $pk_column = self::getPkColumnName($model);
        $label_column = self::getLabelColumnName($model);

        $query = new Query();
        $query->select($pk_column . ' as id, ' . $label_column .' AS text')
            ->from($model->tableName())
            ->where($label_column . ' LIKE "%' . $label .'%"')
            ->limit($limit);

        $command = $query->createCommand();

        return array_values($command->queryAll());
    }
}
