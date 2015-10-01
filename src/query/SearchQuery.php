<?php

namespace vladdnepr\ycm\query;

use vladdnepr\ycm\utils\helpers\ModelHelper;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

class SearchQuery
{
    const MYSQL_DATETIME_FORMAT = 'Y-m-d H:i:s';

    /**
     * @var ActiveQuery
     */
    protected $query;

    /**
     * @var ActiveRecord
     */
    protected $model;

    public function __construct(ActiveRecord $model)
    {
        $this->model = $model;
        $this->query = $model->find();
    }

    public function getQuery()
    {
        return $this->query;
    }

    public function getModel()
    {
        return $this->model;
    }

    public function dateTime($attribute)
    {
        $this->range(
            $this->model->tableName() . '.' . $attribute,
            $this->model->$attribute,
            $this->model->{$attribute . '_to'}
        );
    }

    public function date($attribute)
    {
        $this->range(
            $this->model->tableName() . '.' . $attribute,
            $this->model->$attribute ?(new \DateTime($this->model->$attribute))
                ->format(self::MYSQL_DATETIME_FORMAT) : null,
            $this->model->{$attribute . '_to'} ? (new \DateTime($this->model->{$attribute . '_to'}))
                ->modify('+1 day')->format(self::MYSQL_DATETIME_FORMAT) : null
        );
    }

    public function range($attribute, $value_from, $value_to)
    {
        $this->query->andFilterWhere(['>=', $attribute, $value_from])
            ->andFilterWhere(['<=', $attribute, $value_to]);
    }

    public function relation($relation_name)
    {
        /* @var ActiveQuery $relation */
        $relation = $this->model->getRelation($relation_name);
        $relationClass = $relation->modelClass;
        $relationField = $relationClass::tableName() . '.' .
            ($relation->multiple ? array_values($relation->link)[0] : array_keys($relation->link)[0]);

        $relationValue = $this->model->$relation_name;

        if ($relationValue instanceof ActiveRecord) {
            $relationValue = ModelHelper::getPkColumnValue($relationValue);
        }

        $this->query->joinWith($relation_name)
            ->andFilterWhere([$relationField => $relationValue]);
    }

    public function like($attribute)
    {
        $this->query->andFilterWhere(['like', $this->model->tableName() .'.' . $attribute, $this->model->$attribute]);
    }

    public function equal($attribute)
    {
        $this->query->andFilterWhere([$attribute => $this->model->$attribute]);
    }
}
