<?php
namespace vladdnepr\ycm\behaviors;

use vladdnepr\ycm\helpers\ModelHelper;
use vladdnepr\ycm\Module;

class MultilangBehavior extends BaseBehavior
{
    /**
     * @inheritdoc
     */
    public function attach($owner)
    {
        if (property_exists($owner, Module::MODEL_MULTILANG_PROPERTY_NAME)) {
            foreach ((array)$owner->attributes_multilang as $attribute_multilang) {
                foreach (ModelHelper::getMultiLangAttributes($owner, $attribute_multilang) as $attribute) {
                    $this->rules[] = [[$attribute], 'safe', 'on' => 'default'];
                }
            }
        }

        parent::attach($owner);
    }
}
