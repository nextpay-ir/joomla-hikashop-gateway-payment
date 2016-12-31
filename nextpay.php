<?php

defined('_JEXEC') or die('Restricted access');
class plgHikashoppaymentNextpay extends hikashopPaymentPlugin
{
    public $accepted_currencies = [
        'IRR', 'TOM',
    ];

    public $multiple = true;
    public $name = 'nextpay';
    public $doc_form = 'nextpay';

    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);
    }

    public function onBeforeOrderCreate(&$order, &$do)
    {
        if (parent::onBeforeOrderCreate($order, $do) === true) {
            return true;
        }

        if (empty($this->payment_params->merchant)) {
            $this->app->enqueueMessage('Please check your &quot;Nextpay&quot; plugin configuration');
            $do = false;
        }
    }

    public function onAfterOrderConfirm(&$order, &$methods, $method_id)
    {
        parent::onAfterOrderConfirm($order, $methods, $method_id);
        try {
            $client = new SoapClient('https://api.nextpay.org/gateway/token.wsdl', ['encoding' => 'UTF-8']);
        } catch (SoapFault $ex) {
            die('System Error1: constructor error');
        }
        try {
            $callBackUrl = HIKASHOP_LIVE.'index.php?option=com_hikashop&ctrl=checkout&task=notify&notif_payment='.$this->name.'&tmpl=component&lang='.$this->locale.$this->url_itemid.'&orderID='.$order->order_id;
            $amount = round($order->cart->full_total->prices[0]->price_value_with_tax, (int) $this->currency->currency_locale['int_frac_digits']);
            $parameters = [
                'api_key'  => $this->payment_params->api,
                'amount'      => $amount,
                'order_id' => $order->order_id,
                'callback_uri' => $callBackUrl,
            ];
            $result = $client->TokenGenerator($parameters);
            $result = $result->TokenGeneratorResult;
            if(intval($result->code) == -1){
                $this->payment_params->url = 'https://api.nextpay.org/gateway/payment/'.$result->trans_id;

                return $this->showPage('end');
            } else {
                echo "<p align=center>Bank Error $result->Status.<br />Order UNSUCCSESSFUL!</p>";
                exit;
                die;
            }
        } catch (SoapFault $ex) {
            die('System Error2: error in get data from bank');
        }
    }

    public function onPaymentNotification(&$statuses)
    {
        $filter = JFilterInput::getInstance();

        $dbOrder = $this->getOrder($_REQUEST['orderID']);
        $this->loadPaymentParams($dbOrder);
        if (empty($this->payment_params)) {
            return false;
        }
        $this->loadOrderData($dbOrder);
        if (empty($dbOrder)) {
            echo 'Could not load any order for your notification '.$_REQUEST['orderID'];

            return false;
        }
        $order_id = $dbOrder->order_id;

        $url = HIKASHOP_LIVE.'administrator/index.php?option=com_hikashop&ctrl=order&task=edit&order_id='.$order_id;
        $order_text = "\r\n".JText::sprintf('NOTIFICATION_OF_ORDER_ON_WEBSITE', $dbOrder->order_number, HIKASHOP_LIVE);
        $order_text .= "\r\n".str_replace('<br/>', "\r\n", JText::sprintf('ACCESS_ORDER_WITH_LINK', $url));

        if (isset($_POST['trans_id']) AND isset($_POST['order_id'])) {
            $history = new stdClass();
            $history->notified = 0;
            $history->amount = round($dbOrder->order_full_price, (int) $this->currency->currency_locale['int_frac_digits']);
            $history->data = ob_get_clean();

            try {
                $client = new SoapClient('https://api.nextpay.org/gateway/verify.wsdl', ['encoding' => 'UTF-8']);
            } catch (SoapFault $ex) {
                die('System Error1: constructor error');
            }
            try {
                $msg = '';
                $parameters = [
                    'api_key' => $this->payment_params->api,
                    'trans_id'  => $_POST['trans_id'],
                    'amount'     => $history->amount,
                    'order_id' => $order_id,
                ];
                $result = $client->PaymentVerification($parameters);
                $result = $result->PaymentVerificationResult;
                if(intval($result->code) == 0){
                    $order_status = $this->payment_params->verified_status;
                    $msg = 'پرداخت شما با موفقیت انجام شد.';
                } else {
                    $order_status = $this->payment_params->pending_status;
                    $order_text = JText::sprintf('CHECK_DOCUMENTATION', HIKASHOP_HELPURL.'payment-nextpay-error#verify')."\r\n\r\n".$order_text;
                    $msg = $this->getStatusMessage($result->code);
                }
            } catch (SoapFault $ex) {
                die('System Error2: error in get data from bank');
            }

            $config = &hikashop_config();
            if ($config->get('order_confirmed_status', 'confirmed') == $order_status) {
                $history->notified = 1;
            }

            $email = new stdClass();
            $email->subject = JText::sprintf('PAYMENT_NOTIFICATION_FOR_ORDER', 'Nextpay', $order_status, $dbOrder->order_number);
            $email->body = str_replace('<br/>', "\r\n", JText::sprintf('PAYMENT_NOTIFICATION_STATUS', 'Nextpay', $order_status)).' '.JText::sprintf('ORDER_STATUS_CHANGED', $order_status)."\r\n\r\n".$order_text;
            $this->modifyOrder($order_id, $order_status, $history, $email);
        } else {
            $order_status = $this->payment_params->invalid_status;
            $email = new stdClass();
            $email->subject = JText::sprintf('NOTIFICATION_REFUSED_FOR_THE_ORDER', 'Nextpay').'invalid transaction';
            $email->body = JText::sprintf("Hello,\r\n A Nextpay notification was refused because it could not be verified by the nextpay server (or pay cenceled)")."\r\n\r\n".JText::sprintf('CHECK_DOCUMENTATION', HIKASHOP_HELPURL.'payment-nextpay-error#invalidtnx');
            $action = false;
            $this->modifyOrder($order_id, $order_status, null, $email);
        }
        header('location: '.HIKASHOP_LIVE.'index.php?option=com_hikashop&ctrl=order');
        exit;
    }

    public function getStatusMessage($status)
    {
        $status = (string) $status;
        $error_array = array(
            0 => "Complete Transaction",
            -1 => "Default State",
            -2 => "Bank Failed or Canceled",
            -3 => "Bank Payment Pendding",
            -4 => "Bank Canceled",
            -20 => "api key is not send",
            -21 => "empty trans_id param send",
            -22 => "amount in not send",
            -23 => "callback in not send",
            -24 => "amount incorrect",
            -25 => "trans_id resend and not allow to payment",
            -26 => "Token not send",
            -30 => "amount less of limite payment",
            -32 => "callback error",
            -33 => "api_key incorrect",
            -34 => "trans_id incorrect",
            -35 => "type of api_key incorrect",
            -36 => "order_id not send",
            -37 => "transaction not found",
            -38 => "token not found",
            -39 => "api_key not found",
            -40 => "api_key is blocked",
            -41 => "params from bank invalid",
            -42 => "payment system problem",
            -43 => "gateway not found",
            -44 => "response bank invalid",
            -45 => "payment system deactived",
            -46 => "request incorrect",
            -48 => "commission rate not detect",
            -49 => "trans repeated",
            -50 => "account not found",
            -51 => "user not found"
        );
        if (isset($statusCode[$status])) {
            return $statusCode[$error_array];
        }

        return 'خطای نامشخص. کد خطا: '.$status;
    }

    public function onPaymentConfiguration(&$element)
    {
        $subtask = JRequest::getCmd('subtask', '');

        parent::onPaymentConfiguration($element);
    }

    public function onPaymentConfigurationSave(&$element)
    {
        return true;
    }

    public function getPaymentDefaultValues(&$element)
    {
        $element->payment_name = 'درگاه پرداخت نکست پی';
        $element->payment_description = '';
        $element->payment_images = '';

        $element->payment_params->invalid_status = 'cancelled';
        $element->payment_params->pending_status = 'created';
        $element->payment_params->verified_status = 'confirmed';
    }
}
