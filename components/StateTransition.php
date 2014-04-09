<?php

class StateTransition extends CComponent
{
    public $source_state;
    public $target_state;
    public $label;
    public $post_label;
    public $icon;
    public $css_class;
    public $auth_item_name;
    public $confirmation_required = false;
    public $display_order;

    public static function groupBySource($transitions, $source_attribute, $target_attribute)
    {
        $result = array();
        foreach($transitions as $transition) {
            if (!isset($result[$transition->$source_attribute])) {
                $result[$transition->$source_attribute] = array('state' => $transition, 'targets' => array());
            }
            $result[$transition->$source_attribute]['targets'][$transition->$target_attribute] = $transition;
        }
        return $result;
    }

    public static function groupByTarget($transitions, $source_attribute, $target_attribute)
    {
        $result = array();
        foreach($transitions as $transition) {
            if (!isset($result[$transition->$target_attribute])) {
                $result[$transition->$target_attribute] = array('state' => $transition, 'sources' => array());
            }
            $result[$transition->$target_attribute]['sources'][$transition->$source_attribute] = $transition;
        }
        return $result;
    }
}
