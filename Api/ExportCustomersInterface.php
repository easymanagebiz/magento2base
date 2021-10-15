<?php

namespace Develodesign\Easymanage\Api;

interface ExportCustomersInterface {

    /**
     * Export magento products as json
     * @return string
     */
    public function export();


    /**
     * Search magento products as json
     * @return string
     */
    public function search();
}
