<?php
/**
 * StateAction displays a list of possible state transitions and raises an AR model event.
 *
 * It requires to be used by a NetController class.
 *
 * Installation steps:
 * * if required, call the fsm console command to generate migrations, run them and create two models
 * * attach the fsm behavior to the model
 * * call StateAction::getContextMenuItem when building the context menu in CRUD controller
 *
 * @author Jan Was <jwas@nets.com.pl>
 */
class StateAction extends CAction
{
    /**
     * @var string Prefix of the auth item used to check access. Controller's $authModelClass is appended to it.
     */
    public $stateAuthItem = 'update ';
    /**
     * @var string Auth item used to check access to update the main model. If null, the update button won't be available.
     */
    public $updateAuthItem;
    /**
     * @var callable a closure to check if current user is a superuser and authorization should be skipped
     */
    public $isAdminCallback;

	/**
	 * Runs the action.
	 */
	public function run($id, $targetState = null, $confirmed = false)
	{
        $this->controller->viewPath = Yii::getPathOfAlias('fsm.views');

        $model = $this->loadModel($id, $this->controller->modelClass);
        if ($this->controller->checkAccessInActions && !Yii::app()->user->checkAccess($this->stateAuthItem.$this->controller->authModelClass, array('model'=>$model))) {
            throw new CHttpException(403,Yii::t('app','You are not authorized to perform this action on this object.'));
        }

        $behavior = $model->asa('fsm');
        $stateAttribute = $behavior->attribute;
        $transitionsModel = CActiveRecord::model($behavior->transitionsModelClass);
        $stateChanges = self::getGroupedBySource($transitionsModel);


        $uiType = $model->uiType($stateAttribute);
        $sourceState = $model->$stateAttribute;
        if (!isset($stateChanges[$sourceState])) {
            $stateChanges[$sourceState] = array(
                'state' => null,
                'targets' => array(),
            );
        }

        if ($targetState === null) {
            // display all possible state transitions to select from
			$this->render('state', array(
				'model'         => $model,
                'targetState'   => null,
				'states'        => $this->prepareStates($model, $stateAttribute, $transitionsModel),
			));
		} elseif ($targetState === $sourceState) {
            Yii::app()->user->setFlash('error', Yii::t('app', 'Status has already been changed').', '.CHtml::link(Yii::t('app','return to'), $this->createUrl('view', array('id'=>$model->id))));
			$this->render('confirm', array(
				'model'         => $model,
				'sourceState'   => $sourceState,
				'targetState'   => $targetState,
                'format'        => $uiType,
			));
		} else if ((!is_callable($this->isAdminCallback) || !call_user_func($this->isAdminCallback)) && !isset($stateChanges[$sourceState]['targets'][$targetState])) {
			$sourceLabel = Yii::app()->format->format($sourceState, $model->uiType($stateAttribute));
			$targetLabel = Yii::app()->format->format($targetState, $model->uiType($stateAttribute));
			throw new CHttpException(400, Yii::t('app', 'Changing application status from {from} to {to} is not allowed.', array('{from}'=>$sourceLabel,'{to}'=>$targetLabel)));
		} else if (isset($stateChanges[$sourceState]['state']->auth_item_name) && !Yii::app()->user->checkAccess($stateChanges[$sourceState]['state']->auth_item_name, array('model'=>$model))) {
			$sourceLabel = Yii::app()->format->format($sourceState, $model->uiType($stateAttribute));
			$targetLabel = Yii::app()->format->format($targetState, $model->uiType($stateAttribute));
			throw new CHttpException(400, Yii::t('app', 'You don\'t have necessary permissions to move the application from {from} to {to}.', array('{from}'=>$sourceLabel, '{to}'=>$targetLabel)));
		}

		if ($targetState === $sourceState) {
            Yii::app()->user->setFlash('error', Yii::t('app', 'Status has already been changed').', '.CHtml::link(Yii::t('app','return to'), $this->createUrl('view', array('id'=>$model->id))));
        } elseif ($confirmed && $model->isTransitionAllowed($targetState)) {
            if (!$model->performTransition($targetState, isset($_REQUEST['reason']) && ($reason=trim($_REQUEST['reason']))!=='' ? $reason : null)) {
                Yii::app()->user->setFlash('error', Yii::t('app', 'Failed to update status. Administrator has been notified.'));
            }
            Yii::app()->user->setFlash('success', $stateChanges[$sourceState]['targets'][$targetState]->post_label);
            $this->redirect(array('view', 'id'=>$model->id));
            Yii::app()->end();
        }

        $this->render('confirm', array(
            'model'         => $model,
            'sourceState'   => $sourceState,
            'targetState'   => $targetState,
            'format'        => $uiType,
        ));
	}

