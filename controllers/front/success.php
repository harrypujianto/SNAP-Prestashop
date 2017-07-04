<?php

session_start();

class MidtransPaySuccessModuleFrontController extends ModuleFrontController
{
	public $ssl = true;

	/**
	 * @see FrontController::initContent()
	 */
	public function initContent()
	{
		$this->display_column_left = false;
		$this->display_column_right = false;
		parent::initContent();

		$cart = $this->context->cart;
		if (!$this->module->checkCurrency($cart))
			Tools::redirect('index.php?controller=order');
		$status = 'success';


		// If async payment denied (CIMB, klikpay, etc), redir to failure page
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			parse_str(file_get_contents("php://input"), $data);
			if (json_decode(urldecode($data['response']),true)['transaction_status'] == 'deny') { 
				Tools::redirect( $this->context->link->getModuleLink('midtranspay','failure').'?&status_code=202' ); exit();
			}
		};
		$this->context->smarty->assign(array(
			'status' => $status,
			'this_path' => $this->module->getPathUri(),
			'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->module->name.'/'
		));

		$this->setTemplate('notification.tpl');
	}

}


		