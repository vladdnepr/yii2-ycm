<?php

namespace vladdnepr\ycm\data;

use vladdnepr\ycm\Module;
use yii\data\ActiveDataProvider;

class SearchDataProvider extends ActiveDataProvider
{
    /**
     * @var Module
     */
    public $module;

    protected function prepareModels()
    {
        return array_map(
            [$this->module, 'attachBehaviorsToModel'],
            parent::prepareModels()
        );
    }
}
