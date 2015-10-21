<?php


namespace vladdnepr\ycm\behaviors;

class DatePickerBehavior extends BaseBehavior
{
    protected $dates_to = [];

    /**
     * @inheritdoc
     */
    public function attach($owner)
    {
        $table = $owner->getTableSchema();
        foreach ($table->getColumnNames() as $column_name) {
            switch ($table->getColumn($column_name)->type) {
                case "timestamp":
                case "date":
                case "datetime":
                    $this->rules[] = [[$column_name, $column_name . '_to'], 'safe', 'on' => 'ycm-search'];
                    break;
            }
        }

        parent::attach($owner);
    }


    public function canGetProperty($name, $checkVars = true)
    {
        return $this->getAttributeNameWithoutPostfix($name, '_to');
    }

    public function canSetProperty($name, $checkVars = true)
    {
        return $this->getAttributeNameWithoutPostfix($name, '_to');
    }

    public function __set($name, $value)
    {
        $this->dates_to[$name] = $value;
    }

    public function __get($name)
    {
        return isset($this->dates_to[$name]) ? $this->dates_to[$name] : null;
    }

    public function __unset($name)
    {
        unset($this->dates_to[$name]);
    }

    public function __isset($name)
    {
        return isset($this->dates_to[$name]);
    }
}
