<?php
/**
 * Shop System Plugins
 * - Terms of use can be found under
 * https://guides.qenta.com/shop_plugins:info
 * - License can be found under:
 * https://github.com/qenta-cee/virtuemart3-qcp/blob/master/LICENSE
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

require_once 'autoload.php';


class plgVmPaymentqentaceecheckout extends vmPSPlugin
{
	public static $_this = false;

	protected static $WINDOW_NAME = 'QentaCEECheckoutFrame';
	protected static $PLUGIN_NAME = 'VirtueMart2_CheckoutPage';
	protected static $PLUGIN_VERSION = '2.0.0';

	protected $_method;
	protected $_order;

	const QCP_CUSTOMER_ID_DEMO = 'D200001';
	const QCP_SHOP_ID_DEMO = '';
	const QCP_SECRET_DEMO = 'B8AKTPWBRMNBV455FG6M2DANE99WU2';
	const QCP_BACKEND_PASSWORD_DEMO = 'jcv45z';
	const QCP_CUSTOMER_ID_TEST = 'D200411';
	const QCP_SHOP_ID_TEST = '';
	const QCP_SECRET_TEST = 'CHCSH7UGHVVX2P7EHDHSY4T2S4CGYK4QBE4M5YUUG2ND5BEZWNRZW5EJYVJQ';
	const QCP_BACKEND_PASSWORD_TEST = '2g4f9q2m';
	const QCP_CUSTOMER_ID_TEST3D = 'D200411';
	const QCP_SHOP_ID_TEST3D = '3D';
	const QCP_SECRET_TEST3D = 'DP4TMTPQQWFJW34647RM798E9A5X7E8ATP462Z4VGZK53YEJ3JWXS98B9P4F';
	const QCP_BACKEND_PASSWORD_TEST3D = '2g4f9q2m';

	const QCP_SERVICE_PROVIDER_PAYOLUTION = 'payolution';

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
			$client = new QentaCEE\QPay\FrontendClient(array(
				'CUSTOMER_ID' => trim($data['params']['customer_id'] ? $data['params']['customer_id'] : ' '),
				'SHOP_ID' => $data['params']['shop_id'],
				'SECRET' => trim($data['params']['secret'] ? $data['params']['secret'] : ' '),
				'LANGUAGE' => $this->_getLanguage()
			));

			$returnUrl = JROUTE::_(JURI::root());

			$consumerData = new QentaCEE\Stdlib\ConsumerData();
			$consumerData->setUserAgent($_SERVER['HTTP_USER_AGENT'])
						 ->setIpAddress($_SERVER['REMOTE_ADDR']);

			$client->setAmount(0.01)
				   ->setCurrency('EUR')
				   ->setPaymentType(QentaCEE\QPay\PaymentType::CCARD)
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
				vmAdminInfo(vmText::_('VMPAYMENT_QENTACEECHECKOUT_CHECK_CONFIGURATION_OK'));
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

			$mailer->setSubject(vmText::_('VMPAYMENT_QENTACEECHECKOUT_SUPPORT_REQUEST'));
			$mailer->setBody($support_message);

			if ($mailer->Send()) {
				vmAdminInfo(vmText::_('VMPAYMENT_QENTACEECHECKOUT_SUPPORT_SEND_OK'));
			}
		}
	}

	public function getVmPluginCreateTableSQL()
	{
		return $this->createTableSQL('Payment Qenta Table');
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

			// qenta specific data
			'qenta_order_number' => 'varchar(50)',
			'qenta_gateway_ref' => 'varchar(50)',
			'qenta_response_raw' => 'text');

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
			$msg = vmText::_('VMPAYMENT_QENTACEECHECKOUT_INITIATE_PAYMENT_ERROR');

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
			$html = sprintf('<iframe src="%s" width="100%%" height="900" name="%s" border="0" frameborder="0"></iframe>',
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

		$return = QentaCEE\QPay\ReturnFactory::getInstance($_POST, $this->_getSecret($method));

		if (!$return->validate()) {
			$paymentResponse = JText::_('VMPAYMENT_QENTACEECHECKOUT_INVALID_RESPONSE');
			$mainframe = JFactory::getApplication();
			$mainframe->redirect(JRoute::_('index.php/cart'), JText::_($paymentResponse));
		}

		$paymentState = $input->get('paymentState');
		if ($paymentState == QentaCEE\QPay\ReturnFactory::STATE_PENDING ||
			$paymentState == QentaCEE\QPay\ReturnFactory::STATE_SUCCESS
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

		if ($paymentState == QentaCEE\QPay\ReturnFactory::STATE_PENDING) {
			$modelOrder = VmModel::getModel('orders');
			$order = array();
			$order['order_status'] = $this->_getStatusPending();
			$order['comments'] = JText::sprintf('VMPAYMENT_QENTACEECHECKOUT_PAYMENT_STATUS_PENDING', $order_number);
			$order['customer_notified'] = 0;
			$modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, true);

			$paymentResponse = JText::_('VMPAYMENT_QENTACEECHECKOUT_PAYMENT_PENDING');
			return true;
		}

		if ($paymentState == QentaCEE\QPay\ReturnFactory::STATE_FAILURE) {
			$modelOrder = VmModel::getModel('orders');
			$order = array();
			$order['order_status'] = $this->_getStatusFailed();
			$order['comments'] = JText::sprintf('VMPAYMENT_QENTACEECHECKOUT_PAYMENT_STATUS_FAILED', $order_number, $return->getErrors()->getMessage());
			$order['customer_notified'] = 0;

			if ($this->_getMethod()->keep_unsuccessful_orders) {
				$modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, true);
			} else {
				$modelOrder->remove(array($virtuemart_order_id));
			}

			$paymentResponse = JText::_('VMPAYMENT_QENTACEECHECKOUT_PAYMENT_FAILED');

			$mainframe = JFactory::getApplication();
			$mainframe->redirect(JRoute::_('index.php/cart'), JText::_($paymentResponse));
		}

		// C ... completed
		if ($order['details']['BT']->order_status == 'C') {
			$paymentResponse = JText::_('VMPAYMENT_QENTACEECHECKOUT_PAYMENT_CONFIRMED');
		}

		$session = JFactory::getSession();
		$data = $session->get('QENTACEECHECKOUT', 0, 'vm');
		if (!empty($data)) {
			$sessionQenta = unserialize($data);
			$sessionQenta->consentInvoice = 'off';
			$sessionQenta->consentInvoiceB2B = 'off';
			$sessionQenta->consentInstallment = 'off';
			$session->set('QENTACEECHECKOUT', serialize($sessionQenta), 'vm');
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
		$dbValues['qenta_order_number'] = $input->get('orderNumber');
		$dbValues['payment_name'] = parent::renderPluginName($method);
		$dbValues['payment_order_total'] = $this->_getAmount($order);
		$dbValues['payment_currency'] = $this->_getOrderCurrency();

		$dbValues['qenta_gateway_ref'] = $input->get('gatewayReferenceNumber');
		$dbValues['qenta_response_raw'] = serialize($_POST);
		$this->storePSPluginInternalData($dbValues, 'virtuemart_order_id', false);

		$modelOrder = VmModel::getModel('orders');
		$order = array();
		$order['customer_notified'] = 1;

		$this->logInfo(__FUNCTION__ . print_r($_POST, true), 'message');

		$message = null;
		try {
			$return = QentaCEE\QPay\ReturnFactory::getInstance($_POST, $this->_getSecret($method));
			if (!$return->validate()) {
				$order['order_status'] = $this->_getStatusFailed();
				$message = $order['comments'] = JText::_('VMPAYMENT_QENTACEECHECKOUT_INVALID_RESPONSE');
			}

			$order['order_status'] = $method->status_pending;
			$paymentState = $input->get('paymentState');
			switch ($paymentState) {
				case QentaCEE\QPay\ReturnFactory::STATE_SUCCESS:
					$order['order_status'] = $this->_getStatusSuccess();
					$order['comments'] = JText::_('VMPAYMENT_QENTACEECHECKOUT_PAYMENT_CONFIRMED');
					break;

				case QentaCEE\QPay\ReturnFactory::STATE_PENDING:
					$order['order_status'] = $this->_getStatusPending();
					$order['comments'] = JText::_('VMPAYMENT_QENTACEECHECKOUT_PAYMENT_PENDING');
					break;

				case QentaCEE\QPay\ReturnFactory::STATE_CANCEL:
					$order['order_status'] = $this->_getStatusCancel();
					$order['comments'] = JText::_('VMPAYMENT_QENTACEECHECKOUT_PAYMENT_CANCELLED');
					break;

				case QentaCEE\QPay\ReturnFactory::STATE_FAILURE:
					$order['order_status'] = $this->_getStatusFailed();
					$order['comments'] = JText::_('VMPAYMENT_QENTACEECHECKOUT_PAYMENT_FAILED');
					break;

				default:
					break;
			}
		} catch (Exception $e) {
			$this->logInfo(__FUNCTION__ . $e->getMessage(), 'error');
			$order['order_status'] = $this->_getStatusFailed();
			$order['comments'] = JText::_('VMPAYMENT_QENTACEECHECKOUT_PAYMENT_FAILED');
			$message = $e->getMessage();
		}

		if (!$this->_getMethod()->keep_unsuccessful_orders && ($order['order_status'] == $this->_getStatusFailed() || $order['order_status'] == $this->_getStatusCancel())) {
			$modelOrder->remove(array($virtuemart_order_id));
		} else {
			$modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, true);
		}

		echo QentaCEE\QPay\ReturnFactory::generateConfirmResponseString($message, true);
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

		VmInfo(Jtext::_('VMPAYMENT_QENTACEECHECKOUT_PAYMENT_CANCELLED'));
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
		$paymenttype = $input->get('qenta_paymenttype');
		$birthDay = $input->get('qcp_day');
		$birthMonth = $input->get('qcp_month');
		$birthYear = $input->get('qcp_year');
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

		$session = JFactory::getSession();
		$sessionQenta = new stdClass();
		$sessionQenta->paymenttype = $paymenttype;
		$sessionQenta->consentInvoice = $consentInvoice;
		$sessionQenta->consentInvoiceB2B = $consentInvoiceB2B;
		$sessionQenta->consentInstallment = $consentInstallment;
		$sessionQenta->birthDay = $birthDay;
		$sessionQenta->birthMonth = $birthMonth;
		$sessionQenta->birthYear = $birthYear;
		$session->set('QENTACEECHECKOUT', serialize($sessionQenta), 'vm');

		if (!$found) {
			$msg .= JText::_('VMPAYMENT_QENTACEECHECKOUT_ERROR_PAYMENTTYPE');
			return false;
		}

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

		$data = vRequest::getPost();
		if(array_key_exists('qenta_paymenttype', $data)) {
			$this->changePaymentTypeAjax($data);
		}

		if (!$this->selectedThisByMethodId($cart->virtuemart_paymentmethod_id)) {
			return null; // Another method was selected, do nothing
		}

		$method = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id);

		$this->_setMethod($method);

		$session = JFactory::getSession();
		$data = $session->get('QENTACEECHECKOUT', 0, 'vm');
		if (empty($data))
			return false;

		$sessionQenta = unserialize($data);
		$found = !strlen($sessionQenta->paymenttype);

		foreach ($this->_getEnabledPaymentTypes() as $m) {
			if (strtolower($m['value']) == $sessionQenta->paymenttype) {
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
	 * @param				$cart_prices_name
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
		$data = unserialize($paymentTable->qenta_response_raw);

		$blacklist = array('vmOrderNumber', 'vmPaymentMethodId', 'responseFingerprint', 'responseFingerprintOrder');
		$html = '<table class="adminlist">' . "\n";
		$html .= $this->getHtmlHeaderBE();
		$html .= $this->getHtmlRowBE('QENTACEECHECKOUT_NAME', $paymentTable->payment_name);
		foreach ($data as $key => $value) {
			if (in_array($key, $blacklist))
				continue;
			$html .= str_replace("QENTACEECHECKOUT_", "", $this->getHtmlRowBE("QENTACEECHECKOUT_$key", $value));
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
	 * @author QENTA Payment CEE
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
	#						PROTECTED METHODS						   #
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
		$data = $session->get('QENTACEECHECKOUT', 0, 'vm');
		if (empty($data) || $selectedPlugin != $plugin->$pluginmethod_id ) {
			$paymenttype_selected = null;
			$birthDay = '0';
			$birthMonth = '0';
			$birthYear = '0';
		} else {
			$sessionQenta = unserialize($data);
			$paymenttype_selected = $sessionQenta->paymenttype;
			$birthDay = $sessionQenta->birthDay;
			$birthMonth = $sessionQenta->birthMonth;
			$birthYear = $sessionQenta->birthYear;
		}
		$ratepay = "";
		if (((int)$this->_getMethod()->paymenttype_invoice == 1 && $this->_getInvoiceFinancialInstitution() == "ratepay") ||
			((int)$this->_getMethod()->paymenttype_installment == 1 && $this->_getInstallmentFinancialInstitution() == "ratepay")) {
			$customer_id = $this->_getCustomerId();
			if (isset($_SESSION['qcp-consumerDeviceId'])) {
				$consumerDeviceId = $_SESSION['qcp-consumerDeviceId'];
			} else {
				$timestamp = microtime();
				$consumerDeviceId = md5( $customer_id . "_" . $timestamp );
				$_SESSION['qcp-consumerDeviceId'] = $consumerDeviceId;
			}

			$ratepay = '<script language="JavaScript">var di = {t:"' . $consumerDeviceId . '",v:"WDWL",l:"Checkout"};</script>';
			$ratepay .= '<script type="text/javascript" src="//d.ratepay.com/' . $consumerDeviceId . '/di.js"></script>';
			$ratepay .= '<noscript><link rel="stylesheet" type="text/css" href="//d.ratepay.com/di.css?t=' . $consumerDeviceId . '&v=WDWL&l=Checkout"></noscript>';
			$ratepay .= '<object type="application/x-shockwave-flash" data="//d.ratepay.com/WDWL/c.swf" width="0" height="0"><param name="movie" value="//d.ratepay.com/WDWL/c.swf" /><param name="flashvars" value="t=' . $consumerDeviceId . '&v=WDWL"/><param name="AllowScriptAccess" value="always"/></object>';
		}

		$html = $this->renderByLayout('displaypayment', array(
			'paymenttypes' => $this->_getEnabledPaymentTypes(),
			'paymentmethod_id' => $plugin->$pluginmethod_id,
			'paymenttype_selected' => $paymenttype_selected,
			'birth_day' => $birthDay,
			'birth_month' => $birthMonth,
			'birth_year' => $birthYear,
			'ratepay_script' => $ratepay
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

			$client = new QentaCEE\QPay\FrontendClient(array(
				'CUSTOMER_ID' => $this->_getCustomerId(),
				'SHOP_ID' => $this->_getShopId(),
				'SECRET' => $this->_getSecret(),
				'LANGUAGE' => $this->_getLanguage()
			));

			$session = JFactory::getSession();
			$data = $session->get('QENTACEECHECKOUT', null, 'vm');
			$sessionQenta = unserialize($data);

			/**
			 * If only one payment plugin is published, then selection of payment methods within QPay checkout page
			 * is not possible. Thus a default has to be used.
			 */
			if(!$sessionQenta->paymenttype) {
				$sessionQenta->paymenttype = 'SELECT';
			}

			$paymentType = strtoupper($sessionQenta->paymenttype);

			if ($paymentType == QentaCEE\QPay\PaymentType::INVOICE . 'B2B') {
				$paymentType = QentaCEE\QPay\PaymentType::INVOICE;
			}

			// consumer data (IP and User agent) are mandatory!
			$consumerData = new QentaCEE\Stdlib\ConsumerData();
			$consumerData->setUserAgent($_SERVER['HTTP_USER_AGENT'])
						 ->setIpAddress($_SERVER['REMOTE_ADDR']);

			if ($this->_sendShippingInformation()
				|| ($paymentType == QentaCEE\QPay\PaymentType::INVOICE && $this->_getMethod()->invoice_provider != 'payolution')
				|| ($paymentType == QentaCEE\QPay\PaymentType::INSTALLMENT && $this->_getMethod()->installment_provider != 'payolution')
			) {
				$this->_setConsumerShippingInformation($consumerData);
			}

			if ($this->_sendBillingInformation() || in_array($paymentType, array(QentaCEE\QPay\PaymentType::INVOICE, QentaCEE\QPay\PaymentType::INSTALLMENT))) {
				$this->_setConsumerBillingInformation($consumerData);
			}

			if ($this->_sendBasketInformation()
				|| ($paymentType == QentaCEE\QPay\PaymentType::INVOICE && $this->_getMethod()->invoice_provider != 'payolution')
				|| ($paymentType == QentaCEE\QPay\PaymentType::INSTALLMENT && $this->_getMethod()->installment_provider != 'payolution')
			) {
				$client->setBasket($this->_generateBasketInformation($cart));
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

			$version = QentaCEE\QPay\FrontendClient::generatePluginVersion(
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

			if ( isset( $_SESSION['qcp-consumerDeviceId'] ) ){
				$client->consumerDeviceId = $_SESSION['qcp-consumerDeviceId'];
				unset( $_SESSION['qcp-consumerDeviceId'] );
			}

			if ($paymentType == QentaCEE\QPay\PaymentType::MASTERPASS) {
				$client->setShippingProfile('NO_SHIPPING');
			}

			if ($paymentType == QentaCEE\QPay\PaymentType::IDL) {
				if (isset($_POST['financialInstitution_idl'])) {
					$client->setFinancialInstitution($_POST['financialInstitution_idl']);
				} else {
					$client->setFinancialInstitution($sessionQenta->additional["financialInstitution_idl"]);
				}
			} else if ($paymentType == QentaCEE\QPay\PaymentType::EPS) {
				if (isset($_POST['financialInstitution_eps'])) {
					$client->setFinancialInstitution($_POST['financialInstitution_eps']);
				} else {
					$client->setFinancialInstitution($sessionQenta->additional["financialInstitution_eps"]);
				}
			}

			if (array_key_exists('ST', $order['details'])) {
				$client->createConsumerMerchantCrmId($order['details']['ST']->email);
			}
			else {
				$client->createConsumerMerchantCrmId($order['details']['BT']->email);
			}

			if ($this->_sendConfirmationEmail()) {
				$client->setConfirmMail($this->_getConfirmMail());
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
		$sessionQenta = null;
		$session = JFactory::getSession();
		$data = $session->get('QENTACEECHECKOUT', 0, 'vm');
		if (!empty($data)) {
			$sessionQenta = unserialize($data);
		}

		$cart = VirtueMartCart::getCart();
		$paymentTypes = array();
		if ((int)$this->_getMethod()->paymenttype_select == 1) {
			$paymentTypes[1]['title'] = $this->_getPaymentTypeName(QentaCEE\QPay\PaymentType::SELECT);
			$paymentTypes[1]['value'] = QentaCEE\QPay\PaymentType::SELECT;
		}
		if ((int)$this->_getMethod()->paymenttype_ccard == 1) {
			$paymentTypes[2]['image'] = strtolower(QentaCEE\QPay\PaymentType::CCARD);
			$paymentTypes[2]['title'] = $this->_getPaymentTypeName(QentaCEE\QPay\PaymentType::CCARD);
			$paymentTypes[2]['value'] = QentaCEE\QPay\PaymentType::CCARD;
		}
		if ((int)$this->_getMethod()->paymenttype_ccard_moto == 1) {
			$paymentTypes[3]['image'] = strtolower(QentaCEE\QPay\PaymentType::CCARD_MOTO);
			$paymentTypes[3]['title'] = $this->_getPaymentTypeName(QentaCEE\QPay\PaymentType::CCARD_MOTO);
			$paymentTypes[3]['value'] = QentaCEE\QPay\PaymentType::CCARD_MOTO;
		}
		if ((int)$this->_getMethod()->paymenttype_maestro == 1) {
			$paymentTypes[4]['image'] = strtolower(QentaCEE\QPay\PaymentType::MAESTRO);
			$paymentTypes[4]['title'] = $this->_getPaymentTypeName(QentaCEE\QPay\PaymentType::MAESTRO);
			$paymentTypes[4]['value'] = QentaCEE\QPay\PaymentType::MAESTRO;
		}
		if ((int)$this->_getMethod()->paymenttype_bancontact_mistercash == 1) {
			$paymentTypes[5]['image'] = strtolower(QentaCEE\QPay\PaymentType::BMC);
			$paymentTypes[5]['title'] = $this->_getPaymentTypeName(QentaCEE\QPay\PaymentType::BMC);
			$paymentTypes[5]['value'] = QentaCEE\QPay\PaymentType::BMC;
		}
		if ((int)$this->_getMethod()->paymenttype_ekonto == 1) {
			$paymentTypes[6]['image'] = strtolower(QentaCEE\QPay\PaymentType::EKONTO);
			$paymentTypes[6]['title'] = $this->_getPaymentTypeName(QentaCEE\QPay\PaymentType::EKONTO);
			$paymentTypes[6]['value'] = QentaCEE\QPay\PaymentType::EKONTO;
		}
		if ((int)$this->_getMethod()->paymenttype_eps == 1) {
			$paymentTypes[7]['image'] = strtolower(QentaCEE\QPay\PaymentType::EPS);
			$paymentTypes[7]['title'] = $this->_getPaymentTypeName(QentaCEE\QPay\PaymentType::EPS);
			$paymentTypes[7]['value'] = QentaCEE\QPay\PaymentType::EPS;
			$paymentTypes[7]['financial_inst'] = QentaCEE\QPay\PaymentType::getFinancialInstitutions('EPS');
		}
		if ((int)$this->_getMethod()->paymenttype_giropay == 1) {
			$paymentTypes[8]['image'] = strtolower(QentaCEE\QPay\PaymentType::GIROPAY);
			$paymentTypes[8]['title'] = $this->_getPaymentTypeName(QentaCEE\QPay\PaymentType::GIROPAY);
			$paymentTypes[8]['value'] = QentaCEE\QPay\PaymentType::GIROPAY;
		}
		if ((int)$this->_getMethod()->paymenttype_idl == 1) {
			$paymentTypes[9]['image'] = strtolower(QentaCEE\QPay\PaymentType::IDL);
			$paymentTypes[9]['title'] = $this->_getPaymentTypeName(QentaCEE\QPay\PaymentType::IDL);
			$paymentTypes[9]['value'] = QentaCEE\QPay\PaymentType::IDL;
			$paymentTypes[9]['financial_inst'] = QentaCEE\QPay\PaymentType::getFinancialInstitutions('IDL');
		}
		if ((int)$this->_getMethod()->paymenttype_poli == 1) {
			$paymentTypes[10]['image'] = strtolower(QentaCEE\QPay\PaymentType::POLI);
			$paymentTypes[10]['title'] = $this->_getPaymentTypeName(QentaCEE\QPay\PaymentType::POLI);
			$paymentTypes[10]['value'] = QentaCEE\QPay\PaymentType::POLI;
		}
		if ((int)$this->_getMethod()->paymenttype_p24 == 1) {
			$paymentTypes[11]['image'] = strtolower(QentaCEE\QPay\PaymentType::P24);
			$paymentTypes[11]['title'] = $this->_getPaymentTypeName(QentaCEE\QPay\PaymentType::P24);
			$paymentTypes[11]['value'] = QentaCEE\QPay\PaymentType::P24;
		}
		if ((int)$this->_getMethod()->paymenttype_sofortueberweisung == 1) {
			$paymentTypes[13]['image'] = strtolower(QentaCEE\QPay\PaymentType::SOFORTUEBERWEISUNG);
			$paymentTypes[13]['title'] = $this->_getPaymentTypeName(QentaCEE\QPay\PaymentType::SOFORTUEBERWEISUNG);
			$paymentTypes[13]['value'] = QentaCEE\QPay\PaymentType::SOFORTUEBERWEISUNG;
		}
		if ((int)$this->_getMethod()->paymenttype_tatrapay == 1) {
			$paymentTypes[14]['image'] = strtolower(QentaCEE\QPay\PaymentType::TATRAPAY);
			$paymentTypes[14]['title'] = $this->_getPaymentTypeName(QentaCEE\QPay\PaymentType::TATRAPAY);
			$paymentTypes[14]['value'] = QentaCEE\QPay\PaymentType::TATRAPAY;
		}
		if ((int)$this->_getMethod()->paymenttype_trustly == 1) {
			$paymentTypes[15]['image'] = strtolower(QentaCEE\QPay\PaymentType::TRUSTLY);
			$paymentTypes[15]['title'] = $this->_getPaymentTypeName(QentaCEE\QPay\PaymentType::TRUSTLY);
			$paymentTypes[15]['value'] = QentaCEE\QPay\PaymentType::TRUSTLY;
		}
		if ((int)$this->_getMethod()->paymenttype_trustpay == 1) {
			$paymentTypes[16]['image'] = strtolower(QentaCEE\QPay\PaymentType::TRUSTPAY);
			$paymentTypes[16]['title'] = $this->_getPaymentTypeName(QentaCEE\QPay\PaymentType::TRUSTPAY);
			$paymentTypes[16]['value'] = QentaCEE\QPay\PaymentType::TRUSTPAY;
		}
		if ((int)$this->_getMethod()->paymenttype_epay_bg == 1) {
			$paymentTypes[17]['image'] = strtolower(QentaCEE\QPay\PaymentType::EPAYBG);
			$paymentTypes[17]['title'] = $this->_getPaymentTypeName(QentaCEE\QPay\PaymentType::EPAYBG);
			$paymentTypes[17]['value'] = QentaCEE\QPay\PaymentType::EPAYBG;
		}
		if ((int)$this->_getMethod()->paymenttype_moneta == 1) {
			$paymentTypes[18]['image'] = strtolower(QentaCEE\QPay\PaymentType::MONETA);
			$paymentTypes[18]['title'] = $this->_getPaymentTypeName(QentaCEE\QPay\PaymentType::MONETA);
			$paymentTypes[18]['value'] = QentaCEE\QPay\PaymentType::MONETA;
		}
		if ((int)$this->_getMethod()->paymenttype_invoice == 1 && $this->isInvoiceAllowed($cart)) {
			$paymentTypes[19]['image'] = strtolower(QentaCEE\QPay\PaymentType::INVOICE);
			$paymentTypes[19]['title'] = $this->_getPaymentTypeName(QentaCEE\QPay\PaymentType::INVOICE);
			$paymentTypes[19]['value'] = QentaCEE\QPay\PaymentType::INVOICE;
			$paymentTypes[19]['birthday_header'] = JText::_('VMPAYMENT_QENTACEECHECKOUT_BIRTHDAY_HEADER');

			if ($this->_getInvoiceFinancialInstitution() == self::QCP_SERVICE_PROVIDER_PAYOLUTION && $this->_getPayolutionTerms()) {
				$paymentTypes[19]['additional_header'] = JText::_('VMPAYMENT_QENTACEECHECKOUT_PAYOLUTION_CONSENT_HEADER');
				$paymentTypes[19]['consent_text'] = $this->_getPayolutionConsentText();
				$paymentTypes[19]['consent_checked'] = '';
				if ($sessionQenta != null) {
					$paymentTypes[19]['consent_checked'] = ($sessionQenta->consentInvoice == 'on') ? ' checked="checked"' : '';
				}
			}
		}
		if ((int)$this->_getMethod()->paymenttype_invoiceb2b == 1 && $this->isInvoiceB2BAllowed($cart)) {
			$paymentTypes[20]['image'] = strtolower(QentaCEE\QPay\PaymentType::INVOICE);
			$paymentTypes[20]['title'] = $this->_getPaymentTypeName(QentaCEE\QPay\PaymentType::INVOICE . 'B2B');
			$paymentTypes[20]['value'] = QentaCEE\QPay\PaymentType::INVOICE . 'B2B';

			$paymentTypes[20]['additional_header'] = JText::_('VMPAYMENT_QENTACEECHECKOUT_PAYOLUTION_CONSENT_HEADER');
			$paymentTypes[20]['consent_text'] = $this->_getPayolutionConsentText();
			$paymentTypes[20]['consent_checked'] = '';
			if ($sessionQenta != null) {
				$paymentTypes[20]['consent_checked'] = ($sessionQenta->consentInvoiceB2B == 'on') ? ' checked="checked"' : '';
			}
		}
		if ((int)$this->_getMethod()->paymenttype_installment == 1 && $this->isInstallmentAllowed($cart)) {
			$paymentTypes[21]['image'] = strtolower(QentaCEE\QPay\PaymentType::INSTALLMENT);
			$paymentTypes[21]['title'] = $this->_getPaymentTypeName(QentaCEE\QPay\PaymentType::INSTALLMENT);
			$paymentTypes[21]['value'] = QentaCEE\QPay\PaymentType::INSTALLMENT;
			$paymentTypes[21]['birthday_header'] = JText::_('VMPAYMENT_QENTACEECHECKOUT_BIRTHDAY_HEADER');

			if ($this->_getInstallmentFinancialInstitution() == self::QCP_SERVICE_PROVIDER_PAYOLUTION && $this->_getPayolutionTerms()) {
				$paymentTypes[21]['additional_header'] = JText::_('VMPAYMENT_QENTACEECHECKOUT_PAYOLUTION_CONSENT_HEADER');
				$paymentTypes[21]['consent_text'] = $this->_getPayolutionConsentText();
				$paymentTypes[21]['consent_checked'] = '';
				if ($sessionQenta != null) {
					$paymentTypes[21]['consent_checked'] = ($sessionQenta->consentInstallment == 'on') ? ' checked="checked"' : '';
				}
			}
		}
		if ((int)$this->_getMethod()->paymenttype_paypal == 1) {
			$paymentTypes[22]['image'] = strtolower(QentaCEE\QPay\PaymentType::PAYPAL);
			$paymentTypes[22]['title'] = $this->_getPaymentTypeName(QentaCEE\QPay\PaymentType::PAYPAL);
			$paymentTypes[22]['value'] = QentaCEE\QPay\PaymentType::PAYPAL;
		}
		if ((int)$this->_getMethod()->paymenttype_psc == 1) {
			$paymentTypes[23]['image'] = strtolower(QentaCEE\QPay\PaymentType::PSC);
			$paymentTypes[23]['title'] = $this->_getPaymentTypeName(QentaCEE\QPay\PaymentType::PSC);
			$paymentTypes[23]['value'] = QentaCEE\QPay\PaymentType::PSC;
		}
		if ((int)$this->_getMethod()->paymenttype_skrillwallet == 1) {
			$paymentTypes[25]['image'] = strtolower(QentaCEE\QPay\PaymentType::SKRILLWALLET);
			$paymentTypes[25]['title'] = $this->_getPaymentTypeName(QentaCEE\QPay\PaymentType::SKRILLWALLET);
			$paymentTypes[25]['value'] = QentaCEE\QPay\PaymentType::SKRILLWALLET;
		}
		if ((int)$this->_getMethod()->paymenttype_sepadd == 1) {
			$paymentTypes[26]['image'] = strtolower(QentaCEE\QPay\PaymentType::SEPADD);
			$paymentTypes[26]['title'] = $this->_getPaymentTypeName(QentaCEE\QPay\PaymentType::SEPADD);
			$paymentTypes[26]['value'] = QentaCEE\QPay\PaymentType::SEPADD;
		}
		if ((int)$this->_getMethod()->paymenttype_masterpass == 1) {
			$paymentTypes[27]['image'] = strtolower(QentaCEE\QPay\PaymentType::MASTERPASS);
			$paymentTypes[27]['title'] = $this->_getPaymentTypeName(QentaCEE\QPay\PaymentType::MASTERPASS);
			$paymentTypes[27]['value'] = QentaCEE\QPay\PaymentType::MASTERPASS;
		}
		if ((int)$this->_getMethod()->paymenttype_pbx == 1) {
			$paymentTypes[28]['image'] = strtolower(QentaCEE\QPay\PaymentType::PBX);
			$paymentTypes[28]['title'] = $this->_getPaymentTypeName(QentaCEE\QPay\PaymentType::PBX);
			$paymentTypes[28]['value'] = QentaCEE\QPay\PaymentType::PBX;
		}
		if ((int)$this->_getMethod()->paymenttype_voucher == 1) {
			$paymentTypes[29]['image'] = strtolower(QentaCEE\QPay\PaymentType::VOUCHER);
			$paymentTypes[29]['title'] = $this->_getPaymentTypeName(QentaCEE\QPay\PaymentType::VOUCHER);
			$paymentTypes[29]['value'] = QentaCEE\QPay\PaymentType::VOUCHER;
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
			case QentaCEE\QPay\PaymentType::SELECT:
				$title = JText::_('VMPAYMENT_QENTACEECHECKOUT_PAYMENTTYPE_SELECT');
				break;
			case QentaCEE\QPay\PaymentType::CCARD_MOTO:
				$title = JText::_('VMPAYMENT_QENTACEECHECKOUT_PAYMENTTYPE_CCARD_MOTO');
				break;
			case QentaCEE\QPay\PaymentType::CCARD:
				$title = JText::_('VMPAYMENT_QENTACEECHECKOUT_PAYMENTTYPE_CCARD');
				break;
			case QentaCEE\QPay\PaymentType::MAESTRO:
				$title = JText::_('VMPAYMENT_QENTACEECHECKOUT_PAYMENTTYPE_MAESTRO');
				break;
			case QentaCEE\QPay\PaymentType::EPS:
				$title = JText::_('VMPAYMENT_QENTACEECHECKOUT_PAYMENTTYPE_EPS');
				break;
			case QentaCEE\QPay\PaymentType::IDL:
				$title = JText::_('VMPAYMENT_QENTACEECHECKOUT_PAYMENTTYPE_IDL');
				break;
			case QentaCEE\QPay\PaymentType::GIROPAY:
				$title = JText::_('VMPAYMENT_QENTACEECHECKOUT_PAYMENTTYPE_GIROPAY');
				break;
			case QentaCEE\QPay\PaymentType::SOFORTUEBERWEISUNG:
				$title = JText::_('VMPAYMENT_QENTACEECHECKOUT_PAYMENTTYPE_SOFORTUEBERWEISUNG');
				break;
			case QentaCEE\QPay\PaymentType::PBX:
				$title = JText::_('VMPAYMENT_QENTACEECHECKOUT_PAYMENTTYPE_PBX');
				break;
			case QentaCEE\QPay\PaymentType::PSC:
				$title = JText::_('VMPAYMENT_QENTACEECHECKOUT_PAYMENTTYPE_PSC');
				break;
			case QentaCEE\QPay\PaymentType::PAYPAL:
				$title = JText::_('VMPAYMENT_QENTACEECHECKOUT_PAYMENTTYPE_PAYPAL');
				break;
			case QentaCEE\QPay\PaymentType::SEPADD:
				$title = JText::_('VMPAYMENT_QENTACEECHECKOUT_PAYMENTTYPE_SEPA-DD');
				break;
			case QentaCEE\QPay\PaymentType::TRUSTPAY:
				$title = JText::_('VMPAYMENT_QENTACEECHECKOUT_PAYMENTTYPE_TRUSTPAY');
				break;
			case QentaCEE\QPay\PaymentType::INVOICE:
				$title = JText::_('VMPAYMENT_QENTACEECHECKOUT_PAYMENTTYPE_INVOICE');
				break;
			case QentaCEE\QPay\PaymentType::INVOICE . 'B2B':
				$title = JText::_('VMPAYMENT_QENTACEECHECKOUT_PAYMENTTYPE_INVOICEB2B');
				break;
			case QentaCEE\QPay\PaymentType::INSTALLMENT:
				$title = JText::_('VMPAYMENT_QENTACEECHECKOUT_PAYMENTTYPE_INSTALLMENT');
				break;
			case QentaCEE\QPay\PaymentType::BMC:
				$title = JText::_('VMPAYMENT_QENTACEECHECKOUT_PAYMENTTYPE_BANCONTACT_MISTERCASH');
				break;
			case QentaCEE\QPay\PaymentType::P24:
				$title = JText::_('VMPAYMENT_QENTACEECHECKOUT_PAYMENTTYPE_P24');
				break;
			case QentaCEE\QPay\PaymentType::MONETA:
				$title = JText::_('VMPAYMENT_QENTACEECHECKOUT_PAYMENTTYPE_MONETA');
				break;
			case QentaCEE\QPay\PaymentType::POLI:
				$title = JText::_('VMPAYMENT_QENTACEECHECKOUT_PAYMENTTYPE_POLI');
				break;
			case QentaCEE\QPay\PaymentType::EKONTO:
				$title = JText::_('VMPAYMENT_QENTACEECHECKOUT_PAYMENTTYPE_EKONTO');
				break;
			case QentaCEE\QPay\PaymentType::TRUSTLY:
				$title = JText::_('VMPAYMENT_QENTACEECHECKOUT_PAYMENTTYPE_TRUSTLY');
				break;
			case QentaCEE\QPay\PaymentType::MASTERPASS:
				$title = JText::_('VMPAYMENT_QENTACEECHECKOUT_PAYMENTTYPE_MASTERPASS');
				break;
			case QentaCEE\QPay\PaymentType::SKRILLWALLET:
				$title = JText::_('VMPAYMENT_QENTACEECHECKOUT_PAYMENTTYPE_SKRILLWALLET');
				break;
			case QentaCEE\QPay\PaymentType::EPAYBG:
				$title = JText::_('VMPAYMENT_QENTACEECHECKOUT_PAYMENTTYPE_EPAY_BG');
				break;
			case QentaCEE\QPay\PaymentType::TATRAPAY:
				$title = JText::_('VMPAYMENT_QENTACEECHECKOUT_PAYMENTTYPE_TATRAPAY');
				break;
			case QentaCEE\QPay\PaymentType::VOUCHER:
				$title = JText::_('VMPAYMENT_QENTACEECHECKOUT_PAYMENTTYPE_VOUCHER');
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
	 * @param QentaCEE\Stdlib\ConsumerData $consumerData
	 */
	protected function _setConsumerShippingInformation(QentaCEE\Stdlib\ConsumerData $consumerData)
	{
		$order = $this->_getOrder();

		$shippingData = array_key_exists('ST', $order['details']) ? $order['details']['ST'] : $order['details']['BT'];

		$shippingAddress = new QentaCEE\Stdlib\ConsumerData\Address(QentaCEE\Stdlib\ConsumerData\Address::TYPE_SHIPPING);

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
	 * @param QentaCEE\Stdlib\ConsumerData $consumerData
	 */
	protected function _setConsumerBillingInformation(QentaCEE\Stdlib\ConsumerData $consumerData)
	{
		$order = $this->_getOrder();
		$billingData = $order['details']['BT'];

		if (isset($billingData->company)) {
			$consumerData->setCompanyName($billingData->company);
		} else {
			$session = JFactory::getSession();
			$data = $session->get('QENTACEECHECKOUT', null, 'vm');
			$sessionQenta = unserialize($data);
			if (isset($sessionQenta->birthDay)) {
				$birthDay = $sessionQenta->birthDay;
				$birthMonth = $sessionQenta->birthMonth;
				$birthYear = $sessionQenta->birthYear;
				$birthday = new DateTime($birthYear . "-" . $birthMonth . "-" . $birthDay);
				$consumerData->setBirthDate($birthday);
			}
		}
		$consumerData->setEmail($billingData->email);

		$billingAddress = new QentaCEE\Stdlib\ConsumerData\Address(QentaCEE\Stdlib\ConsumerData\Address::TYPE_BILLING);

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

		$basket = new QentaCEE\Stdlib\Basket();

		foreach ($cart->products as $pkey => $prow) {
			$priceWithTax = $prow->prices['basePriceWithTax'];
			if ($prow->prices['salesPriceWithDiscount'] > 0) {
				$priceWithTax = $prow->prices['salesPriceWithDiscount'];
			}

			$tax = round($priceWithTax - $prow->prices['product_price'], $precision - 1);
			$unitPrice = round($priceWithTax - $tax, $precision);

			$bitem = new QentaCEE\Stdlib\Basket\Item($prow->product_sku);
			$bitem->setDescription($prow->product_name);
			$bitem->setName($prow->product_name);
			$bitem->setUnitGrossAmount(number_format($priceWithTax, $precision, '.', ''));
			$bitem->setUnitNetAmount(number_format($unitPrice, $precision, '.', ''));
			$bitem->setUnitTaxRate(round($tax * 100 / $unitPrice ));
			$bitem->setUnitTaxAmount(number_format($tax, $precision, '.', ''));
			$basket->addItem($bitem, (int)$prow->amount);
		}
		if ( $cart->cartData['shipmentValue'] != 0 ) {
			$bitem = new QentaCEE\Stdlib\Basket\Item( 'shipping' );
			$bitem->setName( strip_tags( $cart->cartData['shipmentName'] ) );
			$bitem->setUnitGrossAmount( number_format( $cart->cartPrices['salesPriceShipment'], $precision, '.', '' ) );
			$bitem->setUnitNetAmount( number_format( $cart->cartPrices['shipmentValue'], $precision, '.', '' ));
			$bitem->setUnitTaxAmount( number_format( $cart->cartPrices['shipmentTax'], $precision, '.', '' ) );
			$bitem->setUnitTaxRate(round($cart->cartPrices['shipmentTax'] * 100 / $cart->cartPrices['shipmentValue']));
			$bitem->setDescription( strip_tags( $cart->cartData['shipmentName'] ) );
			$basket->addItem( $bitem );
		}
		return $basket;
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
		$currencies = explode( ",", $this->_getMethod()->invoice_currencies );
		if (! in_array( $currency, $currencies ) )
			return false;

		$country = ShopFunctions::getCountryByID($cart->BT['virtuemart_country_id'], 'country_2_code');
		$countries = explode( ",", $this->_getMethod()->invoice_countries );
		if (! in_array( $country, $countries ) )
			return false;

		if ( $this->_getMethod()->invoice_provider == 'payolution' ) {
			$billingAddress = $cart->BT;
			$shippingAddress = $cart->ST;

			if ( ! is_array( $billingAddress ) ) {
				return false;
			}

			if ( $cart->ST ) {
				$fields = array(
					'virtuemart_country_id',
					'company',
					'first_name',
					'last_name',
					'address_1',
					'address_2',
					'zip',
					'city'
				);
				foreach ( $fields as $f ) {
					if ( isset($billingAddress[$f],$shippingAddress[$f]) && $billingAddress[ $f ] != $shippingAddress[ $f ] ) {
						return false;
					}
				}
			}
		}

		$prices = $cart->getCartPrices();
		$total = $prices['billTotal'];

		if ($this->_getInvoiceMin() && $this->_getInvoiceMin() > $total)
			return false;

		if ($this->_getInvoiceMax() && $this->_getInvoiceMax() < $total)
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

		if ($this->_getInvoiceB2BMin() && $this->_getInvoiceB2BMin() > $total)
			return false;

		if ($this->_getInvoiceB2BMax() && $this->_getInvoiceB2BMax() < $total)
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
		$currencies = explode( ",", $this->_getMethod()->installment_currencies );
		if (! in_array( $currency, $currencies ) )
			return false;

		$country = ShopFunctions::getCountryByID($cart->BT['virtuemart_country_id'], 'country_2_code');
		$countries = explode( ",", $this->_getMethod()->installment_countries );
		if (! in_array( $country, $countries ) )
			return false;

		if ( $this->_getMethod()->installment_provider == 'payolution' ) {
			$billingAddress = $cart->BT;
			$shippingAddress = $cart->ST;

			if ( ! is_array( $billingAddress ) ) {
				return false;
			}

			if ( $cart->ST ) {
				$fields = array(
					'virtuemart_country_id',
					'company',
					'first_name',
					'last_name',
					'address_1',
					'address_2',
					'zip',
					'city'
				);
				foreach ( $fields as $f ) {
					if ( isset($billingAddress[ $f ]) && isset($shippingAddress[ $f ]) && $billingAddress[ $f ] != $shippingAddress[ $f ] ) {
						return false;
					}
				}

			}
		}

		$prices = $cart->getCartPrices();
		$total = $prices['billTotal'];

		if ($this->_getInstallmentMin() && $this->_getInstallmentMin() > $total)
			return false;

		if ($this->_getInstallmentMax() && $this->_getInstallmentMax() < $total)
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
			'demo' => self::QCP_CUSTOMER_ID_DEMO,
			'test' => self::QCP_CUSTOMER_ID_TEST,
			'test3d' => self::QCP_CUSTOMER_ID_TEST3D
		);

		return $customerIdArray[$this->_getMethod()->configuration];
	}

	protected function _getShopId()
	{
		$shopIdArray = array(
			'production' => $this->_getMethod()->shop_id,
			'demo' => self::QCP_SHOP_ID_DEMO,
			'test' => self::QCP_SHOP_ID_TEST,
			'test3d' => self::QCP_SHOP_ID_TEST3D
		);

		return $shopIdArray[$this->_getMethod()->configuration];
	}

	protected function _getSecret()
	{
		$secretArray = array(
			'production' => trim($this->_getMethod()->secret),
			'demo' => self::QCP_SECRET_DEMO,
			'test' => self::QCP_SECRET_TEST,
			'test3d' => self::QCP_SECRET_TEST3D
		);

		return $secretArray[$this->_getMethod()->configuration];
	}

	protected function _getBackendPassword()
	{
		$backendPasswordArray = array(
			'production' => $this->_getMethod()->backend_password,
			'demo' => self::QCP_BACKEND_PASSWORD_DEMO,
			'test' => self::QCP_BACKEND_PASSWORD_TEST,
			'test3d' => self::QCP_BACKEND_PASSWORD_TEST3D
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
		if ($paymentType == strtolower(QentaCEE\Stdlib\PaymentTypeAbstract::INVOICE) || $paymentType == strtolower(QentaCEE\Stdlib\PaymentTypeAbstract::INSTALLMENT)) {
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

		if ($paymentType == strtolower(QentaCEE\Stdlib\PaymentTypeAbstract::POLI)) {
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
		$text = JText::_('VMPAYMENT_QENTACEECHECKOUT_PAYOLUTION_CONSENT');
		$payolutionLink = JText::_('VMPAYMENT_QENTACEECHECKOUT_PAYOLUTION_CONSENT_LINK');

		if (strlen($this->_getPayolutionMid()) > 0) {
			$payolutionLink = sprintf('<a href="https://payment.payolution.com/payolution-payment/infoport/dataprivacyconsent?mId=%s" target="_blank">%s</a>',
				$this->_getPayolutionMid(), JText::_('VMPAYMENT_QENTACEECHECKOUT_PAYOLUTION_CONSENT_LINK'));
		}

		return sprintf($text, $payolutionLink);
	}

	function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter)
	{
		return $this->onCheckAutomaticSelected($cart, $cart_prices, $paymentCounter);
	}

	public function changePaymentTypeAjax($data)
	{
		$session = JFactory::getSession();
		$cart = VirtueMartCart::getCart ();

		$cart->setPaymentMethod(false,true,$data['pid']);
		$sessionData = $session->get('QENTACEECHECKOUT', 0, 'vm');
		if (!empty($sessionData)) {
			$sessionQenta = unserialize($sessionData);
			$sessionQenta->paymenttype = $data["qenta_paymenttype"];
			$sessionQenta->additional = $data["qcp_additional"];
		}
		$session->set('QENTACEECHECKOUT', serialize($sessionQenta), 'vm');
	}

	public function plgVmOnSelfCallFE()
	{

		$action = vRequest::getCmd('action');
		$data = vRequest::getPost();
		switch ($action) {
			case "changePaymentTypeAjax":
				$this->changePaymentTypeAjax($data);
				break;
		}
	}
}
