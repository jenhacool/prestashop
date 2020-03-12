<?php

class EzdefiAjaxModuleFrontController extends ModuleFrontController
{
	protected $helper;

	protected $db;

	protected $config;

	protected $api;

	public function __construct()
	{
		parent::__construct();

		$this->helper = new EzdefiHelper();
		$this->db = new EzdefiDb();
		$this->config = new EzdefiConfig();
		$this->api = new EzdefiApi();
	}

	public function initContent()
	{
		parent::initContent();

		$response = null;

		$action = Tools::getValue('action');

		if(!$action || empty($action)) {
			die;
		}

		switch ($action) {
			case 'create_payment':
				$response = $this->createPayment(
					(int) Tools::getValue('uoid', ''),
					(array) Tools::getValue('coin_data', ''),
					(string) Tools::getValue('method', '')
				);
				break;
			case 'check_order_status':
				$response = $this->checkOrderStatus(
					(int) Tools::getValue('uoid', '')
				);
				break;
		}

		header('Content-Type: application/json');

		die(json_encode([
			'data' => $response
		]));
	}

	/**
	 * Create payment AJAX callback
	 *
	 * @param $uoid
	 * @param $symbol
	 * @param $method
	 *
	 * @return bool|string
	 * @throws PrestaShopDatabaseException
	 * @throws PrestaShopException
	 * @throws SmartyException
	 */
	protected function createPayment($uoid, $coin_data, $method)
	{
		if(empty($uoid) || empty($coin_data) || empty($method)) {
			return "<div style='text-align:center'>Can't create payment. Please contact with shop owner</div>";
		}

		if(is_null($order = $this->helper->getOrderById($uoid))) {
			return "<div style='text-align:center'>Can't create payment. Please contact with shop owner</div>";
		}

		if(!in_array($method, array( 'ezdefi_wallet', 'amount_id' ))) {
			return "<div style='text-align:center'>Can't create payment. Please contact with shop owner</div>";
		}

		$amountId = ($method === 'amount_id') ? true : false;

		$paymentData = $this->preparePaymentData(
			$order,
            $coin_data,
			$amountId
		);

		if(is_null($paymentData)) {
			return "<div style='text-align:center'>Can't create payment. Please contact with shop owner</div>";
		}

		$payment = $this->api->createPayment($paymentData);

		if(is_null($payment)) {
			return "<div style='text-align:center'>Can't create payment. Please contact with shop owner</div>";
		}

		if($amountId) {
			$value = $payment['originValue'];
		} else {
			$value = $payment['value'] / pow(10, $payment['decimal']);
		}

		$data = array(
			'amount_id' => str_replace(',', '', $value),
			'currency' => $coin_data['symbol'],
			'order_id' => substr($payment['uoid'], 0, strpos($payment['uoid'],'-')),
			'status' => 'not_paid',
			'payment_method' => ($amountId) ? 'amount_id' : 'ezdefi_wallet',
		);

		$this->db->addException($data);

		$this->context->smarty->assign(array(
			'payment' => $payment,
			'order' => $order,
			'fiat' => $this->helper->getCurrencyIsoCode($order->id_currency),
			'coin_data' => $coin_data,
			'modulePath' => _MODULE_DIR_ . 'ezdefi',
		));

		return $this->context->smarty->fetch('module:ezdefi/views/templates/front/payment.tpl');
	}

	/**
	 * Check order status AJAX callback
	 * @param $uoid
	 *
	 * @return bool|string
	 * @throws PrestaShopDatabaseException
	 * @throws PrestaShopException
	 */
	protected function checkOrderStatus($uoid)
	{
		if(is_null($order = $this->helper->getOrderById($uoid))) {
			return false;
		}

		return ($order->getCurrentState() == $this->config->getConfig('PS_OS_PAYMENT')) ? 'done' : 'pending';
	}

	/**
	 * Prepare data to create payment
	 *
	 * @param $order
	 * @param $currencyData
	 * @param bool $amountId
	 *
	 * @return array|null
	 */
	protected function preparePaymentData($order, $coin_data, $amountId = false)
	{
		$callback = $this->context->link->getModuleLink($this->module->name, 'callback', array(), true);

		$total = $order->total_paid_tax_incl;
		$discount = $coin_data['discount'];
		$value = $total - ($total * ($discount / 100));

		$id_currency = $order->id_currency;
		$fiat = $this->helper->getCurrencyIsoCode($id_currency);

		if($amountId) {
			$rate = $this->api->getTokenExchange($fiat, $coin_data['symbol']);

			if(is_null($rate)) {
			    return null;
            }

            $value = round( $value * $rate, $coin_data['decimal'] );
		}

		$uoid = ($amountId) ? ($order->id . '-1') : ($order->id . '-0');

		$data = [
			'uoid' => $uoid,
			'to' => $coin_data['wallet_address'],
			'value' => $value,
			'safedist' => $coin_data['block_confirmation'],
		    'duration' => $coin_data['duration'] * 60,
			'callback' => $callback,
            'coinId' => $coin_data['_id']
		];

		if($amountId) {
			$data['amountId'] = true;
			$data['currency'] = $coin_data['symbol'] . ':' . $coin_data['symbol'];
		} else {
			$data['currency'] = $fiat . ':' . $coin_data['symbol'];
		}

		return $data;
	}
}