<?php



namespace vladdnepr\ycm\behaviors;

use yii\base\Behavior;
use yii\base\InvalidConfigException;
use yii\db\ActiveRecord;
use yii\validators\Validator;

/**
 * Class BaseBehavior
 * @property ActiveRecord $owner
 * @package vladdnepr\ycm\behavior
 */
abstract class BaseBehavior extends Behavior
{
    /**
     * @var ActiveRecord the owner of this behavior
     */
    public $owner;

    /**
     * @var array
     */
    public $rules = []; // added rules to $owner;

    /**
     * @var \yii\validators\Validator[]
     */
    protected $validators = []; // track references of appended validators

    /**
     * Check and get relation name if $name contain $postfix
     * @param $name
     * @param $postfix
     * @return null|string
     */
    protected function getAttributeNameWithoutPostfix($name, $postfix)
    {
        $result = null;
        $pos = strpos($name, $postfix);

        if ($pos !== null) {
            $result = substr($name, 0, $pos);
        }

        return $result;
    }

    /**
     * @inheritdoc
     * @param $owner ActiveRecord
     */
    public function attach($owner)
    {
        parent::attach($owner);
        $validators = $owner->getValidators();
        foreach ($this->rules as $rule) {
            if ($rule instanceof Validator) {
                $validators->append($rule);
                $this->validators[] = $rule; // keep a reference in behavior
            } elseif (is_array($rule) && isset($rule[0], $rule[1])) { // attributes, validator type
                $validator = Validator::createValidator($rule[1], $owner, (array) $rule[0], array_slice($rule, 2));
                $validators->append($validator);
                $this->validators[] = $validator; // keep a reference in behavior
            } else {
                throw new InvalidConfigException(
                    'Invalid validation rule: a rule must specify both attribute names and validator type.'
                );
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function detach()
    {
        $ownerValidators = $this->owner->getValidators();
        $cleanValidators = [];
        foreach ($ownerValidators as $validator) {
            if (!in_array($validator, $this->validators)) {
                $cleanValidators[] = $validator;
            }
        }
        $ownerValidators->exchangeArray($cleanValidators);
    }
}
