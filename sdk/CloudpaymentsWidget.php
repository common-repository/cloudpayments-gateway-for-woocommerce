<?php
  
  namespace Cloudpayments;
  
  class CloudpaymentsWidget
  {
    public string $widgetURL = 'https://widget.cloudpayments.ru/bundles/cloudpayments.js';
    public string $lang = 'ru-RU';
    public string $payType = 'auth';
    public array $options = array(
      'publicId' => '',
      'amount' => 0,
      'currency' => 'RUB',
      'enabledDMS' => false,
    );
    public string $onSuccess = '';
    public string $onFail = '';
    
    function __construct($publicId)
    {
      $this->options['publicId'] = $publicId;
    }
    
    public function setURL($url)
    {
      $this->widgetURL = $url;
    }
    
    public function setLang($lang)
    {
      $this->lang = $lang;
    }
    
    public function setSkin($skin)
    {
      $this->options['skin'] = $skin;
    }
    
    public function setType($payType)
    {
      switch ($payType) {
        case 'auth':
        case 'charge':
          $this->payType = $payType;
          break;
      }
    }
    
    public function setDescription($desc)
    {
      $this->options['description'] = $desc;
    }
    
    public function setAmount($amount)
    {
      $this->options['amount'] = $amount;
    }
    
    public function setCurrency($currency) {
      $this->options['currency'] = $currency;
    }
    
    public function enableDMS() {
      $this->options['enabledDMS'] = true;
    }
    
    public function setEmail($email)
    {
      $this->options['email'] = $email;
    }
    
    public function setInvoiceId($invoiceId)
    {
      $this->options['invoiceId'] = $invoiceId;
    }
    
    public function setAccountId($accountId)
    {
      $this->options['accountId'] = $accountId;
    }
    
    public function setData($data)
    {
      $this->options['data'] = $data;
    }
    
    public function setOnSuccess($func)
    {
      $this->onSuccess = $func;
    }
    
    public function setOnFail($func)
    {
      $this->onFail = $func;
    }
    
    public function getWidgetData() {
      return array(
        'data'       => $this->options,
        'widget_f'   => $this->payType,
        'language'   => $this->lang,
        'return_url' => $this->onSuccess,
        'cancel_return_url'   => $this->onFail,
      );
    }
  }