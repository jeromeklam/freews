<?php
namespace FreeWS\Wamp2;

/**
 *
 * @author jeromeklam
 *
 */
interface DataproviderInterface
{

    /**
     * Get data for one vent
     *
     * @param string $p_event
     * @param string $p_type
     * @param mixed $p_id
     *
     * @return mixed
     */
    public function getDataByEvent($p_event, $p_type, $p_id);
}
