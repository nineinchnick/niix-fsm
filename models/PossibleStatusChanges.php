<?php

Yii::import('application.models._base.BasePossibleApplicationStatusChanges');

class PossibleApplicationStatusChanges extends BasePossibleApplicationStatusChanges
{
	public static function model($className=__CLASS__) {
		return parent::model($className);
	}

    public function scopes() {
        $t = $this->getTableAlias(true);
        return array_merge(parent::scopes(), array(
            'enabled' => array(
                'condition'=>$t.'.enabled=TRUE',
            ),
        ));
    }

    public function forProduct($product) {
        if (is_object($product))
            $product = $product->id;
        $this->getDbCriteria()->mergeWith(array(
            'condition'=>$this->getTableAlias(true).'.product_id=:product_id',
            'params'=>array(':product_id'=>$product),
        ));
        return $this;
    }

    public function getGroupedBySource($product_id) {
        $statusChanges = PossibleApplicationStatusChanges::model()->enabled()->forProduct($product_id)->findAll();
        $result = array();
        foreach($statusChanges as $statusChange) {
            if (!isset($result[$statusChange->from_status_id])) {
                $result[$statusChange->from_status_id] = array(
                    'authItem'=>$statusChange->auth_item_name,
                    $statusChange->to_status_id=>array(),
                );
            } else {
                $result[$statusChange->from_status_id][$statusChange->to_status_id] = array();
            }
        }
        return $result;
    }

    public function getGroupedByTarget($product_id) {
        $statusChanges = PossibleApplicationStatusChanges::model()->enabled()->forProduct($product_id)->findAll();
        $result = array();
        foreach($statusChanges as $statusChange) {
            /**
             * if there were target auth items set we would merge them with the source one
            if (isset($result[$statusChange->to_status_id]) && isset($result[$statusChange->to_status_id][$statusChange->from_status_id]))
                $result[$statusChange->to_status_id][$statusChange->from_status_id] = array($statusChange->auth_item_name);
            else
                $result[$statusChange->to_status_id][$statusChange->from_status_id] = array($statusChange->auth_item_name),
             */
            $result[$statusChange->to_status_id][$statusChange->from_status_id] = array($statusChange->auth_item_name);
        }
        return $result;
    }
}
