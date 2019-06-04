<?php

namespace Develodesign\Easymanage\Api;

interface MailprocessInterface {

    /**
     * Get content email string
     * @return string
     */
    public function process();


    /**
     * Get emails subscriber id
     * @return string
     */
    public function subscriberids();

}
