<?php
/**
 * Shop System Plugins - Terms of Use
 *
 * The plugins offered are provided free of charge by Wirecard Central Eastern Europe GmbH
 * (abbreviated to Wirecard CEE) and are explicitly not part of the Wirecard CEE range of
 * products and services.
 *
 * They have been tested and approved for full functionality in the standard configuration
 * (status on delivery) of the corresponding shop system. They are under General Public
 * License Version 2 (GPLv2) and can be used, developed and passed on to third parties under
 * the same terms.
 *
 * However, Wirecard CEE does not provide any guarantee or accept any liability for any errors
 * occurring when used in an enhanced, customized shop system configuration.
 *
 * Operation in an enhanced, customized configuration is at your own risk and requires a
 * comprehensive test phase by the user of the plugin.
 *
 * Customers use the plugins at their own risk. Wirecard CEE does not guarantee their full
 * functionality neither does Wirecard CEE assume liability for any disadvantages related to
 * the use of the plugins. Additionally, Wirecard CEE does not guarantee the full functionality
 * for customized shop systems or installed plugins of other vendors of plugins within the same
 * shop system.
 *
 * Customers are responsible for testing the plugin's functionality before starting productive
 * operation.
 *
 * By installing the plugin into the shop system the customer agrees to these terms of use.
 * Please do not use the plugin if you do not agree to these terms of use!
 */

defined('_JEXEC') or die('Restricted access');

/* resources:
 * http://www.spiralscripts.co.uk/Joomla-Tips/custom-plugin-fields-in-virtuemart-2-2.html
 * http://docs.joomla.org/Developers
 * order-states: select * from jom_virtuemart_orderstates;
 */


ini_set('include_path', ini_get('include_path') . PATH_SEPARATOR . realpath(dirname(__FILE__)) . '/library');

if (!class_exists('vmPSPlugin')) {
    require(JPATH_VM_PLUGINS . DIRECTORY_SEPARATOR . 'vmpsplugin.php');
}

require_once 'Zend/Loader/Autoloader.php';
Zend_Loader_Autoloader::getInstance()->registerNamespace("WirecardCEE");


class plgVmPaymentwirecardceecheckout extends vmPSPlugin
{
    public static $_this = false;

    protected static $WINDOW_NAME = 'WirecardCEECheckoutFrame';
    protected static $PLUGIN_NAME = 'VirtueMart2_CheckoutPage';
    protected static $PLUGIN_VERSION = '1.5.0';

    protected $_method;
    protected $_order;

    const WCP_CUSTOMER_ID_DEMO = 'D200001';
    const WCP_SHOP_ID_DEMO = '';
    const WCP_SECRET_DEMO = 'B8AKTPWBRMNBV455FG6M2DANE99WU2';
    const WCP_BACKEND_PASSWORD_DEMO = 'jcv45z';
    const WCP_CUSTOMER_ID_TEST = 'D200411';
    const WCP_SHOP_ID_TEST = '';
    const WCP_SECRET_TEST = 'CHCSH7UGHVVX2P7EHDHSY4T2S4CGYK4QBE4M5YUUG2ND5BEZWNRZW5EJYVJQ';
    const WCP_BACKEND_PASSWORD_TEST = '2g4f9q2m';
    const WCP_CUSTOMER_ID_TEST3D = 'D200411';
    const WCP_SHOP_ID_TEST3D = '3D';
    const WCP_SECRET_TEST3D = 'DP4TMTPQQWFJW34647RM798E9A5X7E8ATP462Z4VGZK53YEJ3JWXS98B9P4F';
    const WCP_BACKEND_PASSWORD_TEST3D = '2g4f9q2m';

    const WCP_SERVICE_PROVIDER_PAYOLUTION = 'payolution';

    const INVOICE_INSTALLMENT_MIN_AGE = 18;

    public function __construct(& $subject, $config)
    {
        parent::__construct($subject, $config);
        $this->_loggable = true;
        $this->_debug = true;
        $this->tableFields = array_keys($this->getTableSQLFields());
        $this->_tablepkey = 'id';
        $this->_tableId = 'id';
        $logosFieldName = $this->_psType . '_logos';
        $this->$logosFieldName = array();

        $this->tellMerchantIfConfigurationIsValidate();

        $varsToPush = $this->getVarsToPush();
        $varsToPush['max_retries'] = array(-1, 'int');
        unset($varsToPush['support_email']);
        unset($varsToPush['support_replyto']);
        unset($varsToPush['support_message']);

        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
        $this->sendSupportRequest();
    }

    /**
     * Validates configuration by initiating a transaction with the given parameters and displays the determined state
     */
    private function tellMerchantIfConfigurationIsValidate()
    {
        $data = vRequest::getPost(FILTER_SANITIZE_STRING);

        if (!isset($data['params'])) {
            return;
        }

        //TODO dirty hack... an improvement is strongly encouraged...
        $check_configuration = $data['params']['support_message'];
        if ($check_configuration === '1') {
            $client = new WirecardCEE_QPay_FrontendClient(array(
                'CUSTOMER_ID' => trim($data['params']['customer_id'] ? $data['params']['customer_id'] : ' '),
                'SHOP_ID' => $data['params']['shop_id'],
                'SECRET' => trim($data['params']['secret'] ? $data['params']['secret'] : ' '),
                'LANGUAGE' => $this->_getLanguage()
            ));

            $returnUrl = JROUTE::_(JURI::root());

            $consumerData = new WirecardCEE_Stdlib_ConsumerData();
            $consumerData->setUserAgent($_SERVER['HTTP_USER_AGENT'])
                ->setIpAddress($_SERVER['REMOTE_ADDR']);

            $client->setAmount(0.01)
                ->setCurrency('EUR')
                ->setPaymentType(WirecardCEE_QPay_PaymentType::CCARD)
                ->setOrderDescription('Config Test')
                ->setSuccessUrl($returnUrl)
                ->setCancelUrl($returnUrl)
                ->setFailureUrl($returnUrl)
                ->setConfirmUrl($returnUrl)
                ->setServiceUrl($data['params']['service_url'] ? $data['params']['service_url'] : ' ')
                ->setImageUrl($data['params']['image_url'] ? $data['params']['image_url'] : ' ')
                ->setConsumerData($consumerData);

            $response = $client->initiate();
            if ($response->hasFailed()) {
                $responseArray = $response->getResponse();
                vmError($responseArray['message']);
            } else {
                vmAdminInfo(vmText::_('VMPAYMENT_WIRECARDCEECHECKOUT_CHECK_CONFIGURATION_OK'));
            }
        }
    }

    /**
     * Sends a support request including shop and plugin configuration
     */
    private function sendSupportRequest()
    {
        $data = vRequest::getPost(FILTER_SANITIZE_STRING);

        $support_email = $data['params']['support_email'];
        if (strlen($support_email) > 0) {
            $support_replyto = $data['params']['support_replyto'];
            $support_message = $data['params']['support_message'];

            unset($data['params']['secret']);
            unset($data['params']['support_email']);
            unset($data['params']['support_replyto']);
            unset($data['params']['support_message']);

            $support_message .= sprintf("\tVirtueMart version: %s\n", VmConfig::getInstalledVersion());
            $support_message .= sprintf("\tPlugin: %s %s\n", self::$PLUGIN_NAME, self::$PLUGIN_VERSION);

            foreach ($data['params'] as $key => $value) {
                $support_message .= sprintf("\t%s: %s\n", $key, $value);
            }

            $mailer = JFactory::getMailer();
            $mailer->addRecipient($support_email);

            if (strlen($support_replyto) > 0) {
                $mailer->addReplyTo($support_replyto);
            }

            $mailer->setSubject(vmText::_('VMPAYMENT_WIRECARDCEECHECKOUT_SUPPORT_REQUEST'));
            $mailer->setBody($support_message);

            if ($mailer->Send()) {
                vmAdminInfo(vmText::_('VMPAYMENT_WIRECARDCEECHECKOUT_SUPPORT_SEND_OK'));
            }
        }
    }

    public function getVmPluginCreateTableSQL()
    {
        return $this->createTableSQL('Payment Wirecard Table');
    }

    /**
     * @return string
     */
    public function getTableSQLFields()
    {
        $SQLfields = array(
            'id' => 'int(11) UNSIGNED NOT NULL AUTO_INCREMENT',
            'virtuemart_order_id' => 'int(1) UNSIGNED',
            'order_number' => 'char(64)',
            'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
            'payment_name' => 'varchar(5000)',
            'payment_order_total' => 'decimal(15,5) NOT NULL',
            'payment_currency' => 'smallint(1)',

            // wirecard specific data
            'wirecard_order_number' => 'varchar(50)',
            'wirecard_gateway_ref' => 'varchar(50)',
            'wirecard_response_raw' => 'text');

        return $SQLfields;
    }

    /**
     * User completed checkout, start payment
     * return: may be redirect is done by the payment plugin (eg: paypal)
     * if payment plugin echos a form, false = nothing happen, true= echo form ,
     * 1 = cart should be emptied, 0 cart should not be emptied
     *
     * @param VirtueMartCart $cart
     * @param $order
     * @return bool|null
     */
    public function plgVmConfirmedOrder($cart, $order)
    {
        if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }
        if (!class_exists('VirtueMartModelOrders')) {
            require(JPATH_VM_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'orders.php');
        }

        $this->_setMethod($method);
        $this->_setOrder($order);

        $redirectUrl = $this->_initiatePayment($cart);

        if (!$redirectUrl) {
            $msg = vmText::_('VMPAYMENT_WIRECARDCEECHECKOUT_INITIATE_PAYMENT_ERROR');

            $app = JFactory::getApplication();
            $app->redirect(JRoute::_('index.php?option=com_virtuemart&view=cart&Itemid=' . vRequest::getInt('Itemid') . '&lang=' . vRequest::getCmd('lang', ''), false), $msg);
        }

