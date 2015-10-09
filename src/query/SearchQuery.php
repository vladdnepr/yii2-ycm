<?php

namespace vladdnepr\ycm\query;

use vladdnepr\ycm\utils\helpers\ModelHelper;
use yii\data\ActiveDataProvider;
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

    public static function getDataProvider(ActiveRecord $model, $attributes)
    {
        $sort = [];

        if (method_exists($model, 'gridViewSort')) {
            $sort = $model->gridViewSort();
        }

        $search = new static($model);

        $dataProvider = new ActiveDataProvider([
            'query' => $search->getQuery(),
            'sort' => $sort,
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);

        if (method_exists($model, 'search')) {
            $scenarios = $model->scenarios();
            if (isset($scenarios['ycm-search'])) {
                $model->setScenario('ycm-search');
            }

            $modelSearch = $model->search($attributes);

            if ($modelSearch instanceof ActiveDataProvider) {
                $dataProvider = $modelSearch;
            } elseif (is_array($modelSearch)) {
                // Load data to model
                $model->load($attributes);

                if ($model->validate()) {
                    // Iterate config
                    foreach ($modelSearch as $search_condition) {
                        // Check attribute name and search type exists
                        if (!isset($search_condition[0]) || !isset($search_condition[1])) {
                            throw new \Exception(
                                'Search row must contain in 0 offset attribute name, in 1 offset - search type'
                            );
                        }

                        // Check used type implemented
                        if (!method_exists($search, $search_condition[1])) {
                            throw new \Exception('Search type' . $search_condition[1] . ' not implemented');
                        }

                        $search->{$search_condition[1]}($search_condition[0]);
                    }
                }
            } else {
                throw new \BadMethodCallException(
                    'Search method must be return instance of ActiveDataProvider or config array'
                );
            }
        }

        return $dataProvider;
    }

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
