<?php

namespace Develodesign\Easymanage\Api;

interface SaveProductsInterface {

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
