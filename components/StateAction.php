<?php
/**
 * StateAction displays a list of possible state transitions and raises an AR model event.
 *
 * It requires to be used by a NetController class.
 *
 * Installation steps:
 * * optionally, call the fsm console command to generate migrations, run them and create two models
 * * implement the IStateful interface in the main model
 * * include a new 'transition' scenario in validation rules, most often it would make a 'notes' attribute safe or even required
 * * optionally, add an inline validator that would depend on the source state
 * * copy the views into the controller's viewPath
 * * call StateAction::getContextMenuItem when building the context menu in CRUD controller
 *
 * @author Jan Was <jwas@nets.com.pl>
 */
class StateAction extends CAction
{
    /**
     * @var string Prefix of the auth item used to check access. Controller's $authModelClass is appended to it.
     */
    public $stateAuthItemTemplate = '{modelClass}.update';
    /**
     * @var string Auth item used to check access to update the main model. If null, the update button won't be available.
     */
    public $updateAuthItemTemplate;
    /**
     * @var callable a closure to check if current user is a superuser and authorization should be skipped
     */
    public $isAdminCallback;

	/**
	 * Runs the action.
	 */
	public function run($id, $targetState = null, $confirmed = false)
	{
        $model = $this->controller->loadModel($id, $this->controller->modelClass);
        $model->scenario = IStateful::SCENARIO;
        $authItem = strtr($this->stateAuthItemTemplate, array('{modelClass}'=>$this->controller->authModelClass));
        if ($this->controller->checkAccessInActions && !Yii::app()->user->checkAccess($authItem, array('model'=>$model))) {
            throw new CHttpException(403,Yii::t('app','You are not authorized to perform this action on this object.'));
        }

        if (!$model instanceof IStateful) {
            throw new CHttpException(500,Yii::t('app', 'Model {model} needs to implement the IStateful interface.', array('{model}'=>$this->controller->modelClass)));
        }
        $stateAttribute = $model->stateAttributeName;
        $stateChanges = $model->getTransitionsGroupedBySource();


        $uiType = $model->uiType($stateAttribute);
        $sourceState = $model->$stateAttribute;
        if (!isset($stateChanges[$sourceState])) {
            $stateChanges[$sourceState] = array('state' => null, 'targets' => array());
        }

        $this->controller->initForm($model);

        if ($targetState === null) {
            // display all possible state transitions to select from
			$this->controller->render('fsm_state', array(
				'model'         => $model,
                'targetState'   => null,
				'states'        => $this->prepareStates($model),
			));
            Yii::app()->end();
		} else if ((!is_callable($this->isAdminCallback) || !call_user_func($this->isAdminCallback)) && !isset($stateChanges[$sourceState]['targets'][$targetState])) {
			$sourceLabel = Yii::app()->format->format($sourceState, $model->uiType($stateAttribute));
			$targetLabel = Yii::app()->format->format($targetState, $model->uiType($stateAttribute));
			throw new CHttpException(400, Yii::t('app', 'Changing application status from {from} to {to} is not allowed.', array('{from}'=>$sourceLabel,'{to}'=>$targetLabel)));
		} else if (isset($stateChanges[$sourceState]['state']->auth_item_name) && !Yii::app()->user->checkAccess($stateChanges[$sourceState]['state']->auth_item_name, array('model'=>$model))) {
			$sourceLabel = Yii::app()->format->format($sourceState, $model->uiType($stateAttribute));
			$targetLabel = Yii::app()->format->format($targetState, $model->uiType($stateAttribute));
			throw new CHttpException(400, Yii::t('app', 'You don\'t have necessary permissions to move the application from {from} to {to}.', array('{from}'=>$sourceLabel, '{to}'=>$targetLabel)));
		}

        $model->setTransitionRules($targetState);

		if ($targetState === $sourceState) {
            Yii::app()->user->setFlash('error', Yii::t('app', 'Status has already been changed').', '.CHtml::link(Yii::t('app','return to'), $this->createUrl('view', array('id'=>$model->id))));
        } elseif ($confirmed && $model->isTransitionAllowed($targetState)) {
            $oldAttributes = $model->getAttributes();
            $data = $this->controller->processForm($model);
            // explicitly assign the new state value to avoid forcing the state attribute to be safe
            $model->{$model->getStateAttributeName()} = $targetState;

            if ($model->performTransition($oldAttributes)) {
                Yii::app()->user->setFlash('success', $stateChanges[$sourceState]['targets'][$targetState]->post_label);
                $this->controller->redirect(array('view', 'id'=>$model->id));
                Yii::app()->end();
            }
            Yii::app()->user->setFlash('error', Yii::t('app', 'Failed to save changes.'));
        }

        $this->controller->render('fsm_confirm', array(
            'model'         => $model,
            'sourceState'   => $sourceState,
            'targetState'   => $targetState,
            'transition'    => $stateChanges[$sourceState]['targets'][$targetState],
            'format'        => $uiType,
        ));
	}

