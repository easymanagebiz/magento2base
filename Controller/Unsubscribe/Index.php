<?php

namespace Develodesign\Easymanage\Controller\Unsubscribe;

class Index extends \Magento\Framework\App\Action\Action
{

    protected $_modelEmails;

    protected $_modelUnsubscriberEmails;

    protected $resultRawFactory;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Develodesign\Easymanage\Model\Email $modelEmails,
        \Develodesign\Easymanage\Model\Emailunsubscriber $modelUnsubscriberEmails,
        \Magento\Framework\Controller\Result\RawFactory $resultRawFactory

    )
    {

        $this->_modelUnsubscriberEmails = $modelUnsubscriberEmails;
        $this->_modelEmails = $modelEmails;
        $this->resultRawFactory    = $resultRawFactory;
        parent::__construct($context);
    }

    public function execute()
    {
      $subscriberId = $this->getRequest()->getParam('id');
      if(!$subscriberId) {
        return $this->_redirect('/');
      }

      $modelEmail = $this->_modelEmails->load($subscriberId, 'unique_id');
      if(!$modelEmail->getId()) {
        return $this->_redirect('/');
      }

      $modelEmailUnsubscriber = $this->_modelUnsubscriberEmails->load($modelEmail->getEmailAddress(), 'email_address');
      if(!$modelEmailUnsubscriber->getId()) {
        $modelEmailUnsubscriber->setEmailAddress($modelEmail->getEmailAddress());
        $modelEmailUnsubscriber->save();
      }

      $result = $this->resultRawFactory->create();
      $redirectUrl = $this->_url->getUrl('/');

      $text = '<html><body><div style="text-align:center">';
      $text .= '<h4>' .  __('You are unsubscribed from email list') . '</h4><br>';
      $text .= __('You will be redirected to home page in <span style="font-weight:bold;" id="sec">5</span> sec. Or click <a href="%1">here</a>', $redirectUrl);
      $text .= '<script>var interval = 5; setInterval(function() {
        interval--;
        if(interval <= 0) {
          document.location.href = "' . $redirectUrl . '";
        }else{
          var el = document.getElementById("sec");
          el.innerHTML = interval;
        }
      }, 1000);</script>';

      $text .= '</div></body></html>';

      $result->setContents($text);

      return $result;

    }

}
