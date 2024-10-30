<?php

class CloudPayments_Api
{
  private string $enabledDMS;
  private string $status_cancel;
  private string $status_pay;
  private string $api_pass;
  private string $DMS_AU_status;
  private string $public_id;
  
  public function __construct($enabledDMS, $statusCancel, $statusPay, $apiPass, $DMS_AU_status, $publicID)
    {
        $this->enabledDMS     = $enabledDMS;
        $this->status_cancel = $statusCancel;
        $this->status_pay     = $statusPay;
        $this->api_pass       = $apiPass;
        $this->DMS_AU_status  = $DMS_AU_status;
        $this->public_id      = $publicID;
    }
    
    public function processRequest()
    {
        $action  = $_GET['action'];
        $request = $_POST;
        
        if (empty($request))
          exit('{"error":"empty request"}');
        
        if (!$this->CheckHmac()){
          exit('{"error":"access denied"}');
        }
        
        switch ($action) {
            case 'check':
                $this->processCheckAction($request);
                break;
            case 'fail':
                $this->processFailAction($request);
                break;
            case 'pay':
                $this->processSuccessAction($request);
                break;
            case 'confirm':
                $this->processConfirmAction($request);
                break;
            case 'receipt':
                $this->processReceiptAction($request);
                break;
            case 'cancel':
            case 'void':
            case 'refund':
                $this->processRefundAction($request);
                break;
            default:
                exit('{"error":"unknown action"}');
        }
        
        die();
    }
    
    public function processCheckAction($request)
    {
        
        if ( ! empty($request['Data'])) {
            $request_data = json_decode(stripslashes($request['Data']), true);
            
            if (isset($request_data['add_payment_method']) && $request_data['add_payment_method'] == 1) {
                echo json_encode(array('code' => 0));
                
                return;
            }
        }
        
        $order = self::getOrder($request);
        $accountId = $order->get_billing_email() ?? $order->get_user_id();
        
        if (empty($order)) {
            die('ORDER NOT FOUND');
        }
        
        if ($order->get_payment_method() !== 'wc_cloudpayments_gateway'){
          die('PAYMENT NOT FOUND');
        }
        
        $data['code'] = 0;
        
        if ( ! $this->isCorrectSum($request, $order)) {
            $data['code'] = 11;
        }
        
        if ( ! $this->isCorrectOrderID($order, $request)) {
            $data['code'] = 10;
        }
        
        if ($order->has_status($this->status_pay)) {
            $data['code'] = 13;
        }
        
        if ( ! $request['AccountId'] == $accountId) {
            $data['code'] = 12;
        }
        
        if ($order->is_paid()) {
            $data['code'] = 13;
        }
        
        if ($order->has_status('cancelled')) {
            $data['code'] = 20;
        }
        
        echo json_encode($data);
    }
    
    private function processFailAction($request)
    {
        $order        = $this->getOrder($request);
        $data['code'] = 0;
        
        if ($order) {
            $order->update_status('wc-pending');
        }
        
        echo json_encode($data);
    }