	/**
	 * Builds an array containing all possible status changes and result of validating every transition.
	 * @params mixed $model
	 * @params string $attribute
     * @params mixed $transitionsModel
	 * @return array contains in order: (array)statuses, (boolean)valid
	 */
    public function prepareStates($model, $attribute, $transitionsModel)
    {
		$checkedAccess = array();
        if ($this->updateAuthItem !== null) {
            $checkedAccess[$this->updateAuthItem.$this->controller->authModelClass] = Yii::app()->user->checkAccess($this->updateAuthItem.$this->controller->authModelClass, array('model'=>$model));
        }
		$result = array();
        if ($this->updateAuthItem !== null) {
            $result[] = array(
				'label' => Yii::t('app', 'Update item'),
				'icon' => 'pencil',
				'url' => array('update', 'id' => $model->getPrimaryKey()),
				'enabled' => $checkedAccess[$this->updateAuthItem.$this->controller->authModelClass],
				'class' => 'btn-success',
			);
        }
		$valid = true;
        $sourceState = $model->$attribute;
        foreach(self::getGroupedByTarget($transitionsModel) as $targetState => $target) {
            $state = $target['state'];
            $sources = $target['sources'];

			if (!isset($sources[$sourceState])) continue;

			$enabled = null;
			foreach($sources[$sourceState] as $sourceStateObject) {
                $authItem = $sourceStateObject->auth_item_name;
				if (isset($checkedAccess[$authItem])) {
					$status = $checkedAccess[$authItem];
				} else {
					$status = $checkedAccess[$authItem] = Yii::app()->user->checkAccess($authItem, array('model'=>$model));
				}
				$enabled = ($enabled === null || $enabled) && $status;
			}

            $valid = !$enabled || $model->isTransitionAllowed($targetState);

			$urlParams = array('id' => $model->id, 'state' => $targetState);
			if (!$state->confirmation_required) {
				$urlParams['confirmed'] = true;
			} else {
				$urlParams['return'] = $this->id;
			}
            $entry = array(
                'post'      => $targetState->post_label,
                'label'     => $sources[$sourceState]->label,
                'icon'      => $states[$targetState]->icon,
                'class'     => $states[$targetState]->css_class,
                'target'    => $targetState,
                'enabled'   => $enabled && $allDocsValidates && $allAttachValidates,
                'valid'     => $valid,
                'url'       => $this->createUrl($this->id, $urlParams),
            );
            if ($targetState->display_order) {
                $result[$targetState->display_order] = $entry;
            } else {
                $result[] = $entry;
            }
		}
		ksort($result);
		return $result;
	}

    protected static function getGroupedBySource($transitionsModel)
    {
        $transitions = $transitionsModel->enabled()->findAll();
        $result = array();
        foreach($transitions as $transition) {
            if (!isset($result[$transition->source_status_id])) {
                $result[$transition->source_status_id] = array('state' => $transition, 'targets' => array());
            }
            $result[$transition->source_status_id]['targets'][$transition->target_status_id] = $transition;
        }
        return $result;
    }

    protected static function getGroupedByTarget($transitionsModel)
    {
        $transitions = $transitionsModel->enabled()->findAll();
        $result = array();
        foreach($transitions as $transition) {
            /**
             * if there were target auth items set we would merge them with the source one
            if (isset($result[$transition->target_status_id]) && isset($result[$transition->target_status_id][$transition->source_status_id]))
                $result[$transition->target_status_id][$transition->source_status_id] = array($transition->auth_item_name);
            else
                $result[$transition->target_status_id][$transition->source_status_id] = array($transition->auth_item_name),
             */
            if (!isset($result[$transition->target_status_id])) {
                $result[$transition->target_status_id] = array('state' => $transition, 'sources' => array());
            }
            $result[$transition->target_status_id]['sources'][$transition->source_status_id] = $transition;
        }
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
            'items' => array(),
        );
        foreach($transitions as $targetState => $target) {
            $state = $target['state'];
            $sources = $target['sources'];

            if (!$isAdmin && !isset($sources[$sourceState])) continue;

            $enabled = $isAdmin ? true : null;
            if (isset($sources[$sourceState])) {
                foreach($sources[$sourceState] as $sourceStateObject) {
                    $authItem = $sourceStateObject->auth_item_name;
                    if (isset($checkedAccess[$authItem])) {
                        $status = $checkedAccess[$authItem];
                    } else {
                        $status = $checkedAccess[$authItem] = Yii::app()->user->checkAccess($authItem, array('model'=>$model));
                    }
                    $enabled = ($enabled === null || $enabled) && $status;
                }
            }
            $url = array($action, 'id' => $model->primaryKey, 'state' => $targetState);
            $statusMenu['items'][] = array(
                'label'		=> $targetState->label,
                'icon'		=> $targetState->icon,
                'url'		=> $enabled ? $url : null,
            );
        }
    }
}

