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

    /**
     * Creates a list of StateTransition as all possible combinations between passes states.
     * States must be objects with following properties: id, pre_label, post_label, icon, css_class
     * @param $states array
     * @return array
     */
    public static function statesToTransitions($states)
    {
        $result = array();
        for($i = 0; $i < count($states); $i++) {
            for($j = 0; $j < count($states); $j++) {
                if ($i == $j) continue;
                $transition = new StateTransition;
                $transition->source_state = $states[$i]->id;
                $transition->target_state = $states[$j]->id;
                $transition->label = $states[$j]->pre_label;
                $transition->post_label = $states[$j]->post_label;
                $transition->icon = $states[$j]->icon;
                $transition->css_class = $states[$j]->css_class;
                $result[] = $transition;
            }
        }
        return $result;
    }

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