    private function processSuccessAction($request)
    {
        
        $order    = $this->getOrder($request);
        $DMS_TYPE = $this->enabledDMS;
        
        if (!is_object($order)) {
          echo json_encode(array('code' => 10));
          return;
        }
        
        if ($DMS_TYPE == 'yes') {
            if ($order) {
                $order->update_status($this->DMS_AU_status);
            }
        } else {
            $order->update_status($this->status_pay);
            $order->payment_complete();
            $order->add_order_note(sprintf('Payment approved (TransactionID: %s)', json_encode($request['TransactionId'])));
        }
        
        /** СОЗДАНИЕ ТОКЕНА */
        
        if ( ! empty($request['AccountId'])) {
            
            $tokens  = WC_Payment_Tokens::get_customer_tokens($request['AccountId'], 'wc_cloudpayments_gateway');
            $user_id = $request['AccountId'];
            $result  = true;
            
            $year  = '20' . substr($request['CardExpDate'], -2);
            $month = substr($request['CardExpDate'], 0, 2);
            
            foreach ($tokens as $token) {
                
                $expiry_year  = $token->get_expiry_year('');
                $expiry_month = $token->get_expiry_month('');
                $card_type    = $token->get_card_type('');
                $last4        = $token->get_last4('');
                $gateway_id   = $token->get_gateway_id('');
                
                if ($gateway_id == 'wc_cloudpayments_gateway' &&
                    $last4 == $request['CardLastFour'] &&
                    $card_type == $request['CardType'] &&
                    $expiry_month == $month &&
                    $expiry_year == $year) {
                    $result = false;
                    break;
                }
            }
            
            if ($result and !empty($request['Token'])) {
                $token = new WC_Payment_Token_CC();
                $token->set_token($request['Token']);
                $token->set_gateway_id('wc_cloudpayments_gateway');
                $token->set_card_type($request['CardType']);
                $token->set_last4($request['CardLastFour']);
                $token->set_expiry_month($month);
                $token->set_expiry_year($year);
                $token->set_user_id($user_id);
                $token->set_default('true');
                $token->save();
            }
            
            $request_data = json_decode(stripslashes($request['Data']), true);
            
            if (isset($request_data['add_payment_method']) && $request_data['add_payment_method'] == 1) {
                if ($request['Data']) {
                    $auth = base64_encode($this->public_id . ":" . $this->api_pass);
                    wp_remote_post('https://api.cloudpayments.ru/payments/void', array(
                        'timeout'     => 30,
                        'redirection' => 5,
                        'httpversion' => '1.0',
                        'blocking'    => true,
                        'headers'     => array('Authorization' => 'Basic ' . $auth, 'Content-Type' => 'application/json'),
                        'body'        => json_encode(array('TransactionId' => $request['TransactionId'])),
                        'cookies'     => array()
                    ));
                }
            }
            
            
        }
        
        /** КОНЕЦ - СОЗДАНИЕ ТОКЕНА */
        
        echo json_encode(array('code' => 0));
    }
    
    private function processRefundAction($request)
    {
        $order = self::getOrder($request);
        if ($order->get_payment_method() !== 'wc_cloudpayments_gateway'){
          die('PAYMENT NOT FOUND');
        }
        if ($order) {
            $order->update_status($this->status_cancel);
        }
        $data['code'] = 0;
        echo json_encode($data);
    }
    
    private function processConfirmAction($request)
    {
        $order = self::getOrder($request);
        if ($order->get_payment_method() !== 'wc_cloudpayments_gateway'){
          die('PAYMENT NOT FOUND');
        }
        if ($order) {
            $order->update_status($this->status_pay);
        }
        $data['code'] = 0;
        echo json_encode($data);
    }
    
    public static function getOrder($request)
    {
        global $woocommerce;
        $order = wc_get_order($request['InvoiceId']);
        
        return $order;
    }
  
    private function CheckHmac()
    {
      $headers = $this->detallheaders();
      if (!array_key_exists('Content-Hmac', $headers)) {
        return false;
      }
      $message = file_get_contents('php://input');
      $s = hash_hmac('sha256', $message, $this->api_pass, true);
      $hmac = base64_encode($s);
      return $headers['Content-Hmac'] == $hmac;
    }

    private function detallheaders()
    {
      if (!is_array($_SERVER)) {
        return array();
      }
      $headers = array();
      foreach ($_SERVER as $name => $value) {
        if (substr($name, 0, 5) == 'HTTP_') {
          $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
        }
      }
      return $headers;
    }
    
    private function processReceiptAction($request)
    {
        
        if ($request['Type'] == 'IncomeReturn') {
            $Type = 'возврата прихода';
        } elseif ($request['Type'] == 'Income') {
            $Type = 'прихода';
        }
        $url   = $request['Url'];
        $note  = 'Ссылка на чек ' . $Type . ': ' . esc_url($url);
        $order = self::getOrder($request);
        if ($order->get_payment_method() !== 'wc_cloudpayments_gateway'){
          die('PAYMENT NOT FOUND');
        }
        $var   = $order->add_order_note($note, 1);
        $order->save();
        $data['code'] = 0;
        echo json_encode($data);
        exit;
    }
    
    private function isCorrectSum($request, $order)
    {
        $sum        = $request['Amount'];
        $paymentSum = $order->get_total();
        
        return round($paymentSum, 2) == round($sum, 2);
    }
    
    private function isCorrectOrderID($order, $request)
    {
        $oid       = $request['InvoiceId'];
        $paymentid = $order->get_id();
        
        return round($paymentid, 2) == round($oid, 2);
    }
    
}