	/**
	 * Builds an array containing all possible status changes and result of validating every transition.
	 * @params mixed $model
	 * @return array contains in order: (array)statuses, (boolean)valid
	 */
    public function prepareStates($model)
    {
		$checkedAccess = array();
        if ($this->updateAuthItemTemplate !== null) {
            $authItem = strtr($this->updateAuthItemTemplate, array('{modelClass}'=>$this->controller->authModelClass));
            $checkedAccess[$authItem] = Yii::app()->user->checkAccess($authItem, array('model'=>$model));
        }
		$result = array();
        if ($this->updateAuthItemTemplate !== null) {
            $authItem = strtr($this->updateAuthItemTemplate, array('{modelClass}'=>$this->controller->authModelClass));
            $result[] = array(
				'label' => Yii::t('app', 'Update item'),
				'icon' => 'pencil',
				'url' => $this->controller->createUrl('update', array('id' => $model->getPrimaryKey())),
				'enabled' => $checkedAccess[$authItem],
				'class' => 'btn btn-success',
			);
        }
		$valid = true;
        $attribute = $model->stateAttributeName;
        $sourceState = $model->$attribute;
        foreach($model->getTransitionsGroupedByTarget() as $targetState => $target) {
            $state = $target['state'];
            $sources = $target['sources'];

			if (!isset($sources[$sourceState])) continue;

			$enabled = null;
            $sourceStateObject = $sources[$sourceState];
			//foreach($sources[$sourceState] as $sourceStateObject) {
                $authItem = $sourceStateObject->auth_item_name;
				if (isset($checkedAccess[$authItem])) {
					$status = $checkedAccess[$authItem];
				} else {
					$status = $checkedAccess[$authItem] = Yii::app()->user->checkAccess($authItem, array('model'=>$model));
				}
				$enabled = ($enabled === null || $enabled) && $status;
			//}

            $valid = !$enabled || $model->isTransitionAllowed($targetState);

			$urlParams = array('id' => $model->id, 'targetState' => $targetState);
			if (!$state->confirmation_required) {
				$urlParams['confirmed'] = true;
			} else {
				$urlParams['return'] = $this->id;
			}
            $entry = array(
                'post'      => $state->post_label,
                'label'     => $sources[$sourceState]->label,
                'icon'      => $state->icon,
                'class'     => $state->css_class,
                'target'    => $targetState,
                'enabled'   => $enabled && $valid,
                'valid'     => $valid,
                'url'       => $this->controller->createUrl($this->id, $urlParams),
            );
            if ($state->display_order) {
                $result[$state->display_order] = $entry;
            } else {
                $result[] = $entry;
            }
		}
		ksort($result);
		return $result;
	}

    /**
     * Builds a menu item used in the context menu.
     * @param string $action target action
     * @param array $transitions obtained by getGroupedByTarget()
     * @param mixed $model target model
     * @param mixed $sourceState current value of the state attribute
     * @param boolean $isAdmin if true, won't check access and all transitions will be displayed
     */
    public static function getContextMenuItem($action, $transitions, $model, $sourceState, $isAdmin=false)
    {
        $statusMenu = array(
            'label' => Yii::t('app', 'Status changes'),
            'icon'  => 'share',
            'url'	=> '#',
            'items' => array(),
        );
        foreach($transitions as $targetState => $target) {
            $state = $target['state'];
            $sources = $target['sources'];

            if (!$isAdmin && !isset($sources[$sourceState])) continue;

            $enabled = $isAdmin ? true : null;
            if (isset($sources[$sourceState])) {
                $sourceStateObject = $sources[$sourceState];
                //foreach($sources[$sourceState] as $sourceStateObject) {
                    $authItem = $sourceStateObject->auth_item_name;
                    if (isset($checkedAccess[$authItem])) {
                        $status = $checkedAccess[$authItem];
                    } else {
                        $status = $checkedAccess[$authItem] = Yii::app()->user->checkAccess($authItem, array('model'=>$model));
                    }
                    $enabled = ($enabled === null || $enabled) && $status;
                //}
            }
            $url = array('state', 'id' => $model->primaryKey, 'targetState' => $targetState);
            $statusMenu['items'][] = array(
                'label'		=> $state->label,
                'icon'		=> $state->icon,
                'url'		=> $enabled ? $url : null,
            );
        }
        $statusMenu['disabled'] = $model->primaryKey === null || empty($statusMenu['items']);
        return $statusMenu;
    }
}

