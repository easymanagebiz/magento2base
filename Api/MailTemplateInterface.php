<?php

namespace Develodesign\Easymanage\Api;

interface MailTemplateInterface {

    /**
     * Get email templates id => value
     * @return string
     */
    public function all();


    /**
     * Save email template
     * @return string
     */
    public function save();


    /**
     * Delete email template
     * @return string
     */
    public function deleteone();


    /**
     * Get email template
     * @return string
     */
    public function getone();


}