        $dbValues = array();
        $dbValues['virtuemart_order_id'] = $order['details']['BT']->virtuemart_order_id;
        $dbValues['order_number'] = $order['details']['BT']->order_number;
        $dbValues['virtuemart_paymentmethod_id'] = $cart->virtuemart_paymentmethod_id;
        $dbValues['payment_name'] = parent::renderPluginName($method);
        $dbValues['payment_order_total'] = $this->_getAmount($order);
        $dbValues['payment_currency'] = $this->_getOrderCurrency();
        $this->storePSPluginInternalData($dbValues);

        if ($this->_useIFrame()) {
            $html = sprintf('<iframe src="%s" width="100%%" height="640" name="%s" border="0" frameborder="0"></iframe>',
                $redirectUrl,
                $this->_getWindowName());

            // don't delete the cart, don't send email and don't redirect
            $cart->_confirmDone = false;
            $cart->_dataValidated = false;
            $cart->setCartIntoSession();

            JFactory::getApplication()->input->set('html', $html);
        } else {
            header('Location: ' . $redirectUrl);
            exit;
        }
    }


    /**
     * We are returning to the shop, returnUrl
     *
     * @param $html HTML string which couly be modified
     * @param $paymentResponse response text to be printed
     * @return bool|null|string
     */
    public function plgVmOnPaymentResponseReceived(&$html, &$paymentResponse)
    {
        if (!class_exists('VirtueMartCart')) {
            require(JPATH_VM_SITE . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'cart.php');
        }
        if (!class_exists('shopFunctionsF')) {
            require(JPATH_VM_SITE . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'shopfunctionsf.php');
        }
        if (!class_exists('VirtueMartModelOrders')) {
            require(JPATH_VM_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'orders.php');
        }

        $input = new JInput;

        // do iframe breakout
        if ($input->get('iframebreakout') == 'true') {
            print $this->renderByLayout('breakoutiframe', array(
                'returnUrl' => JROUTE::_(JURI::root() .
                    'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived')
            ));
            die;
        }

        $order_number = $input->get('vmOrderNumber');
        $virtuemart_paymentmethod_id = $input->get('vmPaymentMethodId');

        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return null;
        }
        if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number))) {
            $mainframe = JFactory::getApplication();
            $mainframe->redirect(JRoute::_('index.php/cart'), $input->get('cosumerMessage', '', 'STRING'));
        }

        if (!($paymentTable = $this->getDataByOrderId($virtuemart_order_id))) {
            return '';
        }

        $this->_setMethod($method);

        $order = new VirtueMartModelOrders();
        $order = $order->getOrder($virtuemart_order_id);
        $this->_setOrder($order);

        $this->logInfo(__FUNCTION__ . print_r($_POST, true), 'message');

        $return = WirecardCEE_QPay_ReturnFactory::getInstance($_POST, $this->_getSecret($method));

        if (!$return->validate()) {
            $paymentResponse = JText::_('VMPAYMENT_WIRECARDCEECHECKOUT_INVALID_RESPONSE');
            $mainframe = JFactory::getApplication();
            $mainframe->redirect(JRoute::_('index.php/cart'), JText::_($paymentResponse));
        }

        $paymentState = $input->get('paymentState');
        if ($paymentState == WirecardCEE_QPay_ReturnFactory::STATE_PENDING ||
            $paymentState == WirecardCEE_QPay_ReturnFactory::STATE_SUCCESS
        ) {
            $cart = VirtueMartCart::getCart();
            $cart->emptyCart();
        }

        // C ... completed
        // X ... cancelled
        // R ... refunded
        // S ... shipped
        if (in_array($order['details']['BT']->order_status, array('C', 'X', 'R', 'S'))) {
            $this->logInfo(__FUNCTION__ . ' Can\'t change order state, as the order has already a final state', 'message');
            return true;
        }

        if ($paymentState == WirecardCEE_QPay_ReturnFactory::STATE_PENDING) {
            $modelOrder = VmModel::getModel('orders');
            $order = array();
            $order['order_status'] = $this->_getStatusPending();
            $order['comments'] = JText::sprintf('VMPAYMENT_WIRECARDCEECHECKOUT_PAYMENT_STATUS_PENDING', $order_number);
            $order['customer_notified'] = 0;
            $modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, true);

            $paymentResponse = JText::_('VMPAYMENT_WIRECARDCEECHECKOUT_PAYMENT_PENDING');
            return true;
        }

        if ($paymentState == WirecardCEE_QPay_ReturnFactory::STATE_FAILURE) {
            $modelOrder = VmModel::getModel('orders');
            $order = array();
            $order['order_status'] = $this->_getStatusFailed();
            $order['comments'] = JText::sprintf('VMPAYMENT_WIRECARDCEECHECKOUT_PAYMENT_STATUS_FAILED', $order_number, $return->getErrors()->getMessage());
            $order['customer_notified'] = 0;

            if ($this->_getMethod()->keep_unsuccessful_orders) {
                $modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, true);
            } else {
                $modelOrder->remove(array($virtuemart_order_id));
            }

            $paymentResponse = JText::_('VMPAYMENT_WIRECARDCEECHECKOUT_PAYMENT_FAILED');

            $mainframe = JFactory::getApplication();
            $mainframe->redirect(JRoute::_('index.php/cart'), JText::_($paymentResponse));
        }

        // C ... completed
        if ($order['details']['BT']->order_status == 'C') {
            $paymentResponse = JText::_('VMPAYMENT_WIRECARDCEECHECKOUT_PAYMENT_CONFIRMED');
        }

        $session = JFactory::getSession();
        $data = $session->get('WIRECARDCEECHECKOUT', 0, 'vm');
        if (!empty($data)) {
            $sessionWirecard = unserialize($data);
            $sessionWirecard->consentInvoice = 'off';
            $sessionWirecard->consentInvoiceB2B = 'off';
            $sessionWirecard->consentInstallment = 'off';
            $session->set('WIRECARDCEECHECKOUT', serialize($sessionWirecard), 'vm');
        }
        return true;
    }

    /**
     * Payment notification received, server to server request, confirmUrl
     *
     * @return mixed Null when this method was not selected, otherwise the true or false
     *
     */
    public function plgVmOnPaymentNotification()
    {
        if (!class_exists('VirtueMartModelOrders')) {
            require(JPATH_VM_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'orders.php');
        }

        $input = new JInput;
        $order_number = $input->get('vmOrderNumber');
        $virtuemart_paymentmethod_id = $input->get('vmPaymentMethodId');

        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            $this->logInfo(__FUNCTION__ . ' Can\'t get payment type', 'message');
            return null; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return null;
        }
        if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number))) {
            $this->logInfo(__FUNCTION__ . ' Can\'t get VirtueMart order id', 'message');
            return null;
        }

        if (!($paymentTable = $this->getDataByOrderId($virtuemart_order_id))) {
            $this->logInfo('getDataByOrderId payment not found: exit ', 'ERROR');
            return null;
        }

        $this->_setMethod($method);

        $order = VirtueMartModelOrders::getOrder($virtuemart_order_id);
        $this->_setOrder($order);

        // C ... completed
        // X ... cancelled
        // R ... refunded
        // S ... shipped
        if (in_array($order['details']['BT']->order_status, array('C', 'X', 'R', 'S'))) {
            $this->logInfo(__FUNCTION__ . ' Can\'t change order state, as the order has already a final state', 'message');
            return null;
        }

        $dbValues = array();
        $dbValues['virtuemart_order_id'] = $virtuemart_order_id;
        $dbValues['order_number'] = $order_number;
        $dbValues['virtuemart_paymentmethod_id'] = $virtuemart_paymentmethod_id;
        $dbValues['wirecard_order_number'] = $input->get('orderNumber');
        $dbValues['payment_name'] = parent::renderPluginName($method);
        $dbValues['payment_order_total'] = $this->_getAmount($order);
        $dbValues['payment_currency'] = $this->_getOrderCurrency();

        $dbValues['wirecard_gateway_ref'] = $input->get('gatewayReferenceNumber');
        $dbValues['wirecard_response_raw'] = serialize($_POST);
        $this->storePSPluginInternalData($dbValues, 'virtuemart_order_id', false);

        $modelOrder = VmModel::getModel('orders');
        $order = array();
        $order['customer_notified'] = 1;

        $this->logInfo(__FUNCTION__ . print_r($_POST, true), 'message');

        $message = null;
        try {
            $return = WirecardCEE_QPay_ReturnFactory::getInstance($_POST, $this->_getSecret($method));
            if (!$return->validate()) {
                $order['order_status'] = $this->_getStatusFailed();
                $message = $order['comments'] = JText::_('VMPAYMENT_WIRECARDCEECHECKOUT_INVALID_RESPONSE');
            }

            $order['order_status'] = $method->status_pending;
            $paymentState = $input->get('paymentState');
            switch ($paymentState) {
                case WirecardCEE_QPay_ReturnFactory::STATE_SUCCESS:
                    $order['order_status'] = $this->_getStatusSuccess();
                    $order['comments'] = JText::_('VMPAYMENT_WIRECARDCEECHECKOUT_PAYMENT_CONFIRMED');
                    break;

                case WirecardCEE_QPay_ReturnFactory::STATE_PENDING:
                    $order['order_status'] = $this->_getStatusPending();
                    $order['comments'] = JText::_('VMPAYMENT_WIRECARDCEECHECKOUT_PAYMENT_PENDING');
                    break;

                case WirecardCEE_QPay_ReturnFactory::STATE_CANCEL:
                    $order['order_status'] = $this->_getStatusCancel();
                    $order['comments'] = JText::_('VMPAYMENT_WIRECARDCEECHECKOUT_PAYMENT_CANCELLED');
                    break;

                case WirecardCEE_QPay_ReturnFactory::STATE_FAILURE:
                    $order['order_status'] = $this->_getStatusFailed();
                    $order['comments'] = JText::_('VMPAYMENT_WIRECARDCEECHECKOUT_PAYMENT_FAILED');
                    break;

                default:
                    break;
            }
        } catch (Exception $e) {
            $this->logInfo(__FUNCTION__ . $e->getMessage(), 'error');
            $order['order_status'] = $this->_getStatusFailed();
            $order['comments'] = JText::_('VMPAYMENT_WIRECARDCEECHECKOUT_PAYMENT_FAILED');
            $message = $e->getMessage();
        }

        if (!$this->_getMethod()->keep_unsuccessful_orders && ($order['order_status'] == $this->_getStatusFailed() || $order['order_status'] == $this->_getStatusCancel())) {
            $modelOrder->remove(array($virtuemart_order_id));
        } else {
            $modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, true);
        }

        echo WirecardCEE_QPay_ReturnFactory::generateConfirmResponseString($message, true);
    }


    /**
     * cancelUrl
     * From the payment page, the user has cancelled the order. The order previousy created is deleted.
     * The cart is not emptied, so the user can reorder if necessary.
     *
     */
    public function plgVmOnUserPaymentCancel()
    {
        if (!class_exists('VirtueMartCart')) {
            require(JPATH_VM_SITE . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'cart.php');
        }
        if (!class_exists('shopFunctionsF')) {
            require(JPATH_VM_SITE . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'shopfunctionsf.php');
        }
        if (!class_exists('VirtueMartModelOrders')) {
            require(JPATH_VM_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'orders.php');
        }

        $input = new JInput;

        // do iframe breakout
        if ($input->get('iframebreakout') == 'true') {
            print $this->renderByLayout('breakoutiframe', array(
                'returnUrl' => JROUTE::_(JURI::root() .
                    'index.php?option=com_virtuemart&view=pluginresponse&task=pluginUserPaymentCancel')
            ));

            die;
        }

        $order_number = $input->get('vmOrderNumber');
        $virtuemart_paymentmethod_id = $input->get('vmPaymentMethodId');

        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }
        if (empty($order_number) ||
            !$this->selectedThisElement($method->payment_element)
        ) {
            return null;
        }
        if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number))) {
            return null;
        }

        if (!($paymentTable = $this->getDataByOrderId($virtuemart_order_id))) {
            return '';
        }

        $this->_setMethod($method);
        $order = VirtueMartModelOrders::getOrder($virtuemart_order_id);
        $this->_setOrder($order);

        VmInfo(Jtext::_('VMPAYMENT_WIRECARDCEECHECKOUT_PAYMENT_CANCELLED'));
        $this->handlePaymentUserCancel($virtuemart_order_id);
        return true;
    }


    /**
     * This is for adding the input data of the payment method to the cart, after selecting
     *
     * @param VirtueMartCart $cart
     * @param $msg
     * @return null if payment not selected; true if card infos are correct; string containing the errors id cc is not valid
     */
    public function plgVmOnSelectCheckPayment(VirtueMartCart $cart, &$msg)
    {
        if (!$this->selectedThisByMethodId($cart->virtuemart_paymentmethod_id)) {
            return null; // Another method was selected, do nothing
        }

        $method = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id);

        $this->_setMethod($method);

        $input = new JInput;
        $paymenttype = $input->get('wirecard_paymenttype');
        $consentInvoice = 'off';
        $consentInvoiceB2B = 'off';
        $consentInstallment = 'off';

        switch ($paymenttype) {
            case 'invoice':
                $consentInvoice = $input->get('consent_invoice');
                break;
            case 'invoiceb2b':
                $consentInvoiceB2B = $input->get('consent_invoiceb2b');
                break;
            case 'installment':
                $consentInstallment = $input->get('consent_installment');
                break;
        }

        $found = !strlen($paymenttype);

        foreach ($this->_getEnabledPaymentTypes() as $m) {
            if (strtolower($m['value']) == $paymenttype) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            $msg .= JText::_('VMPAYMENT_WIRECARDCEECHECKOUT_ERROR_PAYMENTTYPE');
            return false;
        }

        $session = JFactory::getSession();
        $sessionWirecard = new stdClass();
        $sessionWirecard->paymenttype = $paymenttype;
        $sessionWirecard->consentInvoice = $consentInvoice;
        $sessionWirecard->consentInvoiceB2B = $consentInvoiceB2B;
        $sessionWirecard->consentInstallment = $consentInstallment;
        $session->set('WIRECARDCEECHECKOUT', serialize($sessionWirecard), 'vm');

        return true;
    }


    /**
     * This is for checking the input data of the payment method within the checkout
     *
     * @param VirtueMartCart $cart
     * @return null if payment not selected; true if card infos are correct;
     */
    public function plgVmOnCheckoutCheckDataPayment(VirtueMartCart $cart)
    {
        if (!$this->selectedThisByMethodId($cart->virtuemart_paymentmethod_id)) {
            return null; // Another method was selected, do nothing
        }

        $method = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id);

        $this->_setMethod($method);

        $session = JFactory::getSession();
        $data = $session->get('WIRECARDCEECHECKOUT', 0, 'vm');
        if (empty($data))
            return false;

        $sessionWirecard = unserialize($data);
        $found = !strlen($sessionWirecard->paymenttype);

        foreach ($this->_getEnabledPaymentTypes() as $m) {
            if (strtolower($m['value']) == $sessionWirecard->paymenttype) {
                $found = true;
                break;
            }
        }

        return $found;
    }

    /**
     * Calculate the price (value, tax_id) of the selected method
     *
     * @param VirtueMartCart $cart
     * @param array $cart_prices
     * @param                $cart_prices_name
     * @return bool|null
     */
    public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name)
    {
        return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }

    /**
     * Display stored payment data for an order
     *
     * @see components/com_virtuemart/helpers/vmPaymentPlugin::plgVmOnShowOrderPaymentBE()
     */
    public function plgVmOnShowOrderBEPayment($virtuemart_order_id, $payment_method_id)
    {
        if (!$this->selectedThisByMethodId($payment_method_id)) {
            return null; // Another method was selected, do nothing
        }
        if (!($paymentTable = $this->getDataByOrderId($virtuemart_order_id))) {
            return null;
        }
        $data = unserialize($paymentTable->wirecard_response_raw);

        $blacklist = array('vmOrderNumber', 'vmPaymentMethodId', 'responseFingerprint', 'responseFingerprintOrder');
        $html = '<table class="adminlist">' . "\n";
        $html .= $this->getHtmlHeaderBE();
        $html .= $this->getHtmlRowBE('WIRECARDCEECHECKOUT_NAME', $paymentTable->payment_name);
        foreach ($data as $key => $value) {
            if (in_array($key, $blacklist))
                continue;
            $html .= str_replace("WIRECARDCEECHECKOUT_", "", $this->getHtmlRowBE("WIRECARDCEECHECKOUT_$key", $value));
        }
        $html .= '</table>' . "\n";
        return $html;
    }

    /**
     * plgVmDisplayListFEPayment
     * This event is fired to display the plugin methods in the cart (edit shipment/payment) for example
     *
     * @param VirtueMartCart $cart Cart object
     * @param integer $selected ID of the method selected
     * @param string $htmlIn HTML
     * @return boolean True on success, false on failures, null when this plugin was not selected.
     *
     * @author Valerie Isaksen
     */
    public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn)
    {
        return $this->displayListFE($cart, $selected, $htmlIn);
    }


    /**
     * This method is fired when showing when priting an Order
     * It displays the the payment method-specific data.
     * XXX never invoked by virtuemart
     *
     * @param integer $order_number The order number
     * @param integer $method_id method used for this order
     * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
     * @author Valerie Isaksen
     */
    public function plgVmonShowOrderPrintPayment($order_number, $method_id)
    {
        $html = $this->onShowOrderPrint($order_number, $method_id);
        return $html;
    }


    /**
     * Create the table for this plugin if it does not yet exist.
     * This functions checks if the called plugin is active one.
     * When yes it is calling the standard method to create the tables
     *
     * @author Wirecard CEE
     *
     */
    public function plgVmOnStoreInstallPaymentPluginTable($jplugin_id)
    {
        return $this->onStoreInstallPluginTable($jplugin_id);
    }

    public function plgVmDeclarePluginParamsPayment($name, $id, &$data)
    {
        return $this->declarePluginParams('payment', $name, $id, $data);
    }

    public function plgVmSetOnTablePluginParamsPayment($name, $id, &$table)
    {
        return $this->setOnTablePluginParams($name, $id, $table);
    }

    public function plgVmDeclarePluginParamsPaymentVM3(&$data)
    {
        return $this->declarePluginParams('payment', $data);
    }

    ######################################################################
    #                        PROTECTED METHODS                           #
    ######################################################################

    /**
     * Check if the payment conditions are fulfilled for this payment method
     * If false, payment method is not selectable
     * Always return true in our case, because we are managing many paymenttypes with this plugin
     *
     * @param $cart cart
     * @param $method
     * @param $cart_prices
     * @return true: if the conditions are fulfilled, false otherwise
     *
     */
    protected function checkConditions($cart, $method, $cart_prices)
    {
        return true;
    }

    /**
     * Render the enabled paymenttypes
     *
     * @param $plugin
     * @param $selectedPlugin
     * @param $pluginSalesPrice
     * @return string
     */
    protected function getPluginHtml($plugin, $selectedPlugin, $pluginSalesPrice)
    {
        $this->_setMethod($plugin);
        $pluginmethod_id = $this->_idName;

        $session = JFactory::getSession();
        $data = $session->get('WIRECARDCEECHECKOUT', 0, 'vm');
        if (empty($data)) {
            $paymenttype_selected = null;
        } else {
            $sessionWirecard = unserialize($data);
            $paymenttype_selected = $sessionWirecard->paymenttype;
        }

        $html = parent::getPluginHtml($plugin, $selectedPlugin, $pluginSalesPrice);
        $html .= $this->renderByLayout('displaypayment', array(
            'paymenttypes' => $this->_getEnabledPaymentTypes(),
            'paymentmethod_id' => $plugin->$pluginmethod_id,
            'paymenttype_selected' => $paymenttype_selected
        ));

        return $html;
    }


    /**
     * Initiate Payment
     *
     * @param VirtueMartCart $cart
     * @return string Url to be redirected
     * @throws Exception
     */
    protected function _initiatePayment($cart)
    {
        try {
            $order = $this->_getOrder();

            $client = new WirecardCEE_QPay_FrontendClient(array(
                'CUSTOMER_ID' => $this->_getCustomerId(),
                'SHOP_ID' => $this->_getShopId(),
                'SECRET' => $this->_getSecret(),
                'LANGUAGE' => $this->_getLanguage()
            ));

            $session = JFactory::getSession();
            $data = $session->get('WIRECARDCEECHECKOUT', null, 'vm');
            $sessionWirecard = unserialize($data);
            $paymentType = strtoupper($sessionWirecard->paymenttype);

            //if payment type == MAESTRO replace it with CCARD
            if ($paymentType == WirecardCEE_QPay_PaymentType::MAESTRO) {
                $paymentType = WirecardCEE_QPay_PaymentType::CCARD;
            }

            if ($paymentType == WirecardCEE_QPay_PaymentType::INVOICE . 'B2B') {
                $paymentType = WirecardCEE_QPay_PaymentType::INVOICE;
            }

            // consumer data (IP and User agent) are mandatory!
            $consumerData = new WirecardCEE_Stdlib_ConsumerData();
            $consumerData->setUserAgent($_SERVER['HTTP_USER_AGENT'])
                ->setIpAddress($_SERVER['REMOTE_ADDR']);

            if ($this->_sendShippingInformation()) {
                $this->_setConsumerShippingInformation($consumerData);
            }

            if ($this->_sendBillingInformation() || in_array($paymentType, array(WirecardCEE_QPay_PaymentType::INVOICE, WirecardCEE_QPay_PaymentType::INSTALLMENT))) {
                $this->_setConsumerBillingInformation($consumerData);
            }

            if ($this->_sendBasketInformation() || in_array($paymentType, $this->_getRatepayInstallmentInvoiceArray())) {
                foreach ($this->_generateBasketInformation($cart) as $k => $v) {
                    $client->$k = $v;
                }
            }

            $returnUrl = JROUTE::_(JURI::root() .
                'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived' .
                '&iframebreakout=' . ($this->_useIFrame() ? 'true' : 'false') .
                '&Itemid=' . JFactory::getApplication()->input->getInt('Itemid'));

            $cancelURL = JROUTE::_(JURI::root() .
                'index.php?option=com_virtuemart&view=pluginresponse&task=pluginUserPaymentCancel' .
                '&iframebreakout=' . ($this->_useIFrame() ? 'true' : 'false') .
                '&Itemid=' . JFactory::getApplication()->input->getInt('Itemid')
            );

            $confirmUrl = JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component');

            $version = WirecardCEE_QPay_FrontendClient::generatePluginVersion(
                $this->_getVendor(),
                VmConfig::getInstalledVersion(),
                self::$PLUGIN_NAME,
                self::$PLUGIN_VERSION);

            $client->setAmount($this->_getAmount())
                ->setCurrency($this->_getOrderCurrency())
                ->setPaymentType($paymentType)
                ->setOrderDescription($this->_getOrderDescription())
                ->setPluginVersion($version)
                ->setSuccessUrl($returnUrl)
                ->setPendingUrl($returnUrl)
                ->setCancelUrl($cancelURL)
                ->setFailureUrl($returnUrl)
                ->setConfirmUrl($confirmUrl)
                ->setServiceUrl($this->_getServiceUrl())
                ->setImageUrl($this->_getImageUrl())
                ->setBackgroundColor($this->_getBackgroundColor())
                ->setConsumerData($consumerData)
                ->setDisplayText($this->_getDisplayText())
                ->setCustomerStatement($this->_getCustomerStatement($paymentType, $this->_getMethod()->shopname))
                ->setDuplicateRequestCheck($this->_getDuplicateRequestCheck())
                ->setMaxRetries($this->_getMaxRetries())
                ->setAutoDeposit($this->_getAutoDeposit($paymentType))
                ->setWindowName($this->_getWindowName());

            if (array_key_exists('ST', $order['details'])) {
                $client->createConsumerMerchantCrmId($order['details']['ST']->email);
            }
            else {
                $client->createConsumerMerchantCrmId($order['details']['BT']->email);
            }

            if ($this->_sendConfirmationEmail()) {
                $client->setConfirmMail($this->_getConfirmMail());
            }

            if ($this->_sendLayout()) {
                $client->setLayout($this->_getLayout());
            }

            if ($this->_useMobileDetect()) {
                $client->setLayout($this->_getClientDevice());
            }

            $client->vmOrderNumber = $order['details']['BT']->order_number;
            $client->vmPaymentMethodId = $order['details']['BT']->virtuemart_paymentmethod_id;

            $response = $client->initiate();

            if ($response->hasFailed()) {
                vmError(JText::_("Response failed! Error: {$response->getError()->getMessage()}"));
                //throw new \Exception("Response failed! Error: {$response->getError()->getMessage()}", 500);
            }
        } catch (Exception $e) {
            $sErrorMessage = "Initialization failed with exception: {$e->getMessage()}";
            throw($e);
        }

        return $response->getRedirectUrl();
    }

    /**
     * Return enabled paymenttypes
     *
     * @return array
     */
    protected function _getEnabledPaymentTypes()
    {
        $sessionWirecard = null;
        $session = JFactory::getSession();
        $data = $session->get('WIRECARDCEECHECKOUT', 0, 'vm');
        if (!empty($data)) {
            $sessionWirecard = unserialize($data);
        }

        $cart = VirtueMartCart::getCart();
        $paymentTypes = array();
        if ((int)$this->_getMethod()->paymenttype_select == 1) {
            $paymentTypes[1]['title'] = $this->_getPaymentTypeName(WirecardCEE_QPay_PaymentType::SELECT);
            $paymentTypes[1]['value'] = WirecardCEE_QPay_PaymentType::SELECT;
        }
        if ((int)$this->_getMethod()->paymenttype_ccard == 1) {
            $paymentTypes[2]['image'] = strtolower(WirecardCEE_QPay_PaymentType::CCARD);
            $paymentTypes[2]['title'] = $this->_getPaymentTypeName(WirecardCEE_QPay_PaymentType::CCARD);
            $paymentTypes[2]['value'] = WirecardCEE_QPay_PaymentType::CCARD;
        }
        if ((int)$this->_getMethod()->paymenttype_ccard_moto == 1) {
            $paymentTypes[3]['image'] = strtolower(WirecardCEE_QPay_PaymentType::CCARD_MOTO);
            $paymentTypes[3]['title'] = $this->_getPaymentTypeName(WirecardCEE_QPay_PaymentType::CCARD_MOTO);
            $paymentTypes[3]['value'] = WirecardCEE_QPay_PaymentType::CCARD_MOTO;
        }
        if ((int)$this->_getMethod()->paymenttype_maestro == 1) {
            $paymentTypes[4]['image'] = strtolower(WirecardCEE_QPay_PaymentType::MAESTRO);
            $paymentTypes[4]['title'] = $this->_getPaymentTypeName(WirecardCEE_QPay_PaymentType::MAESTRO);
            $paymentTypes[4]['value'] = WirecardCEE_QPay_PaymentType::MAESTRO;
        }
        if ((int)$this->_getMethod()->paymenttype_bancontact_mistercash == 1) {
            $paymentTypes[5]['image'] = strtolower(WirecardCEE_QPay_PaymentType::BMC);
            $paymentTypes[5]['title'] = $this->_getPaymentTypeName(WirecardCEE_QPay_PaymentType::BMC);
            $paymentTypes[5]['value'] = WirecardCEE_QPay_PaymentType::BMC;
        }
        if ((int)$this->_getMethod()->paymenttype_ekonto == 1) {
            $paymentTypes[6]['image'] = strtolower(WirecardCEE_QPay_PaymentType::EKONTO);
            $paymentTypes[6]['title'] = $this->_getPaymentTypeName(WirecardCEE_QPay_PaymentType::EKONTO);
            $paymentTypes[6]['value'] = WirecardCEE_QPay_PaymentType::EKONTO;
        }
        if ((int)$this->_getMethod()->paymenttype_eps == 1) {
            $paymentTypes[7]['image'] = strtolower(WirecardCEE_QPay_PaymentType::EPS);
            $paymentTypes[7]['title'] = $this->_getPaymentTypeName(WirecardCEE_QPay_PaymentType::EPS);
            $paymentTypes[7]['value'] = WirecardCEE_QPay_PaymentType::EPS;
        }
        if ((int)$this->_getMethod()->paymenttype_giropay == 1) {
            $paymentTypes[8]['image'] = strtolower(WirecardCEE_QPay_PaymentType::GIROPAY);
            $paymentTypes[8]['title'] = $this->_getPaymentTypeName(WirecardCEE_QPay_PaymentType::GIROPAY);
            $paymentTypes[8]['value'] = WirecardCEE_QPay_PaymentType::GIROPAY;
        }
        if ((int)$this->_getMethod()->paymenttype_idl == 1) {
            $paymentTypes[9]['image'] = strtolower(WirecardCEE_QPay_PaymentType::IDL);
            $paymentTypes[9]['title'] = $this->_getPaymentTypeName(WirecardCEE_QPay_PaymentType::IDL);
            $paymentTypes[9]['value'] = WirecardCEE_QPay_PaymentType::IDL;
        }
        if ((int)$this->_getMethod()->paymenttype_poli == 1) {
            $paymentTypes[10]['image'] = strtolower(WirecardCEE_QPay_PaymentType::POLI);
            $paymentTypes[10]['title'] = $this->_getPaymentTypeName(WirecardCEE_QPay_PaymentType::POLI);
            $paymentTypes[10]['value'] = WirecardCEE_QPay_PaymentType::POLI;
        }
        if ((int)$this->_getMethod()->paymenttype_p24 == 1) {
            $paymentTypes[11]['image'] = strtolower(WirecardCEE_QPay_PaymentType::P24);
            $paymentTypes[11]['title'] = $this->_getPaymentTypeName(WirecardCEE_QPay_PaymentType::P24);
            $paymentTypes[11]['value'] = WirecardCEE_QPay_PaymentType::P24;
        }
        if ((int)$this->_getMethod()->paymenttype_skrilldirect == 1) {
            $paymentTypes[12]['image'] = strtolower(WirecardCEE_QPay_PaymentType::SKRILLDIRECT);
            $paymentTypes[12]['title'] = $this->_getPaymentTypeName(WirecardCEE_QPay_PaymentType::SKRILLDIRECT);
            $paymentTypes[12]['value'] = WirecardCEE_QPay_PaymentType::SKRILLDIRECT;
        }
        if ((int)$this->_getMethod()->paymenttype_sofortueberweisung == 1) {
            $paymentTypes[13]['image'] = strtolower(WirecardCEE_QPay_PaymentType::SOFORTUEBERWEISUNG);
            $paymentTypes[13]['title'] = $this->_getPaymentTypeName(WirecardCEE_QPay_PaymentType::SOFORTUEBERWEISUNG);
            $paymentTypes[13]['value'] = WirecardCEE_QPay_PaymentType::SOFORTUEBERWEISUNG;
        }
        if ((int)$this->_getMethod()->paymenttype_tatrapay == 1) {
            $paymentTypes[14]['image'] = strtolower(WirecardCEE_QPay_PaymentType::TATRAPAY);
            $paymentTypes[14]['title'] = $this->_getPaymentTypeName(WirecardCEE_QPay_PaymentType::TATRAPAY);
            $paymentTypes[14]['value'] = WirecardCEE_QPay_PaymentType::TATRAPAY;
        }
        if ((int)$this->_getMethod()->paymenttype_trustly == 1) {
            $paymentTypes[15]['image'] = strtolower(WirecardCEE_QPay_PaymentType::TRUSTLY);
            $paymentTypes[15]['title'] = $this->_getPaymentTypeName(WirecardCEE_QPay_PaymentType::TRUSTLY);
            $paymentTypes[15]['value'] = WirecardCEE_QPay_PaymentType::TRUSTLY;
        }
        if ((int)$this->_getMethod()->paymenttype_trustpay == 1) {
            $paymentTypes[16]['image'] = strtolower(WirecardCEE_QPay_PaymentType::TRUSTPAY);
            $paymentTypes[16]['title'] = $this->_getPaymentTypeName(WirecardCEE_QPay_PaymentType::TRUSTPAY);
            $paymentTypes[16]['value'] = WirecardCEE_QPay_PaymentType::TRUSTPAY;
        }
        if ((int)$this->_getMethod()->paymenttype_epay_bg == 1) {
            $paymentTypes[17]['image'] = strtolower(WirecardCEE_QPay_PaymentType::EPAYBG);
            $paymentTypes[17]['title'] = $this->_getPaymentTypeName(WirecardCEE_QPay_PaymentType::EPAYBG);
            $paymentTypes[17]['value'] = WirecardCEE_QPay_PaymentType::EPAYBG;
        }
        if ((int)$this->_getMethod()->paymenttype_moneta == 1) {
            $paymentTypes[18]['image'] = strtolower(WirecardCEE_QPay_PaymentType::MONETA);
            $paymentTypes[18]['title'] = $this->_getPaymentTypeName(WirecardCEE_QPay_PaymentType::MONETA);
            $paymentTypes[18]['value'] = WirecardCEE_QPay_PaymentType::MONETA;
        }
        if ((int)$this->_getMethod()->paymenttype_invoice == 1 && $this->isInvoiceAllowed($cart)) {
            $paymentTypes[19]['image'] = strtolower(WirecardCEE_QPay_PaymentType::INVOICE);
            $paymentTypes[19]['title'] = $this->_getPaymentTypeName(WirecardCEE_QPay_PaymentType::INVOICE);
            $paymentTypes[19]['value'] = WirecardCEE_QPay_PaymentType::INVOICE;

            if ($this->_getInvoiceFinancialInstitution() == self::WCP_SERVICE_PROVIDER_PAYOLUTION && $this->_getPayolutionTerms()) {
                $paymentTypes[19]['consent_text'] = $this->_getPayolutionConsentText();
                $paymentTypes[19]['consent_checked'] = '';
                if ($sessionWirecard != null) {
                    $paymentTypes[19]['consent_checked'] = ($sessionWirecard->consentInvoice == 'on') ? ' checked="checked"' : '';
                }
            }
        }
        if ((int)$this->_getMethod()->paymenttype_invoiceb2b == 1 && $this->isInvoiceB2BAllowed($cart)) {
            $paymentTypes[20]['image'] = strtolower(WirecardCEE_QPay_PaymentType::INVOICE);
            $paymentTypes[20]['title'] = $this->_getPaymentTypeName(WirecardCEE_QPay_PaymentType::INVOICE . 'B2B');
            $paymentTypes[20]['value'] = WirecardCEE_QPay_PaymentType::INVOICE . 'B2B';

            $paymentTypes[20]['consent_text'] = $this->_getPayolutionConsentText();
            $paymentTypes[20]['consent_checked'] = '';
            if ($sessionWirecard != null) {
                $paymentTypes[20]['consent_checked'] = ($sessionWirecard->consentInvoiceB2B == 'on') ? ' checked="checked"' : '';
            }
        }
        if ((int)$this->_getMethod()->paymenttype_installment == 1 && $this->isInstallmentAllowed($cart)) {
            $paymentTypes[21]['image'] = strtolower(WirecardCEE_QPay_PaymentType::INSTALLMENT);
            $paymentTypes[21]['title'] = $this->_getPaymentTypeName(WirecardCEE_QPay_PaymentType::INSTALLMENT);
            $paymentTypes[21]['value'] = WirecardCEE_QPay_PaymentType::INSTALLMENT;

            if ($this->_getInstallmentFinancialInstitution() == self::WCP_SERVICE_PROVIDER_PAYOLUTION && $this->_getPayolutionTerms()) {
                $paymentTypes[21]['consent_text'] = $this->_getPayolutionConsentText();
                $paymentTypes[21]['consent_checked'] = '';
                if ($sessionWirecard != null) {
                    $paymentTypes[21]['consent_checked'] = ($sessionWirecard->consentInstallment == 'on') ? ' checked="checked"' : '';
                }
            }
        }
        if ((int)$this->_getMethod()->paymenttype_paypal == 1) {
            $paymentTypes[22]['image'] = strtolower(WirecardCEE_QPay_PaymentType::PAYPAL);
            $paymentTypes[22]['title'] = $this->_getPaymentTypeName(WirecardCEE_QPay_PaymentType::PAYPAL);
            $paymentTypes[22]['value'] = WirecardCEE_QPay_PaymentType::PAYPAL;
        }
        if ((int)$this->_getMethod()->paymenttype_psc == 1) {
            $paymentTypes[23]['image'] = strtolower(WirecardCEE_QPay_PaymentType::PSC);
            $paymentTypes[23]['title'] = $this->_getPaymentTypeName(WirecardCEE_QPay_PaymentType::PSC);
            $paymentTypes[23]['value'] = WirecardCEE_QPay_PaymentType::PSC;
        }
        if ((int)$this->_getMethod()->paymenttype_quick == 1) {
            $paymentTypes[24]['image'] = strtolower(WirecardCEE_QPay_PaymentType::QUICK);
            $paymentTypes[24]['title'] = $this->_getPaymentTypeName(WirecardCEE_QPay_PaymentType::QUICK);
            $paymentTypes[24]['value'] = WirecardCEE_QPay_PaymentType::QUICK;
        }
        if ((int)$this->_getMethod()->paymenttype_skrillwallet == 1) {
            $paymentTypes[25]['image'] = strtolower(WirecardCEE_QPay_PaymentType::SKRILLWALLET);
            $paymentTypes[25]['title'] = $this->_getPaymentTypeName(WirecardCEE_QPay_PaymentType::SKRILLWALLET);
            $paymentTypes[25]['value'] = WirecardCEE_QPay_PaymentType::SKRILLWALLET;
        }
        if ((int)$this->_getMethod()->paymenttype_sepadd == 1) {
            $paymentTypes[26]['image'] = strtolower(WirecardCEE_QPay_PaymentType::SEPADD);
            $paymentTypes[26]['title'] = $this->_getPaymentTypeName(WirecardCEE_QPay_PaymentType::SEPADD);
            $paymentTypes[26]['value'] = WirecardCEE_QPay_PaymentType::SEPADD;
        }
        if ((int)$this->_getMethod()->paymenttype_mpass == 1) {
            $paymentTypes[27]['image'] = strtolower(WirecardCEE_QPay_PaymentType::MPASS);
            $paymentTypes[27]['title'] = $this->_getPaymentTypeName(WirecardCEE_QPay_PaymentType::MPASS);
            $paymentTypes[27]['value'] = WirecardCEE_QPay_PaymentType::MPASS;
        }
        if ((int)$this->_getMethod()->paymenttype_pbx == 1) {
            $paymentTypes[28]['image'] = strtolower(WirecardCEE_QPay_PaymentType::PBX);
            $paymentTypes[28]['title'] = $this->_getPaymentTypeName(WirecardCEE_QPay_PaymentType::PBX);
            $paymentTypes[28]['value'] = WirecardCEE_QPay_PaymentType::PBX;
        }
        if ((int)$this->_getMethod()->paymenttype_voucher == 1) {
            $paymentTypes[29]['image'] = strtolower(WirecardCEE_QPay_PaymentType::VOUCHER);
            $paymentTypes[29]['title'] = $this->_getPaymentTypeName(WirecardCEE_QPay_PaymentType::VOUCHER);
            $paymentTypes[29]['value'] = WirecardCEE_QPay_PaymentType::VOUCHER;
        }

        return $paymentTypes;
    }

    /**
     * Return translated name of the given paymenttype
     *
     * @param string $value
     * @return string
     */
    protected function _getPaymentTypeName($value)
    {
        switch ($value) {
            case WirecardCEE_QPay_PaymentType::SELECT:
                $title = JText::_('VMPAYMENT_WIRECARDCEECHECKOUT_PAYMENTTYPE_SELECT');
                break;
            case WirecardCEE_QPay_PaymentType::CCARD_MOTO:
                $title = JText::_('VMPAYMENT_WIRECARDCEECHECKOUT_PAYMENTTYPE_CCARD_MOTO');
                break;
            case WirecardCEE_QPay_PaymentType::CCARD:
                $title = JText::_('VMPAYMENT_WIRECARDCEECHECKOUT_PAYMENTTYPE_CCARD');
                break;
            case WirecardCEE_QPay_PaymentType::MAESTRO:
                $title = JText::_('VMPAYMENT_WIRECARDCEECHECKOUT_PAYMENTTYPE_MAESTRO');
                break;
            case WirecardCEE_QPay_PaymentType::EPS:
                $title = JText::_('VMPAYMENT_WIRECARDCEECHECKOUT_PAYMENTTYPE_EPS');
                break;
            case WirecardCEE_QPay_PaymentType::IDL:
                $title = JText::_('VMPAYMENT_WIRECARDCEECHECKOUT_PAYMENTTYPE_IDL');
                break;
            case WirecardCEE_QPay_PaymentType::GIROPAY:
                $title = JText::_('VMPAYMENT_WIRECARDCEECHECKOUT_PAYMENTTYPE_GIROPAY');
                break;
            case WirecardCEE_QPay_PaymentType::SOFORTUEBERWEISUNG:
                $title = JText::_('VMPAYMENT_WIRECARDCEECHECKOUT_PAYMENTTYPE_SOFORTUEBERWEISUNG');
                break;
            case WirecardCEE_QPay_PaymentType::PBX:
                $title = JText::_('VMPAYMENT_WIRECARDCEECHECKOUT_PAYMENTTYPE_PBX');
                break;
            case WirecardCEE_QPay_PaymentType::PSC:
                $title = JText::_('VMPAYMENT_WIRECARDCEECHECKOUT_PAYMENTTYPE_PSC');
                break;
            case WirecardCEE_QPay_PaymentType::QUICK:
                $title = JText::_('VMPAYMENT_WIRECARDCEECHECKOUT_PAYMENTTYPE_QUICK');
                break;
            case WirecardCEE_QPay_PaymentType::PAYPAL:
                $title = JText::_('VMPAYMENT_WIRECARDCEECHECKOUT_PAYMENTTYPE_PAYPAL');
                break;
            case WirecardCEE_QPay_PaymentType::SEPADD:
                $title = JText::_('VMPAYMENT_WIRECARDCEECHECKOUT_PAYMENTTYPE_SEPA-DD');
                break;
            case WirecardCEE_QPay_PaymentType::TRUSTPAY:
                $title = JText::_('VMPAYMENT_WIRECARDCEECHECKOUT_PAYMENTTYPE_TRUSTPAY');
                break;
            case WirecardCEE_QPay_PaymentType::INVOICE:
                $title = JText::_('VMPAYMENT_WIRECARDCEECHECKOUT_PAYMENTTYPE_INVOICE');
                break;
            case WirecardCEE_QPay_PaymentType::INVOICE . 'B2B':
                $title = JText::_('VMPAYMENT_WIRECARDCEECHECKOUT_PAYMENTTYPE_INVOICEB2B');
                break;
            case WirecardCEE_QPay_PaymentType::INSTALLMENT:
                $title = JText::_('VMPAYMENT_WIRECARDCEECHECKOUT_PAYMENTTYPE_INSTALLMENT');
                break;
            case WirecardCEE_QPay_PaymentType::BMC:
                $title = JText::_('VMPAYMENT_WIRECARDCEECHECKOUT_PAYMENTTYPE_BANCONTACT_MISTERCASH');
                break;
            case WirecardCEE_QPay_PaymentType::P24:
                $title = JText::_('VMPAYMENT_WIRECARDCEECHECKOUT_PAYMENTTYPE_P24');
                break;
            case WirecardCEE_QPay_PaymentType::MONETA:
                $title = JText::_('VMPAYMENT_WIRECARDCEECHECKOUT_PAYMENTTYPE_MONETA');
                break;
            case WirecardCEE_QPay_PaymentType::POLI:
                $title = JText::_('VMPAYMENT_WIRECARDCEECHECKOUT_PAYMENTTYPE_POLI');
                break;
            case WirecardCEE_QPay_PaymentType::EKONTO:
                $title = JText::_('VMPAYMENT_WIRECARDCEECHECKOUT_PAYMENTTYPE_EKONTO');
                break;
            case WirecardCEE_QPay_PaymentType::TRUSTLY:
                $title = JText::_('VMPAYMENT_WIRECARDCEECHECKOUT_PAYMENTTYPE_TRUSTLY');
                break;
            case WirecardCEE_QPay_PaymentType::MPASS:
                $title = JText::_('VMPAYMENT_WIRECARDCEECHECKOUT_PAYMENTTYPE_MPASS');
                break;
            case WirecardCEE_QPay_PaymentType::SKRILLDIRECT:
                $title = JText::_('VMPAYMENT_WIRECARDCEECHECKOUT_PAYMENTTYPE_SKRILLDIRECT');
                break;
            case WirecardCEE_QPay_PaymentType::SKRILLWALLET:
                $title = JText::_('VMPAYMENT_WIRECARDCEECHECKOUT_PAYMENTTYPE_SKRILLWALLET');
                break;
            case WirecardCEE_QPay_PaymentType::EPAYBG:
                $title = JText::_('VMPAYMENT_WIRECARDCEECHECKOUT_PAYMENTTYPE_EPAY_BG');
                break;
            case WirecardCEE_QPay_PaymentType::TATRAPAY:
                $title = JText::_('VMPAYMENT_WIRECARDCEECHECKOUT_PAYMENTTYPE_TATRAPAY');
                break;
            case WirecardCEE_QPay_PaymentType::VOUCHER:
                $title = JText::_('VMPAYMENT_WIRECARDCEECHECKOUT_PAYMENTTYPE_VOUCHER');
                break;
            default:
                $title = htmlentities($value);
                break;
        }
        return $title;
    }

    /**
     * Fill additional shipping information
     *
     * @param WirecardCEE_Stdlib_ConsumerData $consumerData
     */
    protected function _setConsumerShippingInformation(WirecardCEE_Stdlib_ConsumerData $consumerData)
    {
        $order = $this->_getOrder();

        $shippingData = array_key_exists('ST', $order['details']) ? $order['details']['ST'] : $order['details']['BT'];

        $shippingAddress = new WirecardCEE_Stdlib_ConsumerData_Address(WirecardCEE_Stdlib_ConsumerData_Address::TYPE_SHIPPING);

        $countryCode = ShopFunctions::getCountryByID($shippingData->virtuemart_country_id, 'country_2_code');

        $shippingAddress->setFirstname($shippingData->first_name)
            ->setLastname($shippingData->last_name)
            ->setAddress1($shippingData->address_1)
            ->setAddress2($shippingData->address_2)
            ->setCity($shippingData->city)
            ->setZipCode($shippingData->zip)
            ->setCountry($countryCode)
            ->setPhone($shippingData->phone_1);

        if ($countryCode == 'US' || $countryCode == 'CA') {
            $shippingAddress->setState(ShopFunctions::getStateByID($shippingData->virtuemart_state_id, 'state_2_code'));
        } else {
            $shippingAddress->setState(ShopFunctions::getStateByID($shippingData->virtuemart_state_id, 'state_name'));
        }

        $consumerData->addAddressInformation($shippingAddress);
    }

    /**
     * Fill additional consumer billing information
     *
     * @param WirecardCEE_Stdlib_ConsumerData $consumerData
     */
    protected function _setConsumerBillingInformation(WirecardCEE_Stdlib_ConsumerData $consumerData)
    {
        $order = $this->_getOrder();
        $billingData = $order['details']['BT'];

        if (isset($billingData->company)) {
            $consumerData->setCompanyName($billingData->company);
        } else {
            $consumerData->setBirthDate(new \DateTime($billingData->birthday));
        }
        $consumerData->setEmail($billingData->email);

        $billingAddress = new WirecardCEE_Stdlib_ConsumerData_Address(WirecardCEE_Stdlib_ConsumerData_Address::TYPE_BILLING);

        $countryCode = ShopFunctions::getCountryByID($billingData->virtuemart_country_id, 'country_2_code');

        $billingAddress->setFirstname($billingData->first_name)
            ->setLastname($billingData->last_name)
            ->setAddress1($billingData->address_1)
            ->setAddress2($billingData->address_2)
            ->setCity($billingData->city)
            ->setZipCode($billingData->zip)
            ->setCountry($countryCode)
            ->setPhone($billingData->phone_1);

        if ($countryCode == 'US' || $countryCode == 'CA') {
            $billingAddress->setState(ShopFunctions::getStateByID($billingData->virtuemart_state_id, 'state_2_code'));
        } else {
            $billingAddress->setState(ShopFunctions::getStateByID($billingData->virtuemart_state_id, 'state_name'));
        }

        $consumerData->addAddressInformation($billingAddress);
    }

    /**
     * Add shopping basket data
     *
     * @param VirtueMartCart $cart
     * @return array BasketData
     */
    private function _generateBasketInformation(VirtueMartCart $cart)
    {
        $precision = 2;

        $basket = new \WirecardCEE_Stdlib_Basket();
        $basket->setCurrency($this->_getOrderCurrency());

        foreach ($cart->products as $pkey => $prow) {
            $priceWithTax = $prow->prices['basePriceWithTax'];
            if ($prow->prices['salesPriceWithDiscount'] > 0) {
                $priceWithTax = $prow->prices['salesPriceWithDiscount'];
            }

            $tax = round($priceWithTax - $prow->prices['product_price'], $precision - 1);
            $unitPrice = round($priceWithTax - $tax, $precision);

            $bitem = new \WirecardCEE_Stdlib_Basket_Item();
            $bitem->setDescription($prow->product_name);
            $bitem->setArticleNumber($prow->product_sku);
            $bitem->setUnitPrice(number_format($unitPrice, $precision, '.', ''));
            $bitem->setTax(number_format($tax * $prow->amount, $precision, '.', ''));
            $basket->addItem($bitem, (int)$prow->amount);
        }

        $bitem = new \WirecardCEE_Stdlib_Basket_Item();
        $bitem->setArticleNumber('shipping');
        $bitem->setUnitPrice(number_format($cart->cartPrices['shipmentValue'], $precision, '.', ''));
        $bitem->setTax(number_format($cart->cartPrices['shipmentTax'], $precision, '.', ''));
        $bitem->setDescription(strip_tags($cart->cartData['shipmentName']));
        $basket->addItem($bitem);

        return $basket->__toArray();
    }

    /**
     * Check whether invoice is allowed or not
     *
     * @param VirtueMartCart $cart
     * @return bool
     */
    protected function isInvoiceAllowed(VirtueMartCart $cart)
    {
        $currency = $this->_getCurrency($cart);

        if ($currency != 'EUR')
            return false;

        $billingAddress = $cart->BT;
        $shippingAddress = $cart->ST;

        if (!is_array($billingAddress)) {
            return false;
        }

        if (!array_key_exists('birthday', $billingAddress)) {
            return false;
        }

        $d1 = new DateTime($billingAddress['birthday']);
        $diff = $d1->diff(new DateTime);
        $customerAge = $diff->format('%y');

        if ($cart->ST) {
            $fields = array('virtuemart_country_id', 'company', 'first_name', 'last_name', 'address_1', 'address_2', 'zip', 'city');
            foreach ($fields as $f) {
                if ($billingAddress[$f] != $shippingAddress[$f])
                    return false;
            }
        }

        if ($customerAge < $this->_getInvoiceMinAge())
            return false;

        $prices = $cart->getCartPrices();
        $total = $prices['billTotal'];
        $basketSize = count($cart->products) + 1;

        if ($this->_getInvoiceMin() && $this->_getInvoiceMin() > $total)
            return false;

        if ($this->_getInvoiceMax() && $this->_getInvoiceMax() < $total)
            return false;

        if ($this->_getInvoiceMinBasketSize() && $this->_getInvoiceMinBasketSize() > $basketSize)
            return false;

        if ($this->_getInvoiceMaxBasketSize() && $this->_getInvoiceMaxBasketSize() <= $basketSize)
            return false;

        return true;
    }

    /**
     * Check whether invoiceb2b is allowed or not
     *
     * @param VirtueMartCart $cart
     * @return bool
     */
    protected function isInvoiceB2BAllowed(VirtueMartCart $cart)
    {
        $currency = $this->_getCurrency($cart);

        if ($currency != 'EUR')
            return false;

        $billingAddress = $cart->BT;
        $shippingAddress = $cart->ST;

        if (!is_array($billingAddress)) {
            return false;
        }

        if (!array_key_exists('company', $billingAddress)) {
            return false;
        }

        if (!strlen($billingAddress['company'])) {
            return false;
        }

        if ($cart->ST) {
            $fields = array('virtuemart_country_id', 'company', 'first_name', 'last_name', 'address_1', 'address_2', 'zip', 'city');
            foreach ($fields as $f) {
                if ($billingAddress[$f] != $shippingAddress[$f])
                    return false;
            }
        }

        $prices = $cart->getCartPrices();
        $total = $prices['billTotal'];
        $basketSize = count($cart->products) + 1;

        if ($this->_getInvoiceB2BMin() && $this->_getInvoiceB2BMin() > $total)
            return false;

        if ($this->_getInvoiceB2BMax() && $this->_getInvoiceB2BMax() < $total)
            return false;

        if ($this->_getInvoiceB2BMinBasketSize() && $this->_getInvoiceB2BMinBasketSize() > $basketSize)
            return false;

        if ($this->_getInvoiceB2BMaxBasketSize() && $this->_getInvoiceB2BMaxBasketSize() <= $basketSize)
            return false;

        return true;
    }

    /**
     * Check whether installment is allowed or not
     *
     * @param VirtueMartCart $cart
     * @return bool
     */
    protected function isInstallmentAllowed($cart)
    {
        $currency = $this->_getCurrency($cart);

        if ($currency != 'EUR')
            return false;

        $billingAddress = $cart->BT;
        $shippingAddress = $cart->ST;

        if (!is_array($billingAddress)) {
            return false;
        }

        if (!array_key_exists('birthday', $billingAddress)) {
            return false;
        }

        $d1 = new DateTime($billingAddress['birthday']);
        $diff = $d1->diff(new DateTime);
        $customerAge = $diff->format('%y');

        if ($cart->ST) {
            $fields = array('virtuemart_country_id', 'company', 'first_name', 'last_name', 'address_1', 'address_2', 'zip', 'city');
            foreach ($fields as $f) {
                if ($billingAddress[$f] != $shippingAddress[$f])
                    return false;
            }

        }

        if ($customerAge < $this->_getInstallmentMinAge())
            return false;

        $prices = $cart->getCartPrices();
        $total = $prices['billTotal'];
        $basketSize = count($cart->products) + 1;

        if ($this->_getInstallmentMin() && $this->_getInstallmentMin() > $total)
            return false;

        if ($this->_getInstallmentMax() && $this->_getInstallmentMax() < $total)
            return false;

        if ($this->_getInstallmentMinBasketSize() && $this->_getInstallmentMinBasketSize() > $basketSize)
            return false;

        if ($this->_getInstallmentMaxBasketSize() && $this->_getInstallmentMaxBasketSize() <= $basketSize)
            return false;

        return true;
    }

    /*
     * getter/setter
     */
    protected function _setMethod($method)
    {
        $this->_method = $method;
    }

    protected function _getMethod()
    {
        return $this->_method;
    }

    protected function _setOrder($order)
    {
        $this->_order = $order;
    }

    protected function _getOrder()
    {
        return $this->_order;
    }

    protected function _getAmount()
    {
        // XXX ToDo
        // Euro maximal 2 Nachkommastellen (100 Cent = 1 Euro)
        // US Dollar maximal 2 Nachkommastellen (100 Cent = 1 Euro)
        // Yen keine Nachkommastellen
        // Libyan Dinar Maximal 3 N
        $order = $this->_getOrder();
        return sprintf('%.2f', $order['details']['BT']->order_total);
    }

    protected function _getCustomerId()
    {
        $customerIdArray = array(
            'production' => trim($this->_getMethod()->customer_id),
            'demo' => self::WCP_CUSTOMER_ID_DEMO,
            'test' => self::WCP_CUSTOMER_ID_TEST,
            'test3d' => self::WCP_CUSTOMER_ID_TEST3D
        );

        return $customerIdArray[$this->_getMethod()->configuration];
    }

    protected function _getShopId()
    {
        $shopIdArray = array(
            'production' => $this->_getMethod()->shop_id,
            'demo' => self::WCP_SHOP_ID_DEMO,
            'test' => self::WCP_SHOP_ID_TEST,
            'test3d' => self::WCP_SHOP_ID_TEST3D
        );

        return $shopIdArray[$this->_getMethod()->configuration];
    }

    protected function _getSecret()
    {
        $secretArray = array(
            'production' => trim($this->_getMethod()->secret),
            'demo' => self::WCP_SECRET_DEMO,
            'test' => self::WCP_SECRET_TEST,
            'test3d' => self::WCP_SECRET_TEST3D
        );

        return $secretArray[$this->_getMethod()->configuration];
    }

    protected function _getBackendPassword()
    {
        $backendPasswordArray = array(
            'production' => $this->_getMethod()->backend_password,
            'demo' => self::WCP_BACKEND_PASSWORD_DEMO,
            'test' => self::WCP_BACKEND_PASSWORD_TEST,
            'test3d' => self::WCP_BACKEND_PASSWORD_TEST3D
        );

        return $backendPasswordArray[$this->_getMethod()->configuration];
    }

    protected function _getLanguage()
    {
        $lang = JFactory::getLanguage();
        $languages = JLanguageHelper::getLanguages();

        foreach ($languages as $language) {
            if ($language->lang_code == $lang->getTag())
                return $language->sef;
        }
    }

    protected function _getCurrency(VirtueMartCart $cart)
    {
        $currencyModel = VmModel::getModel('Currency');
        $currency = $currencyModel->getCurrency($cart->pricesCurrency);

        return $currency->currency_code_3;
    }

    protected function _getOrderCurrency()
    {
        $order = $this->_getOrder();
        $currencyModel = VmModel::getModel('Currency');
        $currency = $currencyModel->getCurrency($order['details']['BT']->order_currency);

        return $currency->currency_code_3;
    }

    protected function _getOrderDescription()
    {
        $order = $this->_getOrder();
        $orderDescription = 'CID: ' . $order['details']['BT']->virtuemart_user_id . ' OID: ' . $order['details']['BT']->virtuemart_order_id;
        return $orderDescription;
    }

    private function _getBackgroundColor()
    {
        return $this->_getMethod()->background_color;
    }

    private function _getRatepayInstallmentInvoiceArray()
    {
        $paymentTypes = array();
        if ($this->_getMethod()->invoice_provider !== self::WCP_SERVICE_PROVIDER_PAYOLUTION) {
            array_push($paymentTypes, WirecardCEE_QPay_PaymentType::INVOICE);
        }
        if ($this->_getMethod()->installment_provider !== self::WCP_SERVICE_PROVIDER_PAYOLUTION) {
            array_push($paymentTypes, WirecardCEE_QPay_PaymentType::INSTALLMENT);
        }

        return $paymentTypes;
    }

    protected function _getInvoiceMin()
    {
        return (int)$this->_getMethod()->invoice_min;
    }

    protected function _getInvoiceMax()
    {
        return (int)$this->_getMethod()->invoice_max;
    }

    protected function _getInvoiceMinBasketSize()
    {
        return (int)$this->_getMethod()->invoice_min_basket_size;
    }

    protected function _getInvoiceMaxBasketSize()
    {
        return (int)$this->_getMethod()->invoice_max_basket_size;
    }

    private function _getInvoiceFinancialInstitution()
    {
        return $this->_getMethod()->invoice_provider;
    }

    private function _getInvoiceMinAge()
    {
        if ($this->_getInvoiceFinancialInstitution() == self::WCP_SERVICE_PROVIDER_PAYOLUTION) {
            return self::INVOICE_INSTALLMENT_MIN_AGE;
        }
        return (int)$this->_getMethod()->invoice_min_age;
    }

    protected function _getInvoiceB2BMin()
    {
        return (int)$this->_getMethod()->invoiceb2b_min;
    }

    protected function _getInvoiceB2BMax()
    {
        return (int)$this->_getMethod()->invoiceb2b_max;
    }

    protected function _getInvoiceB2BMinBasketSize()
    {
        return (int)$this->_getMethod()->invoiceb2b_min_basket_size;
    }

    protected function _getInvoiceB2BMaxBasketSize()
    {
        return (int)$this->_getMethod()->invoiceb2b_max_basket_size;
    }

    protected function _getInstallmentMin()
    {
        return (int)$this->_getMethod()->installment_min;
    }

    protected function _getInstallmentMax()
    {
        return (int)$this->_getMethod()->installment_max;
    }

    protected function _getInstallmentMinBasketSize()
    {
        return (int)$this->_getMethod()->installment_min_basket_size;
    }

    protected function _getInstallmentMaxBasketSize()
    {
        return (int)$this->_getMethod()->installment_max_basket_size;
    }

    private function _getInstallmentFinancialInstitution()
    {
        return $this->_getMethod()->installment_provider;
    }

    private function _getInstallmentMinAge()
    {
        if ($this->_getInstallmentFinancialInstitution() == self::WCP_SERVICE_PROVIDER_PAYOLUTION) {
            return self::INVOICE_INSTALLMENT_MIN_AGE;
        }
        return (int)$this->_getMethod()->installment_min_age;
    }

    protected function _getDisplayText()
    {
        return $this->_getMethod()->display_text;
    }

    protected function _getImageUrl()
    {
        return $this->_getMethod()->image_url;
    }

    protected function _getServiceUrl()
    {
        return $this->_getMethod()->service_url;
    }

    protected function _getMaxRetries()
    {
        return (int)$this->_getMethod()->max_retries;
    }

    protected function _getAutoDeposit($paymentType)
    {
        if ($paymentType == strtolower(WirecardCEE_Stdlib_PaymentTypeAbstract::INVOICE) || $paymentType == strtolower(WirecardCEE_Stdlib_PaymentTypeAbstract::INSTALLMENT)) {
            return false;
        }
        return (bool)$this->_getMethod()->auto_deposit;
    }

    protected function _getStatusPending()
    {
        return $this->_getMethod()->status_pending;
    }

    protected function _getStatusSuccess()
    {
        return $this->_getMethod()->status_success;
    }

    protected function _getStatusCancel()
    {
        return 'X';
    }

    protected function _getStatusFailed()
    {
        return $this->_getMethod()->status_failed;
    }

    protected function _getWindowName()
    {
        return $this->_useIFrame() ? self::$WINDOW_NAME : null;
    }

    protected function _useIFrame()
    {
        if ($this->_useMobileDetect() && $this->_getClientDevice() !== 'desktop') {
            return false;
        }
        return (bool)$this->_getMethod()->use_iframe;
    }

    protected function _sendShippingInformation()
    {
        return (bool)$this->_getMethod()->send_shipping_data;
    }

    protected function _sendBillingInformation()
    {
        return (bool)$this->_getMethod()->send_billing_data;
    }

    protected function _sendBasketInformation()
    {
        return (bool)$this->_getMethod()->send_basket_information;
    }

    protected function _sendConfirmationEmail()
    {
        return (bool)$this->_getMethod()->send_confirmation_email;
    }

    private function _sendLayout()
    {
        return (bool)strlen($this->_getMethod()->layout);
    }

    private function _useMobileDetect()
    {
        return (bool)$this->_getMethod()->mobile_detect;
    }

    protected function _getCustomerStatement($paymentType, $prefix = '')
    {
        if (!strlen($prefix)) {
            $prefix = 'Web Shop';
        }
        $prefix = substr($prefix, 0, 9);

        if ($paymentType == strtolower(WirecardCEE_Stdlib_PaymentTypeAbstract::POLI)) {
            return $prefix;
        }

        return sprintf('%s Id:%s', $prefix, $this->_generateUniqString(10));
    }

    private function _getConfirmMail()
    {
        $vendorId = 1;
        $vendorModel = VmModel::getModel('vendor');
        return $vendorModel->getVendorEmail($vendorId);
    }

    private function _generateUniqString($length = 10)
    {
        $tid = '';

        $alphabet = "023456789abcdefghikmnopqrstuvwxyzABCDEFGHIKMNOPQRSTUVWXYZ";

        for ($i = 0; $i < $length; $i++) {
            $c = substr($alphabet, mt_rand(0, strlen($alphabet) - 1), 1);

            if ((($i % 2) == 0) && !is_numeric($c)) {
                $i--;
                continue;
            }
            if ((($i % 2) == 1) && is_numeric($c)) {
                $i--;
                continue;
            }

            $alphabet = str_replace($c, '', $alphabet);
            $tid .= $c;
        }

        return $tid;
    }

    protected function _getVendor()
    {
        $order = $this->_getOrder();
        $vendorModel = VmModel::getModel('Vendor');
        $vendorModel->setId($order['details']['BT']->virtuemart_vendor_id);
        $vendor = $vendorModel->getVendor();
        return $vendor->vendor_name;
    }

    protected function _getDuplicateRequestCheck()
    {
        return (bool)$this->_getMethod()->duplicate_request_check;
    }

    protected function _getLayout()
    {
        return $this->_getMethod()->layout;
    }

    private function _getPayolutionTerms()
    {
        return (bool)$this->_getMethod()->payolution_terms;
    }

    private function _getPayolutionMid()
    {
        return $this->_getMethod()->payolution_mid;
    }

    private function _getPayolutionConsentText()
    {
        $text = JText::_('VMPAYMENT_WIRECARDCEECHECKOUT_PAYOLUTION_CONSENT');
        $payolutionLink = JText::_('VMPAYMENT_WIRECARDCEECHECKOUT_PAYOLUTION_CONSENT_LINK');

        if (strlen($this->_getPayolutionMid()) > 0) {
            $payolutionLink = sprintf('<a href="https://payment.payolution.com/payolution-payment/infoport/dataprivacyconsent?mId=%s" target="_blank">%s</a>',
                $this->_getPayolutionMid(), JText::_('VMPAYMENT_WIRECARDCEECHECKOUT_PAYOLUTION_CONSENT_LINK'));
        }

        return sprintf($text, $payolutionLink);
    }

    function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter)
    {
        return $this->onCheckAutomaticSelected($cart, $cart_prices, $paymentCounter);
    }

    /*
     * getting client device type through user agent
     *
     * @return string
     */
    protected function _getClientDevice()
    {
        $detect = new \WirecardCEE_QPay_MobileDetect();

        if ($detect->isTablet()) {
            $layout = 'tablet';
        } elseif ($detect->isMobile()) {
            $layout = 'smartphone';
        } else {
            $layout = 'desktop';
        }
        return $layout;
    }
}