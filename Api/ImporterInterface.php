<?php

namespace Develodesign\Easymanage\Api;

interface ImporterInterface {

    /**
     * Save magento products
     * @return string
     */
    public function save();

    /**
     * Update magento products
     * @return string
     */
    public function process();

}
