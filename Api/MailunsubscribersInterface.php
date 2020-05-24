<?php

namespace Develodesign\Easymanage\Api;

interface MailunsubscribersInterface {

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
