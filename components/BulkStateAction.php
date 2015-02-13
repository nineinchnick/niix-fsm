<?php

/**
 * BulkStateAction works like StateAction, only on a group of records.
 *
 * @see StateAction
 *
 * Since this class is both an action provider and action class, remember to set properties for the subactions:
 * <pre>
 * return array(
 *     ...other actions...
 *     'update.' => array(
 *         'class' => 'BulkUpdateAction',
 *         'runBatch' => array(
 *             'postRoute' => 'index',
 *         ),
 *     ),
 * )
 * </pre>
 *
 * @author jwas
 */
class BulkStateAction extends BaseBulkAction
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
     * @var callable A closure to check if current user is a superuser and authorization should be skipped.
     */
    public $isAdminCallback;
    /**
     * @var string Class name of state action used to perform single operations.
     */
    public $stateActionClass = 'fsm.components.StateAction';
    /**
     * @var boolean Is the job run in a single query.
     */
    public $singleQuery = false;
    /**
     * @var boolean Is the job run in a single transaction.
     * WARNING! Implies a single batch which may run out of execution time.
     * Enable this if there can be a SQL error that would interrupt the whole batch.
     */
    public $singleTransaction = false;
    /**
     * @var string A route to redirect to after finishing the job.
     * It should display flash messages.
     */
    public $postRoute;
    /**
     * @var string Key for flash message set after finishing the job.
     */
    public $postFlashKey = 'success';
    /**
     * @var CAction StateAction instance used to perform transitions.
     */
    private $_stateAction;

    /**
     * Configures authItemTemplate property.
     *
     * @param string $controller
     * @param string $id
     */
    public function __construct($controller, $id)
    {
        parent::__construct($controller, $id);

        if ($this->singleQuery) {
            throw new CException('Not implemented - the singleQuery option has not been implemented yet.');
        }
        if (is_string($this->stateAuthItemTemplate)) {
            $this->stateAuthItemTemplate = strtr($this->stateAuthItemTemplate, array(
                '{modelClass}' => $this->controller->authModelClass,
            ));
        }
        if (is_string($this->updateAuthItemTemplate)) {
            $this->updateAuthItemTemplate = strtr($this->updateAuthItemTemplate, array(
                '{modelClass}' => $this->controller->authModelClass,
            ));
        }
        $this->_stateAction = Yii::createComponent(array(
            'class' => $this->stateActionClass,
            'stateAuthItemTemplate' => $this->stateAuthItemTemplate,
            'updateAuthItemTemplate' => $this->updateAuthItemTemplate,
            'isAdminCallback' => $this->isAdminCallback,
        ), $controller, $id);
    }

    /**
     * @inheritdoc
     */
    public static function actions()
    {
        return self::defaultActions(__CLASS__);
    }

    protected function getSourceState($model)
    {
        $criteria = $this->getCriteria();
        $criteria->select = 'COUNT(DISTINCT '.$model->dbConnection->quoteColumnName('t.'.$model->stateAttributeName).') as t0_c0';
        $finder = new EActiveFinder($model, is_array($criteria->with) ? $criteria->with : array());
        $command = $finder->createCommand($criteria);
        $sourceStates = $command->queryColumn();
        if (count($sourceStates) > 1) {
            throw new CException(Yii::t('app', 'All selected models must have same source state.'));
        }
        return reset($sourceStates);
    }

    /**
     * Renders a form and/or confirmation.
     */
    public function prepare()
    {
        $targetState = Yii::app()->request->getParam('targetState');
        $model = new $this->controller->modelClass;
        $model->scenario = IStateful::SCENARIO;
        $model->{$model->stateAttributeName} = $this->getSourceState($model);
        list($stateChange, $sourceState, $uiType) = $this->_stateAction->prepare($model);

        $this->_stateAction->checkTransition($model, $stateChange, $sourceState, $targetState);

        $model->setTransitionRules($targetState);
        $this->controller->initForm($model);

        $this->_stateAction->render(array(
            'model'         => $model,
            'sourceState'   => $sourceState,
            'targetState'   => $targetState,
            'transition'    => $stateChange['targets'][$targetState],
            'format'        => $uiType,
            'stateActionUrl'=> $this->controller->createUrl($this->mainId.'.runBatch'),
        ));
    }

    /**
     * Performs state changes.
     */
    public function runBatch()
    {
        if (($targetState = Yii::app()->request->getParam('targetState')) === null) {
            throw new CHttpException(400, Yii::t('yii','Your request is invalid.'));
        }
        $baseModel = new $this->controller->modelClass;
        $baseModel->scenario = IStateful::SCENARIO;
        $baseModel->{$baseMdel->stateAttributeName} = $this->getSourceState($baseMdel);
        list($stateChange, $sourceState, $uiType) = $this->_stateAction->prepare($baseModel);

        if ($this->singleTransaction) {
            $trx = $baseModel->dbConnection->currentTransaction === null ? $baseModel->dbConnection->beginTransaction() : null;
        }
        $dataProvider = $this->getDataProvider($baseModel, $this->getCriteria());
        $skippedKeys = array();
        $failedKeys = array();
        foreach($dataProvider->getData() as $model) {
            if (isset($stateChange['state']->auth_item_name) && !Yii::app()->user->checkAccess($stateChange['state']->auth_item_name, array('model'=>$model))) {
                $skippedKeys[] = $model->primaryKey;
            }

            $model->setTransitionRules($targetState);
            $this->controller->initForm($model);

            if (!$this->_stateAction->performTransition($model, $stateChange, $sourceState, $targetState, true)) {
                //! @todo errors should be gathered and displayed somewhere, maybe add a postSummary action in this class
                $failedKeys[] = $model->primaryKey;
            }
        }

        if ($trx !== null) {
            $trx->commit();
        }
        $message = Yii::t('app', '{number} out of {total} {model} has been successfully updated.', array(
            '{number}' => $dataProvider->getTotalItemCount() - count($failedKeys) - count($skippedKeys),
            '{total}' => $dataProvider->getTotalItemCount(),
            '{model}' => $baseModel->label($dataProvider->getTotalItemCount()),
        ));
        Yii::app()->user->setFlash($this->postFlashKey, $message);
        $this->controller->redirect(array($this->postRoute));
    }
}
