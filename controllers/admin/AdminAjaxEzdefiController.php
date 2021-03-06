<?php

class AdminAjaxEzdefiController extends ModuleAdminController
{
	protected $helper;

	protected $db;

	protected $api;

    public function __construct()
    {
    	parent::__construct();

		$this->helper = new EzdefiHelper();

		$this->db = new EzdefiDb();

	    $this->api = new EzdefiApi();
    }

    public function ajaxProcessCheckApiKey()
    {
    	$apiUrl = Tools::getValue('api_url', '');
    	$apiKey = Tools::getValue('api_key', '');

    	if(empty($apiUrl) || empty($apiKey)) {
		    $this->ajaxDie('false');
	    }

    	$api = new EzdefiApi();
    	$api->setApiUrl($apiUrl);
    	$api->setApiKey($apiKey);

	    $result = $api->checkApiKey();

	    if(is_null($result)) {
		    $this->ajaxDie('false');
	    }

	    $this->ajaxDie('true');
    }

    public function ajaxProcessCheckPublicKey()
    {
        $apiUrl = Tools::getValue('api_url', '');
        $apiKey = Tools::getValue('api_key', '');
        $publicKey = Tools::getValue('public_key', '');

        if(empty($apiUrl) || empty($apiKey) || empty($publicKey)) {
            $this->ajaxDie('false');
        }

        $api = new EzdefiApi();
        $api->setApiUrl($apiUrl);
        $api->setApiKey($apiKey);
        $api->setPublicKey($publicKey);

        $result = $api->getWebsiteConfig();

        if(is_null($result)) {
            $this->ajaxDie('false');
        }

        $this->ajaxDie('true');
    }

	public function ajaxProcessGetTokens()
    {
	    $apiUrl = Tools::getValue('api_url', '');
	    $apiKey = Tools::getValue('api_key', '');

	    if(empty($apiUrl) || empty($apiKey)) {
		    $this->ajaxDie([], null, null, 400);
	    }

	    $api = new EzdefiApi();
	    $api->setApiUrl($apiUrl);
	    $api->setApiKey($apiKey);

	    $tokens = $api->getTokens(Tools::getValue('keyword', ''));

	    if(is_null($tokens)) {
		    $this->ajaxDie([], null, null, 400);
	    }

	    $this->ajaxDie([
		    'data' => $tokens
	    ]);
    }

	public function ajaxProcessGetExceptions()
	{
		$offset = 0;

		$per_page = 15;

		$page = (int) Tools::getValue('page');

		if($page && $page > 1) {
			$offset = $per_page * ($page - 1);
		}

		$post_data = Tools::getAllValues();

		$data = $this->db->getExceptions($post_data, $offset, $per_page);

		$total = $data['total'];

		$total_pages = ceil($total / $per_page );

		$response = array(
			'data' => $data['data'],
			'meta_data' => array(
				'per_page' => $per_page,
				'current_page' => ($page) ? (int) $page : 1,
				'total' => (int) $total,
				'total_pages' => $total_pages,
				'offset' => $offset
			)
		);

		$this->ajaxDie([
			'data' => $response
		]);
	}

	public function ajaxProcessAssignAmountId()
	{
		if(!Tools::getValue('amount_id') || !Tools::getValue('order_id') || !Tools::getValue('currency')) {
			return $this->ajaxDie([], null, null, 400);
		}

		$amount_id = Tools::getValue('amount_id');

		$currency = Tools::getValue('currency');

		$old_order_id = (Tools::getValue('old_order_id') && !empty(Tools::getValue('old_order_id'))) ? Tools::getValue('old_order_id') : null;

		$order_id = Tools::getValue('order_id');;

		if(is_null($order = $this->helper->getOrderById($order_id))) {
			$this->ajaxDie([], null, null, 400);
		}

		$this->helper->setOrderDone($order_id);

		if(is_null($old_order_id)) {
			$this->db->deleteAmountIdException($amount_id, $currency, $old_order_id);
			$this->db->deleteExceptionByOrderId($order_id);
		} else {
			$this->db->deleteExceptionByOrderId($old_order_id );
		}

		$this->ajaxDie();
	}

	public function ajaxProcessGetOrders()
	{
		$keyword = (Tools::getValue('keyword')) ? Tools::getValue('keyword') : '';

		$orders = $this->db->getOrders($keyword);

		return $this->ajaxDie($orders);
	}

	public function ajaxProcessDeleteAmountId()
	{
		$amount_id = Tools::getValue('amount_id');

		$order_id = (Tools::getValue('order_id'))  ? Tools::getValue('order_id') : null;

		$currency = Tools::getValue('currency');

		$this->db->deleteAmountIdException($amount_id, $currency, $order_id);

		return $this->ajaxDie();
	}

	public function ajaxProcessReverseOrder()
	{
		if( !Tools::getValue('amount_id') || !Tools::getValue('order_id') || !Tools::getValue('currency')) {
			return $this->ajaxDie([], null, null, 400);
		}

		$amount_id = Tools::getValue('amount_id');

		$currency = Tools::getValue('currency');

		$order_id = Tools::getValue('order_id');

		$order = new Order($order_id);

		if(!$order) {
			return $this->ajaxDie([], null, null, 400);
		}

		$this->helper->setOrderAwaiting($order_id);

		$wheres = array(
			'amount_id' => $amount_id,
			'currency' => $currency,
			'order_id' => $order_id,
			'status' => 'done'
		);

		$data = array(
			'order_id' => null,
			'status' => null,
			'payment_method' => null
		);

		$this->db->updateException( $wheres, $data );

		return $this->ajaxDie();
	}

    protected function ajaxDie($value = null, $controller = null, $method = null, $statusCode = 200)
    {
        header('Content-Type: application/json');
        if (!is_scalar($value)) {
            $value = json_encode($value);
        }
        http_response_code($statusCode);
        parent::ajaxDie($value, $controller, $method);
    }
}
