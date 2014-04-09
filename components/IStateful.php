<?php

interface IStateful
{
    /**
     * Returns all possible state transitions as an array of items like:
     *   array('state'=>StateTransition, 'targets'=>StateTransition[]).
     * @return array
     */
    public function getTransitionsGroupedBySource();
    /**
     * Returns all possible state transitions as an array of items like:
     *   array('state'=>StateTransition, 'sources'=>StateTransition[]).
     * @return array
     */
    public function getTransitionsGroupedByTarget();
    /**
     * Returns the name of the state attribute.
     * @return mixed
     */
    public function getStateAttributeName();
    /**
     * @param $targetState mixed
     * @return boolean
     */
    public function isTransitionAllowed($targetState);
    /**
     * Besides simply updating the state attribute's value this method could also:
     * * save the change reason
     * * log the change
     * * raise events
     *
     * @param $targetState mixed
     * @param $reason string
     * @return boolean
     */
    public function performTransition($targetState, $reason=null);
}
