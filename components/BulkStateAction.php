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
     * @var string if null, defaults to "{modeClass}.update", set to false to skip checkAccess
     */
    public $authItemTemplate = '{modelClass}.update';
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
    }

    /**
     * @inheritdoc
     */
    public static function actions()
    {
        return self::defaultActions(__CLASS__);
    }

    /**
     */
    public function prepare()
    {
        $this->controller->redirect(array_merge(array($this->getMainId().'.runBatch'), $_GET));
    }

    /**
     */
    public function runBatch()
    {
    }
}
