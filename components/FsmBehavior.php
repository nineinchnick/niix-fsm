<?php

class FsmBehavior extends CBehavior
{
    /**
     * @var string name of the attribute holding current state
     */
    public $attribute;
    /**
     * @var string name of the AR model used to load possible state transitions. If null, all transitions are allowed.
     */
    public $transitionsModelClass;
    /**
     * @var callable a callable used to check if current model's state can be change to the one provided as the first argument
     */
    public $isTransitionAllowedCallback;
    /**
     * @var callable a callable used to perform the transition; by default, it just changes the attribute value
     */
    public $transitionCallback;

    /**
     * @var mixed id of the target state
     */
    public function isTransitionAllowed($targetState)
    {
        return call_user_func($this->isTransitionAllowedCallback, $targetState);
    }

    /**
     * @var mixed id of the target state
     */
    public function performTransition($targetState, $reason=null)
    {
        if (is_callable($this->transitionCallback))
            return call_user_func($this->transitionCallback, $targetState, $reason);

		$this->owner->{$this->attribute} = $targetState;
        $attributes = array($this->attribute);

        /* $reason should be set to some attribute like notes
		if ($reason !== null) {
            $this->owner->{$this->reasonAttribute} = $reason;
            $attributes[] = $this->reasonAttribute;
		}
         */
        /* beforeSave should be called
		$this->owner->last_edited = date('Y-m-d H:i:s');
		$this->owner->last_editor_id = Yii::app()->user->getId();
        $attributes[] = 'last_edited';
        $attributes[] = 'last_editor_id';
         */
        /* a transition should be registered as a db entry
        $transition = new PerformedMODELChanges;
        $transition->setAttributes(array(
            'FOREIGN_KEY_id'=> $this->owner->primaryKey,
            'source_status_id'=> $this->owner->{$this->attribute},
            'target_status_id'  => $targetState,
            'user_id'       => Yii::app()->user->getId(),
            'performed_on'  => date('Y-m-d H:i:s'),
        ));
        $transition->save();
         */
		
        return $this->owner->saveAttributes($attributes);
    }
}
