<?php

namespace Develodesign\Easymanage\Model\Addon\Mailunsubscribers;

interface ApiInterface {

    /**
     * Export usubscribers as json
     * @return string
     */
    public function exportunsubscribers();


    /**
     * Update all unsubscribers emails
     * @return string
     */
    public function saveunsubscribers();
}
