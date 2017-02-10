<?php


namespace vladdnepr\ycm\behaviors;

use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;

class RelationsBehavior extends BaseBehavior
{
    /**
     * Saved multiple relations data
     * @var array
     */
    protected $relations_multiple = [];

    /**
     * Map relation name to DB fields.
     * If relation not multiple - value is DB field name
     * @var array
     */
    protected $relations_to_fields_map = [];

    /**
     * Model relations info (getRelation method)
     * @var array
     */
    protected $relations_info = [];

    /**
     * Data of nested relations. Used in setter and getter for search correct work
     * @var array
     */
    protected $relations_nested = [];

    /**
     * @inheritdoc
     */
    public function attach($owner)
    {
        // Extract data for auto rules and extract relations info
        $map = function ($config) use ($owner) {

            if (is_array($config)) {
                if (isset($config[0])) {
                    $this->rules[] = [[$config[0]], 'safe', 'on' => 'ycm-search'];
                    $this->rules[] = [[$config[0]], 'safe', 'on' => 'default'];
                }

                if (isset($config[1])
                    && $config[1] == 'relation'
                    && $relation = $owner->getRelation($config[0], false)
                ) {
                    $this->relations_info[$config[0]] = $relation;

                    if (!$relation->multiple) {
                        $this->relations_to_fields_map[$config[0]] = reset($relation->link);
                    }
                }
            }
        };

        if (method_exists($owner, 'attributeWidgets')) {
            array_map($map, $owner->attributeWidgets());
        }

        if (method_exists($owner, 'gridViewColumns')) {
            array_map($map, $owner->gridViewColumns());
        }

        if (method_exists($owner, 'search')) {
            $search = $owner->search([]);
            if (is_array($search)) {
                array_map($map, $search);
            }
        }

        parent::attach($owner);
    }

    public function canGetProperty($name, $checkVars = true)
    {
        return $this->canSetProperty($name, $checkVars);
    }

    public function canSetProperty($name, $checkVars = true)
    {
        // If name is name of relation or name contain dot (it nested relation)
        return $this->isNameOfRelation($name) || $this->isNameOfNestedRelation($name);
    }

    protected function isNameOfRelation($name)
    {
        return in_array($name, array_keys($this->relations_info));
    }

    protected function isNameOfNestedRelation($name)
    {
        return strpos($name, '.') !== false;
    }

    public function __set($name, $value)
    {
        if ($this->isNameOfRelation($name)) {
            if ($this->relations_info[$name]->multiple) {
                if (!is_array($value)) {
                    throw new \InvalidArgumentException('For multiple relation pass array of ids');
                }
                $this->relations_multiple[$name] = $value;
            } elseif ($value) {
                $this->bindModelById($name, $value);
            }
        }

        if ($this->isNameOfNestedRelation($name)) {
            $this->relations_nested[$name] = $value;
        }
    }

    public function __get($name)
    {
        $result = null;

        if ($this->isNameOfRelation($name)
            && ($relation = $this->owner->getRelation($name))
            && !$relation->multiple
        ) {
            $result = $this->owner->{$this->relations_to_fields_map[$name]};
        }

        if ($this->isNameOfNestedRelation($name)) {
            $result = ArrayHelper::getValue(
                $this->owner,
                $name,
                isset($this->relations_nested[$name]) ? $this->relations_nested[$name] : null
            );
        }

        return $result;
    }

    public function __unset($name)
    {

    }

    public function __isset($name)
    {
        return isset($this->owner->$name);
    }

    public function events()
    {
        return [
            ActiveRecord::EVENT_AFTER_UPDATE => [$this->owner, 'afterUpdate'],
            ActiveRecord::EVENT_AFTER_INSERT => [$this->owner, 'afterUpdate'],
        ];
    }

    public function afterUpdate()
    {
        // Save multiple relations
        foreach ($this->relations_multiple as $relation_name => $relation_data) {
            foreach ($this->owner->$relation_name as $relation_model) {
                /* @var ActiveRecord $relation_model */
                $relation_model_pk = $relation_model->getPrimaryKey();
                if (!in_array($relation_model_pk, $relation_data)) {
                    $this->unbindModelById($relation_name, $relation_model_pk);
                }
            }
            foreach ($relation_data as $id) {
                $this->bindModelById($relation_name, $id);
            }
        }
    }

    protected function bindModelById($relation_name, $id)
    {
        /** @var $model \yii\db\ActiveRecord */
        /** @var $relation_class \yii\db\ActiveRecord */
        $relation_class = $this->owner->getRelation($relation_name, false)->modelClass;
        $model = $relation_class::findOne($id);
        $this->owner->link($relation_name, $model);
    }

    protected function unbindModelById($relation_name, $id)
    {
        /** @var $model \yii\db\ActiveRecord */
        /** @var $relation_class \yii\db\ActiveRecord */
        $relation_class = $this->owner->getRelation($relation_name, false)->modelClass;
        $model = $relation_class::findOne($id);
        $this->owner->unlink($relation_name, $model);
    }
}
