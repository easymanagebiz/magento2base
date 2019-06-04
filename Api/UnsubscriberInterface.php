<?php

namespace Develodesign\Easymanage\Api;

interface UnsubscriberInterface {

    /**
     * Get unsubscribers emails
     * @return string
     */
    public function export();

    /**
     * Save unsubscribers emails
     * @return string
     */
    public function save();

}
