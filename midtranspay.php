<?php
// TODO
// v MIGS fullpay
// v online MIGS installment
// v offline installment
// v online installment
// v bin filter
// v fix text config fields
// v test on migs version
// - certain product
// - add if else checker
// v add javascript to handle option toggle hide-show
// v arrange config
// v add illegible for installment message in payment.tpl
// v add throw catch when notif url is opened by get method
// v add production snap.js url
// v add client key script tag
// 
// TODO 1.7
// v Add description field
// v Test notif & url
// v Additional feature
// v Test additional feature
// v Mutiple getPaymentOptions
// Backward compatibility
// v Check MT_MINAMOUNT strlen
// v Prettify payment page
// v create tutorial


if (!defined('_PS_VERSION_'))
	exit;
// TODO refactor code, get rid of installment etc.
// TODO refactor backend config fields, getrid of enabled payments etc.

// TODO uncomment these, use the real snap php library class (make sure to do this on other file too)
require_once ('library/veritrans/Veritrans.php');
require_once ('library/veritrans/Veritrans/Notification.php');
require_once ('library/veritrans/Veritrans/Transaction.php');

// TODO remove theese
// require_once(dirname(__FILE__).'/../veritranspay/library/veritrans/Veritrans.php');
// require_once(dirname(__FILE__).'/../veritranspay/library/veritrans/Veritrans/Notification.php');
// require_once(dirname(__FILE__).'/../veritranspay/library/veritrans/Veritrans/Transaction.php');


class MidtransPay extends PaymentModule
{
	private $_html = '';
	private $_postErrors = array();

	public $midtrans_merchant_id;
	public $midtrans_merchant_hash;
	public $midtrans_kurs;
	public $midtrans_convenience_fee;
	public $midtrans_client_key;
	public $midtrans_server_key;
	public $midtrans_api_version;
	public $midtrans_installments;
	public $midtrans_3d_secure;
	public $midtrans_payment_type;
	public $midtrans_payment_success_status_mapping;
	public $midtrans_payment_failure_status_mapping;
	public $midtrans_payment_challenge_status_mapping;
	public $midtrans_environment;

	public $config_keys;
	public $hooks = array('payment','header','backOfficeHeader','orderConfirmation','paymentReturn','paymentOptions');


	public function __construct()
	{
		$this->name = 'midtranspay';
		$this->tab = 'payments_gateways';
		$this->version = '1.0';
		$this->author = 'Midtrans';
		$this->bootstrap = true;
		
		$this->currencies = true;
		$this->currencies_mode = 'checkbox';
		$this->midtrans_convenience_fee = 0;

		// key length must be between 0-32 chars to maintain compatibility with <= 1.5
		$this->config_keys = array(			
			'MT_DISPLAY_TITLE',
			'MT_DISPLAY_DESCRIPTION',
			'MT_CLIENT_KEY',
			'MT_SERVER_KEY',
			'MT_API_VERSION',
			'MT_PAYMENT_TYPE',
			'MT_3D_SECURE',
			'MT_KURS',
			'MT_CONVENIENCE_FEE',
			'MT_PAYMENT_SUCCESS_STATUS_MAP',
			'MT_PAYMENT_FAILURE_STATUS_MAP',
			'MT_PAYMENT_CHALLENGE_STATUS_MAP',
			'MT_ENVIRONMENT',
			'MT_ENABLED_CREDIT_CARD',
			'MT_ENABLED_CIMB',
			'MT_ENABLED_MANDIRI',
			'MT_ENABLED_PERMATAVA',
			'MT_ENABLED_BRIEPAY',
			'MT_ENABLED_TELKOMSEL_CASH',
			'MT_ENABLED_XL_TUNAI',
			'MT_ENABLED_MANDIRI_BILL',
			'MT_ENABLED_BBM_MONEY',
			'MT_ENABLED_INDOMARET',
			'MT_ENABLED_INDOSAT_DOMPETKU',
			'MT_ENABLED_MANDIRI_ECASH',
			'MT_SANITIZED',
			'MT_ENABLE_INSTALLMENT',
			'MT_ENABLED_BNI_INSTALLMENT',
			'MT_ENABLED_MANDIRI_INSTALLMENT',
			'MT_INSTALLMENTS_BNI',
			'MT_INSTALLMENTS_MANDIRI',
			'MT_MINAMOUNT',
			// Additional feature vars
			'MT_ENABLED_ADV',
			'MT_ENABLED_MIGS_BTN',
			'MT_TITLE_MIGS_BTN',
			'MT_BINS_MIGS_BTN',
			'MT_ACQ_MIGS_BTN',
			'MT_DISABLE_NON_MIGS_BTN',
			'MT_ENABLED_INSTALLMENTMIGS_BTN',
			'MT_TITLE_INSTALLMENTMIGS_BTN',
			'MT_BINS_INSTALLMENTMIGS_BTN',
			'MT_ACQ_INSTALLMENTMIGS_BTN',
			'MT_ENABLED_INSTALLMENTOFF_BTN',
			'MT_TITLE_INSTALLMENTOFF_BTN',
			'MT_BINS_INSTALLMENTOFF_BTN',
			'MT_TERM_INSTALLMENTOFF_BTN',
			'MT_ENABLED_INSTALLMENTON_BTN',
			'MT_TITLE_INSTALLMENTON_BTN',
			'MT_BINS_INSTALLMENTON_BTN',
			'MT_ENABLED_PROMO_BTN',
			'MT_TITLE_PROMO_BTN',
			'MT_METHOD_PROMO_BTN',
			'MT_BINS_PROMO_BTN',
			'MT_ENABLED_EXPIRY',
			'MT_EXPIRY_DURATION',
			'MT_EXPIRY_UNIT',
			'MT_ENABLED_SAVECARD',
			'MT_ENABLED_FIELDS',
			'MT_FILEDS',
			'MT_ENABLED_CUSTOMVA_BTN',
			'MT_LIST_CUSTOMVA',
			'MT_ENABLED_IGNORE_DENY',
		);

		foreach (array('BNI', 'MANDIRI') as $bank) {
			foreach (array(3, 6, 12) as $months) {
				array_push($this->config_keys, 'MT_INSTALLMENTS_' . $bank . '_' . $months);
			}
		}

		$config = Configuration::getMultiple($this->config_keys);

		foreach ($this->config_keys as $key) {
			if (isset($config[$key]))
				$this->{strtolower($key)} = $config[$key];
		}
		
		
		if (isset($config['MT_KURS']))
			$this->midtrans_kurs = $config['MT_KURS'];
		else
			Configuration::set('MT_KURS', 10000);
		
		Configuration::set('MT_API_VERSION', 2);
		Configuration::set('MT_PAYMENT_TYPE','vtweb');

		if (!isset($config['MT_DISPLAY_TITLE']))
			Configuration::set('MT_DISPLAY_TITLE', "Online Payment via Midtrans");	
		if (!isset($config['MT_DISPLAY_DESCRIPTION']))
			Configuration::set('MT_DISPLAY_DESCRIPTION', "Payment will be displayed on the next step");	
		if (!isset($config['MT_SANITIZED']))
			Configuration::set('MT_SANITIZED', 1);	
		if (!isset($config['MT_3D_SECURE']))
			Configuration::set('MT_3D_SECURE', 1);
		if (!isset($config['MT_ENABLED_CREDIT_CARD']))
			Configuration::set('MT_ENABLED_CREDIT_CARD', 0);
		if (!isset($config['MT_ENABLED_CIMB']))
			Configuration::set('MT_ENABLED_CIMB', 0);		
		if (!isset($config['MT_ENABLED_MANDIRI']))
			Configuration::set('MT_ENABLED_MANDIRI', 0);		
		if (!isset($config['MT_ENABLED_PERMATAVA']))
			Configuration::set('MT_ENABLED_PERMATAVA', 0);
		if (!isset($config['MT_ENABLED_BRIEPAY']))
			Configuration::set('MT_ENABLED_BRIEPAY', 0);
		if (!isset($config['MT_ENABLED_TELKOMSEL_CASH']))
			Configuration::set('MT_ENABLED_TELKOMSEL_CASH', 0);
		if (!isset($config['MT_ENABLED_XL_TUNAI']))
			Configuration::set('MT_ENABLED_XL_TUNAI', 0);
		if (!isset($config['MT_ENABLED_MANDIRI_BILL']))
			Configuration::set('MT_ENABLED_MANDIRI_BILL', 0);
		if (!isset($config['MT_ENABLED_BBM_MONEY']))
			Configuration::set('MT_ENABLED_BBM_MONEY', 0);
		if (!isset($config['MT_ENABLED_INDOMARET']))
			Configuration::set('MT_ENABLED_INDOMARET', 0);
		if (!isset($config['MT_ENABLED_INDOSAT_DOMPETKU']))
			Configuration::set('MT_ENABLED_INDOSAT_DOMPETKU', 0);
		if (!isset($config['MT_ENABLED_MANDIRI_ECASH']))
			Configuration::set('MT_ENABLED_MANDIRI_ECASH', 0);
		if (!isset($config['MT_MINAMOUNT']))
			Configuration::set('MT_MINAMOUNT', 500000);

		// Additional feature vars
		if (!isset($config['MT_ENABLED_ADV']))
			Configuration::set('MT_ENABLED_ADV', 0);

		if (!isset($config['MT_ENABLED_MIGS_BTN']))
			Configuration::set('MT_ENABLED_MIGS_BTN', 0);
		if (!isset($config['MT_TITLE_MIGS_BTN']))
			Configuration::set('MT_TITLE_MIGS_BTN', "Online Payment via Midtrans - MIGS channel");
		if (!isset($config['MT_BINS_MIGS_BTN']))
			Configuration::set('MT_BINS_MIGS_BTN', "");
		if (!isset($config['MT_ACQ_MIGS_BTN']))
			Configuration::set('MT_ACQ_MIGS_BTN', "");
		if (!isset($config['MT_ENABLED_INSTALLMENTMIGS_BTN']))
			Configuration::set('MT_ENABLED_INSTALLMENTMIGS_BTN', 0);
		if (!isset($config['MT_DISABLE_NON_MIGS_BTN']))
			Configuration::set('MT_DISABLE_NON_MIGS_BTN', 0);

		if (!isset($config['MT_ENABLED_INSTALLMENTMIGS_BTN']))
			Configuration::set('MT_ENABLED_INSTALLMENTMIGS_BTN', 0);
		if (!isset($config['MT_TITLE_INSTALLMENTMIGS_BTN']))
			Configuration::set('MT_TITLE_INSTALLMENTMIGS_BTN', "Credit Card Installment Payment via Midtrans - MIGS channel");
		if (!isset($config['MT_BINS_INSTALLMENTMIGS_BTN']))
			Configuration::set('MT_BINS_INSTALLMENTMIGS_BTN', "");
		if (!isset($config['MT_ACQ_INSTALLMENTMIGS_BTN']))
			Configuration::set('MT_ACQ_INSTALLMENTMIGS_BTN', "");

		if (!isset($config['MT_ENABLED_INSTALLMENTOFF_BTN']))
			Configuration::set('MT_ENABLED_INSTALLMENTOFF_BTN', 0);
		if (!isset($config['MT_TITLE_INSTALLMENTOFF_BTN']))
			Configuration::set('MT_TITLE_INSTALLMENTOFF_BTN', "Credit Card Installment for other bank via Midtrans");
		if (!isset($config['MT_BINS_INSTALLMENTOFF_BTN']))
			Configuration::set('MT_BINS_INSTALLMENTOFF_BTN', "");
		if (!isset($config['MT_TERM_INSTALLMENTOFF_BTN']))
			Configuration::set('MT_TERM_INSTALLMENTOFF_BTN', "");

		if (!isset($config['MT_ENABLED_INSTALLMENTON_BTN']))
			Configuration::set('MT_ENABLED_INSTALLMENTON_BTN', 0);
		if (!isset($config['MT_TITLE_INSTALLMENTON_BTN']))
			Configuration::set('MT_TITLE_INSTALLMENTON_BTN', "Credit Card Installment via Midtrans");
		if (!isset($config['MT_BINS_INSTALLMENTON_BTN']))
			Configuration::set('MT_BINS_INSTALLMENTON_BTN', "");

		if (!isset($config['MT_ENABLED_PROMO_BTN']))
			Configuration::set('MT_ENABLED_PROMO_BTN', 0);
		if (!isset($config['MT_TITLE_PROMO_BTN']))
			Configuration::set('MT_TITLE_PROMO_BTN', "Online Payment Promo via Midtrans");
		if (!isset($config['MT_METHOD_PROMO_BTN']))
			Configuration::set('MT_METHOD_PROMO_BTN', "");
		if (!isset($config['MT_BINS_PROMO_BTN']))
			Configuration::set('MT_BINS_PROMO_BTN', "");

		if (!isset($config['MT_ENABLED_EXPIRY']))
			Configuration::set('MT_ENABLED_EXPIRY', 0);
		if (!isset($config['MT_EXPIRY_DURATION']))
			Configuration::set('MT_EXPIRY_DURATION', 24);
		if (!isset($config['MT_EXPIRY_UNIT']))
			Configuration::set('MT_EXPIRY_UNIT', "hours");
		if (!isset($config['MT_ENABLED_SAVECARD']))
			Configuration::set('MT_ENABLED_SAVECARD', 0);
		if (!isset($config['MT_ENABLED_FIELDS']))
			Configuration::set('MT_ENABLED_FIELDS', 0);
		if (!isset($config['MT_FILEDS']))
			Configuration::set('MT_FILEDS', "");
		if (!isset($config['MT_ENABLED_CUSTOMVA_BTN']))
			Configuration::set('MT_ENABLED_CUSTOMVA_BTN', 0);
		if (!isset($config['MT_LIST_CUSTOMVA']))
			Configuration::set('MT_LIST_CUSTOMVA', "bca,mandiri,permata,other_va");
		if (!isset($config['MT_ENABLED_IGNORE_DENY']))
			Configuration::set('MT_ENABLED_IGNORE_DENY', 0);

		parent::__construct();

		$this->displayName = $this->l('Midtrans Pay');
		$this->description = $this->l('Accept payments for your products via Midtrans payment gateway.');
		$this->confirmUninstall = $this->l('Are you sure about uninstalling Midtrans pay?');
		
		
		if (!count(Currency::checkPaymentCurrencies($this->id)))
			$this->warning = $this->l('No currency has been set for this module.');

		// Retrocompatibility
		$this->initContext();
	}

	public function isOldPrestashop()
	{
		return version_compare(Configuration::get('PS_VERSION_DB'), '1.5') == -1;
	}

	public function install()
	{
		// create a new order state for Midtrans, since Prestashop won't assign order ID unless it is validated,
		// and no default order states matches the state we want. Assigning order_id with uniqid() will confuse
		// users in the future
		$order_state = new OrderStateCore();
		$order_state->name = array((int)Configuration::get('PS_LANG_DEFAULT') => 'Awaiting Midtrans payment');;
		$order_state->module_name = 'midtranspay';
		if ($this->isOldPrestashop()) {
			$order_state->color = '#0000FF';
		} else
		{
			$order_state->color = 'RoyalBlue';
		}
		
		$order_state->unremovable = false;
		$order_state->add();

		Configuration::updateValue('MT_ORDER_STATE_ID', $order_state->id);
		Configuration::updateValue('MT_API_VERSION', 2);

		if (!parent::install() || 
			!$this->registerHook('payment') ||
			!$this->registerHook('header') ||
			!$this->registerHook('backOfficeHeader') ||
			!$this->registerHook('orderConfirmation') || 
			!$this->registerHook('paymentReturn') || 
			!$this->registerHook('paymentOptions')
			)
			return false;

		// Set default config values
		Configuration::updateGlobalValue('MT_DISPLAY_TITLE', "Online Payment via Midtrans");	
		Configuration::updateGlobalValue('MT_DISPLAY_DESCRIPTION', "Payment will be displayed on the next step");	
		Configuration::updateGlobalValue('MT_SANITIZED', 1);	
		Configuration::updateGlobalValue('MT_3D_SECURE', 1);
		Configuration::updateGlobalValue('MT_MINAMOUNT', 500000);
		Configuration::updateGlobalValue('MT_ENABLED_ADV', 0);
		Configuration::updateGlobalValue('MT_ENABLED_MIGS_BTN', 0);
		Configuration::updateGlobalValue('MT_TITLE_MIGS_BTN', "Online Payment via Midtrans - MIGS channel");
		Configuration::updateGlobalValue('MT_BINS_MIGS_BTN', "");
		Configuration::updateGlobalValue('MT_ACQ_MIGS_BTN', "");
		Configuration::updateGlobalValue('MT_ENABLED_INSTALLMENTMIGS_BTN', 0);
		Configuration::updateGlobalValue('MT_DISABLE_NON_MIGS_BTN', 0);
		Configuration::updateGlobalValue('MT_ENABLED_INSTALLMENTMIGS_BTN', 0);
		Configuration::updateGlobalValue('MT_TITLE_INSTALLMENTMIGS_BTN', "Credit Card Installment Payment via Midtrans - MIGS channel");
		Configuration::updateGlobalValue('MT_BINS_INSTALLMENTMIGS_BTN', "");
		Configuration::updateGlobalValue('MT_ACQ_INSTALLMENTMIGS_BTN', "");
		Configuration::updateGlobalValue('MT_ENABLED_INSTALLMENTOFF_BTN', 0);
		Configuration::updateGlobalValue('MT_TITLE_INSTALLMENTOFF_BTN', "Credit Card Installment for other bank via Midtrans");
		Configuration::updateGlobalValue('MT_BINS_INSTALLMENTOFF_BTN', "");
		Configuration::updateGlobalValue('MT_TERM_INSTALLMENTOFF_BTN', "6,12");
		Configuration::updateGlobalValue('MT_ENABLED_INSTALLMENTON_BTN', 0);
		Configuration::updateGlobalValue('MT_TITLE_INSTALLMENTON_BTN', "Credit Card Installment via Midtrans");
		Configuration::updateGlobalValue('MT_BINS_INSTALLMENTON_BTN', "");
		Configuration::updateGlobalValue('MT_ENABLED_PROMO_BTN', 0);
		Configuration::updateGlobalValue('MT_TITLE_PROMO_BTN', "Online Payment Promo via Midtrans");
		Configuration::updateGlobalValue('MT_METHOD_PROMO_BTN', "");
		Configuration::updateGlobalValue('MT_BINS_PROMO_BTN', "");
		Configuration::updateGlobalValue('MT_ENABLED_EXPIRY', 0);
		Configuration::updateGlobalValue('MT_EXPIRY_DURATION', 24);
		Configuration::updateGlobalValue('MT_EXPIRY_UNIT', "hours");
		Configuration::updateGlobalValue('MT_ENABLED_SAVECARD', 0);
		Configuration::updateGlobalValue('MT_ENABLED_FIELDS', 0);
		Configuration::updateGlobalValue('MT_FILEDS', "");
		Configuration::updateGlobalValue('MT_ENABLED_CUSTOMVA_BTN', 0);
		Configuration::updateGlobalValue('MT_LIST_CUSTOMVA', "");
		Configuration::updateGlobalValue('MT_ENABLED_IGNORE_DENY', 0);

		return true;
	}

	public function uninstall()
	{
		$status = true;
		foreach ($this->hooks as $hook) {
            $this->unregisterHook($hook);
        }

		$midtrans_payment_waiting_order_state_id = Configuration::get('MT_ORDER_STATE_ID');
		if ($midtrans_payment_waiting_order_state_id)
		{
			$order_state = new OrderStateCore($midtrans_payment_waiting_order_state_id);
			$order_state->delete();
		}

		foreach ($this->config_keys as $key) {
        	Configuration::deleteByName($key);
		}
		
		if (!parent::uninstall())
			$status = false;
		return $status;
	}

	private function _postValidation()
	{
		if (Tools::isSubmit('btnSubmit'))
		{
			if (Tools::getValue('MT_API_VERSION') == 2)
			{
				if (!Tools::getValue('MT_CLIENT_KEY'))
					$this->_postErrors[] = $this->l('Client Key is required.');
				if (!Tools::getValue('MT_SERVER_KEY'))
					$this->_postErrors[] = $this->l('Server Key is required.');
			} 

			// validate conversion rate existence
			if (!Currency::exists('IDR', null) && !Tools::getValue('MT_KURS'))
			{
				$this->_postErrors[] = $this->l('Currency conversion rate must be filled when IDR is not installed in the system.');
			}
		}
	}

	private function _postProcess()
	{
		if (Tools::isSubmit('btnSubmit'))
		{
			foreach ($this->config_keys as $key) {
				Configuration::updateValue($key, Tools::getValue($key));
			}
		}
		$this->_html .= '<div class="alert alert-success conf confirm"> '.$this->l('Settings updated').'</div>';
	}

	private function _displayMidtransPay()
	{
		if (version_compare(Configuration::get('PS_VERSION_DB'), '1.5') == -1)
		{
			$output = $this->context->smarty->fetch(__DIR__ . '/views/templates/hook/infos.tpl');
			$this->_html .= $output;
		} else
		{
			$this->_html .= $this->display(__FILE__, 'infos.tpl');
		}
	}

	private function _displayMidtransPayOld()
	{
		$this->_html .= '<img src="../modules/midtranspay/Midtrans.png" style="float:left; margin-right:15px;"><b>'.$this->l('This module allows payment via midtrans.').'</b><br/><br/>
		'.$this->l('Payment via midtrans.').'<br /><br /><br />';
	}

	private function _displayForm()
	{
		if (version_compare(Configuration::get('PS_VERSION_DB'), '1.5') == -1) {
			// retrocompatibility with Prestashop 1.4
			$this->_displayFormOld();
		} else
		{
			$this->_displayFormNew();
		}
		
	}

	public function getConfigFieldsValues()
	{
		$result = array();
		foreach ($this->config_keys as $key) {
			$result[$key] = Tools::getValue($key, Configuration::get($key));
		}
		//error_log('message fields_value'); // debug
		//error_log(print_r($result,true)); // debug
		return $result;
	}

	private function _displayFormNew()
	{

		$order_states = array();
		foreach (OrderState::getOrderStates($this->context->language->id) as $state) {
			array_push($order_states, array(
				'id_option' => $state['id_order_state'],
				'name' => $state['name']
				)
			);
		}
		
		$installments_options = array();
		foreach (array('BNI', 'MANDIRI') as $bank) {
			$installments_options[$bank] = array();
			foreach (array(3, 6, 12) as $months) {
				array_push($installments_options[$bank], array(
					'id_option' => $bank . '_' . $months,
					'name' => $months . ' Months'
					));
			}
		}

		$environments = array(
			array(
				'id_option' => 'development',
				'name' => 'Development'
				),
			array(
				'id_option' => 'production',
				'name' => 'Production'
				)
			);

		$installment_type = array(
			array(
				'id_option' => 'off',
				'name' => 'Off'
				),
			array(
				'id_option' => 'all_product',
				'name' => 'All Products'
				),
			array(
				'id_option' => 'certain_product',
				'name' => 'Certain Product'
				)			
			);

		$fields_form = array(
			'form' => array(
				'legend' => array(
					'title' => 'Basic Information',
					'icon' => 'icon-cogs'
					),
				'input' => array(
					array(
						'type' => 'text',
						'label' => 'Payment Option Display Text',
						'name' => 'MT_DISPLAY_TITLE',
						'required' => true,
						'desc' => 'Customize payment option title that will be displayed to your customer when they checkout. e.g: Credit Card Payment, Online Payment etc.'
						),
					array(
						'type' => 'text',
						'label' => 'Payment Option Description Text',
						'name' => 'MT_DISPLAY_DESCRIPTION',
						'required' => false,
						'desc' => 'Customize payment option description'
						),
					array(
						'type' => 'select',
						'label' => 'Environment',
						'name' => 'MT_ENVIRONMENT',
						'required' => true,
						'options' => array(
							'query' => $environments,
							'id' => 'id_option',
							'name' => 'name'
							),
						'class' => 'v2_settings sensitive'
						),
					array(
						'type' => 'text',
						'label' => 'Client Key',
						'name' => 'MT_CLIENT_KEY',
						'required' => true,
						'desc' => 'Consult to your Merchant Administration Portal for the value of this field.',
						'class' => 'v1_vtdirect_settings v2_settings sensitive'
						),
					array(
						'type' => 'text',
						'label' => 'Server Key',
						'name' => 'MT_SERVER_KEY',
						'required' => true,
						'desc' => 'Consult to your Merchant Administration Portal for the value of this field.',
						'class' => 'v1_vtdirect_settings v2_settings sensitive'
						),
					array(						
						'type' => (version_compare(Configuration::get('PS_VERSION_DB'), '1.6') == -1)?'radio':'switch',
						'label' => 'Enable 3D Secure?',
						'name' => 'MT_3D_SECURE',
						'required' => true,
						'is_bool' => true,
						'values' => array(
							array(
								'id' => '3d_secure_yes',
								'value' => 1,
								'label' => 'Yes'
								),
							array(
								'id' => '3d_secure_no',
								'value' => 0,
								'label' => 'No'
								)
							),						
						'desc' => 'You must enable 3D Secure. Please contact us if you wish to disable this feature in the Production environment.'
						//'class' => ''
						),
					// array(
					// 	'type' => (version_compare(Configuration::get('PS_VERSION_DB'), '1.6') == -1)?'radio':'switch',
					// 	'label' => 'Enable sanitization',
					// 	'name' => 'MT_SANITIZED',						
					// 	'is_bool' => true,
					// 	'values' => array(
					// 		array(
					// 			'id' => 'sanitized_yes',
					// 			'value' => 1,
					// 			'label' => 'Yes'
					// 			),
					// 		array(
					// 			'id' => 'sanitized_no',
					// 			'value' => 0,
					// 			'label' => 'No'
					// 			)
					// 		),
					// 	//'class' => ''
					// 	),
					// array(
					// 	'type' => (version_compare(Configuration::get('PS_VERSION_DB'), '1.6') == -1)?'radio':'switch',
					// 	'label' => 'Credit Card',
					// 	'name' => 'MT_ENABLED_CREDIT_CARD',						
					// 	'is_bool' => true,
					// 	'values' => array(
					// 		array(
					// 			'id' => 'credit_yes',
					// 			'value' => 1,
					// 			'label' => 'Yes'
					// 			),
					// 		array(
					// 			'id' => 'credit_no',
					// 			'value' => 0,
					// 			'label' => 'No'
					// 			)
					// 		),
					// 	//'class' => ''
					// 	),
					// array(
					// 	'type' => (version_compare(Configuration::get('PS_VERSION_DB'), '1.6') == -1)?'radio':'switch',
					// 	'label' => 'Bank Transfer',
					// 	'name' => 'MT_ENABLED_PERMATAVA',						
					// 	'is_bool' => true,
					// 	'values' => array(
					// 		array(
					// 			'id' => 'permatava_yes',
					// 			'value' => 1,
					// 			'label' => 'Yes'
					// 			),
					// 		array(
					// 			'id' => 'permatava_no',
					// 			'value' => 0,
					// 			'label' => 'No'
					// 			)
					// 		),
					// 	//'class' => ''
					// 	),
					// array(
					// 	'type' => (version_compare(Configuration::get('PS_VERSION_DB'), '1.6') == -1)?'radio':'switch',
					// 	'label' => 'Mandiri Billpayment',
					// 	'name' => 'MT_ENABLED_MANDIRI_BILL',						
					// 	'is_bool' => true,
					// 	'values' => array(
					// 		array(
					// 			'id' => 'mandiri_bill_yes',
					// 			'value' => 1,
					// 			'label' => 'Yes'
					// 			),
					// 		array(
					// 			'id' => 'mandiri_bill_no',
					// 			'value' => 0,
					// 			'label' => 'No'
					// 			)
					// 		),
					// 	//'class' => ''
					// 	),
					// array(
					// 	'type' => (version_compare(Configuration::get('PS_VERSION_DB'), '1.6') == -1)?'radio':'switch',
					// 	'label' => 'CIMB Clicks',
					// 	'name' => 'MT_ENABLED_CIMB',						
					// 	'is_bool' => true,
					// 	'values' => array(
					// 		array(
					// 			'id' => 'cimb_yes',
					// 			'value' => 1,
					// 			'label' => 'Yes'
					// 			),
					// 		array(
					// 			'id' => 'cimb_no',
					// 			'value' => 0,
					// 			'label' => 'No'
					// 			)
					// 		),
					// 	//'class' => ''
					// 	),
					// array(
					// 	'type' => (version_compare(Configuration::get('PS_VERSION_DB'), '1.6') == -1)?'radio':'switch',
					// 	'label' => 'Mandiri ClickPay',
					// 	'name' => 'MT_ENABLED_MANDIRI',						
					// 	'is_bool' => true,
					// 	'values' => array(
					// 		array(
					// 			'id' => 'mandiri_yes',
					// 			'value' => 1,
					// 			'label' => 'Yes'
					// 			),
					// 		array(
					// 			'id' => 'mandiri_no',
					// 			'value' => 0,
					// 			'label' => 'No'
					// 			)
					// 		),
					// 	//'class' => ''
					// 	),
					// array(
					// 	'type' => (version_compare(Configuration::get('PS_VERSION_DB'), '1.6') == -1)?'radio':'switch',
					// 	'label' => 'BRI E-Pay',
					// 	'name' => 'MT_ENABLED_BRIEPAY',						
					// 	'is_bool' => true,
					// 	'values' => array(
					// 		array(
					// 			'id' => 'briepay_yes',
					// 			'value' => 1,
					// 			'label' => 'Yes'
					// 			),
					// 		array(
					// 			'id' => 'briepay_no',
					// 			'value' => 0,
					// 			'label' => 'No'
					// 			)
					// 		),
					// 	//'class' => ''
					// 	),
					// array(
					// 	'type' => (version_compare(Configuration::get('PS_VERSION_DB'), '1.6') == -1)?'radio':'switch',
					// 	'label' => 'Telkomsel T-Cash',
					// 	'name' => 'MT_ENABLED_TELKOMSEL_CASH',						
					// 	'is_bool' => true,
					// 	'values' => array(
					// 		array(
					// 			'id' => 'telkomsel_cash_yes',
					// 			'value' => 1,
					// 			'label' => 'Yes'
					// 			),
					// 		array(
					// 			'id' => 'telkomsel_cash_no',
					// 			'value' => 0,
					// 			'label' => 'No'
					// 			)
					// 		),
					// 	//'class' => ''
					// 	),
					// array(
					// 	'type' => (version_compare(Configuration::get('PS_VERSION_DB'), '1.6') == -1)?'radio':'switch',
					// 	'label' => 'XL Tunai',
					// 	'name' => 'MT_ENABLED_XL_TUNAI',						
					// 	'is_bool' => true,
					// 	'values' => array(
					// 		array(
					// 			'id' => 'xl_tunai_yes',
					// 			'value' => 1,
					// 			'label' => 'Yes'
					// 			),
					// 		array(
					// 			'id' => 'xl_tunai_no',
					// 			'value' => 0,
					// 			'label' => 'No'
					// 			)
					// 		),
					// 	//'class' => ''
					// 	),
					// array(
					// 	'type' => (version_compare(Configuration::get('PS_VERSION_DB'), '1.6') == -1)?'radio':'switch',
					// 	'label' => 'Indomaret',
					// 	'name' => 'MT_ENABLED_INDOMARET',						
					// 	'is_bool' => true,
					// 	'values' => array(
					// 		array(
					// 			'id' => 'indomaret_yes',
					// 			'value' => 1,
					// 			'label' => 'Yes'
					// 			),
					// 		array(
					// 			'id' => 'indomaret_no',
					// 			'value' => 0,
					// 			'label' => 'No'
					// 			)
					// 		),
					// 	//'class' => ''
					// 	),
					// array(
					// 	'type' => (version_compare(Configuration::get('PS_VERSION_DB'), '1.6') == -1)?'radio':'switch',
					// 	'label' => 'Indosat Dompetku',
					// 	'name' => 'MT_ENABLED_INDOSAT_DOMPETKU',						
					// 	'is_bool' => true,
					// 	'values' => array(
					// 		array(
					// 			'id' => 'indosat_dompetku_yes',
					// 			'value' => 1,
					// 			'label' => 'Yes'
					// 			),
					// 		array(
					// 			'id' => 'indosat_dompetku_no',
					// 			'value' => 0,
					// 			'label' => 'No'
					// 			)
					// 		),
					// 	//'class' => ''
					// 	),
					// array(
					// 	'type' => (version_compare(Configuration::get('PS_VERSION_DB'), '1.6') == -1)?'radio':'switch',
					// 	'label' => 'Mandiri Ecash',
					// 	'name' => 'MT_ENABLED_MANDIRI_ECASH',						
					// 	'is_bool' => true,
					// 	'values' => array(
					// 		array(
					// 			'id' => 'mandiri_ecash_yes',
					// 			'value' => 1,
					// 			'label' => 'Yes'
					// 			),
					// 		array(
					// 			'id' => 'mandiri_ecash_no',
					// 			'value' => 0,
					// 			'label' => 'No'
					// 			)
					// 		),
					// 	//'class' => ''
					// 	),
					/*array(
						'type' => 'select',
						'label' => 'Enable Installments',
						'name' => 'MT_ENABLE_INSTALLMENT',						
						'options' => array(
							'query' => $installment_type,
							'id' => 'id_option',
							'name' => 'name'
							),
						//'class' => ''
						),
					array(
						'type' => (version_compare(Configuration::get('PS_VERSION_DB'), '1.6') == -1)?'radio':'switch',
						'label' => 'BNI Installment',
						'name' => 'MT_ENABLED_BNI_INSTALLMENT',						
						'is_bool' => true,
						'values' => array(
							array(
								'id' => 'MT_ENABLED_BNI_INSTALLMENT_on',
								'value' => 1,
								'label' => 'Yes'
								),
							array(
								'id' => 'MT_ENABLED_BNI_INSTALLMENT_off',
								'value' => 0,
								'label' => 'No'
								)
							),
						//'class' => 'MT_ENABLED_BNI_INSTALLMENT'
						),
					array(
						'type' => 'text',
						'label' => 'Enable BNI Installments?',
						'name' => 'MT_INSTALLMENTS_BNI',
						//'class' => 'v1_vtweb_settings sensitive'\
						'class' => 'MT_INSTALLMENTS_BNI'	
						),
					array(
						'type' => (version_compare(Configuration::get('PS_VERSION_DB'), '1.6') == -1)?'radio':'switch',
						'label' => 'MANDIRI Installment',
						'name' => 'MT_ENABLED_MANDIRI_INSTALLMENT',						
						'is_bool' => true,
						'values' => array(
							array(
								'id' => 'MT_ENABLED_MANDIRI_INSTALLMENT_on',
								'value' => 1,
								'label' => 'Yes'
								),
							array(
								'id' => 'MT_ENABLED_MANDIRI_INSTALLMENT_off',
								'value' => 0,
								'label' => 'No'
								)
							),
						//'class' => 'MT_ENABLED_MANDIRI_INSTALLMENT'
						),
					array(
						'type' => 'text',
						'label' => 'Enable Mandiri Installments?',
						'name' => 'MT_INSTALLMENTS_MANDIRI',
						//'class' => 'v1_vtweb_settings sensitive'\
						'class' => 'MT_INSTALLMENTS_MANDIRI'	
						),*/							
				/*	array(
						'type' => 'checkbox',
						'label' => 'Enable Mandiri Installments?',
						'name' => 'MT_INSTALLMENTS',
						'values' => array(
							'query' => $installments_options['MANDIRI'],
							'id' => 'id_option',
							'name' => 'name'
							),
						//'class' => 'v1_vtweb_settings sensitive'
						'class' => 'MT_INSTALLMENTS_MANDIRI'
						),*/
					array(
						'type' => 'select',
						'label' => 'Map payment SUCCESS status to:',
						'name' => 'MT_PAYMENT_SUCCESS_STATUS_MAP',
						'required' => true,	
						'options' => array(
							'query' => $order_states,
							'id' => 'id_option',
							'name' => 'name'
							),
						//'class' => ''
						),
					array(
						'type' => 'select',
						'label' => 'Map payment FAILURE status to:',
						'name' => 'MT_PAYMENT_FAILURE_STATUS_MAP',
						'required' => true,
						'options' => array(
							'query' => $order_states,
							'id' => 'id_option',
							'name' => 'name'
							),
						//'class' => ''
						),
					array(
						'type' => 'select',
						'label' => 'Map payment PENDING/CHALLENGE status to:',
						'name' => 'MT_PAYMENT_CHALLENGE_STATUS_MAP',
						'required' => true,
						'options' => array(
							'query' => $order_states,
							'id' => 'id_option',
							'name' => 'name'
							),
						//'class' => ''
						),
					array(
						'type' => 'text',
						'label' => 'IDR Conversion Rate',
						'name' => 'MT_KURS',
						'desc' => 'Midtrans will use this rate to convert prices to IDR when there are no default conversion system.'
						),
					// array(						
					// 	'type' => (version_compare(Configuration::get('PS_VERSION_DB'), '1.6') == -1)?'radio':'switch',
					// 	'label' => '<strong>Show Advanced Features</strong>',
					// 	'name' => 'MT_ENABLED_ADV',
					// 	'required' => false,
					// 	'is_bool' => true,
					// 	'values' => array(
					// 		array(
					// 			'id' => 'advanced_feature_yes',
					// 			'value' => 1,
					// 			'label' => 'Yes'
					// 			),
					// 		array(
					// 			'id' => 'advanced_feature_no',
					// 			'value' => 0,
					// 			'label' => 'No'
					// 			)
					// 		),						
					// 	'desc' => ''
					// 	//'class' => ''
					// 	),
					array(
						'type' => 'text',
						'label' => 'Installment Minimum Amount',
						'name' => 'MT_MINAMOUNT',
						'desc' => 'Minimum amount to allow payment using installment.',
						'class' => 'advanced'
						),
					// Installment Online
					array(						
						'type' => (version_compare(Configuration::get('PS_VERSION_DB'), '1.6') == -1)?'radio':'switch',
						'label' => '<strong>Enable Online Installment?</strong>',
						'name' => 'MT_ENABLED_INSTALLMENTON_BTN',
						'required' => false,
						'is_bool' => true,
						'values' => array(
							array(
								'id' => 'installmenton_btn_yes',
								'value' => 1,
								'label' => 'Yes'
								),
							array(
								'id' => 'installmenton_btn_no',
								'value' => 0,
								'label' => 'No'
								)
							),						
						// 'desc' => 'Enable online installment payment support, please makesure you have complete the business requirements.',
						'class' => 'advanced-on'
						),
					array(
						'type' => 'text',
						'label' => 'Payment Button Online Installment Display Text',
						'name' => 'MT_TITLE_INSTALLMENTON_BTN',
						'required' => false,
						'desc' => 'Customize payment button title that will be displayed to your customer when they checkout using Online Installment. e.g: Credit Card Installment Payment etc.',
						'class' => 'advanced-on'
						),
					array(
						'type' => 'text',
						'label' => 'Allowed CC BINs for Online Installment',
						'name' => 'MT_BINS_INSTALLMENTON_BTN',
						'desc' => 'Allowed Credit Card BINs for Online Installment payment, separate payment method code with coma. e.g: 5,4811,bni. Leave blank if you are not sure.',
						'class' => 'advanced-on'
						),
					// Installment Offline
					array(						
						'type' => (version_compare(Configuration::get('PS_VERSION_DB'), '1.6') == -1)?'radio':'switch',
						'label' => '<strong>Enable Offline Installment?</strong>',
						'name' => 'MT_ENABLED_INSTALLMENTOFF_BTN',
						'required' => false,
						'is_bool' => true,
						'values' => array(
							array(
								'id' => 'installmentoff_btn_yes',
								'value' => 1,
								'label' => 'Yes'
								),
							array(
								'id' => 'installmentoff_btn_no',
								'value' => 0,
								'label' => 'No'
								)
							),						
						// 'desc' => 'Enable offline installment payment support, please makesure you have complete the business requirements.',
						'class' => 'advanced-off'
						//'class' => ''
						),
					array(
						'type' => 'text',
						'label' => 'Payment Button Offline Installment Display Text',
						'name' => 'MT_TITLE_INSTALLMENTOFF_BTN',
						'required' => false,
						'desc' => 'Customize payment button title that will be displayed to your customer when they checkout using Offline Installment. e.g: Credit Card Installment Payment etc.',
						'class' => 'advanced-off'
						),
					array(
						'type' => 'text',
						'label' => 'Allowed CC BINs for Offline Installment',
						'name' => 'MT_BINS_INSTALLMENTOFF_BTN',
						'desc' => 'Allowed Credit Card BINs for Offline Installment payment. Leave blank if you are not sure.',
						'class' => 'advanced-off'
						),
					array(
						'type' => 'text',
						'label' => 'Offline Installment Terms',
						'name' => 'MT_TERM_INSTALLMENTOFF_BTN',
						'desc' => 'Allowed Offline Installment terms. Separate terms with coma. e.g: 6,12.',
						'class' => 'advanced-off'
						),
					// Promo payment
					array(						
						'type' => (version_compare(Configuration::get('PS_VERSION_DB'), '1.6') == -1)?'radio':'switch',
						'label' => '<strong>Enable Promo Payment?</strong>',
						'name' => 'MT_ENABLED_PROMO_BTN',
						'required' => false,
						'is_bool' => true,
						'values' => array(
							array(
								'id' => 'promo_btn_yes',
								'value' => 1,
								'label' => 'Yes'
								),
							array(
								'id' => 'promo_btn_no',
								'value' => 0,
								'label' => 'No'
								)
							),						
						// 'desc' => 'Enable additional button for promo/discount, please makesure you have promo agreement with us.',
						'class' => 'advanced-promo'
						//'class' => ''
						),
					array(
						'type' => 'text',
						'label' => 'Promo Button Display Text',
						'name' => 'MT_TITLE_PROMO_BTN',
						'required' => false,
						'desc' => 'Customize payment button title that will be displayed to your customer when they checkout using Promo Button. e.g: Credit Card Promo etc.',
						'class' => 'advanced-promo'
						),
					array(
						'type' => 'text',
						'label' => 'Allowed CC BINs for Promo',
						'name' => 'MT_BINS_PROMO_BTN',
						'desc' => 'Allowed Credit Card BINs for Promo payment, separate payment method code with coma. e.g: 5,4811,bni. Leave blank if you are not sure.',
						'class' => 'advanced-promo'
						),
					array(
						'type' => 'text',
						'label' => 'Allowed Payment Method for Promo',
						'name' => 'MT_METHOD_PROMO_BTN',
						'desc' => 'Customize allowed payment method, separate payment method code with coma. e.g: bank_transfer,credit_card. Leave blank if you are not sure.',
						'class' => 'advanced-promo'
						),
					// MIGS
					array(						
						'type' => (version_compare(Configuration::get('PS_VERSION_DB'), '1.6') == -1)?'radio':'switch',
						'label' => '<strong>Enable MIGS Channel Acquiring?</strong>',
						'name' => 'MT_ENABLED_MIGS_BTN',
						'required' => false,
						'is_bool' => true,
						'values' => array(
							array(
								'id' => 'migs_btn_yes',
								'value' => 1,
								'label' => 'Yes'
								),
							array(
								'id' => 'migs_btn_no',
								'value' => 0,
								'label' => 'No'
								)
							),						
						// 'desc' => 'Enable additional button for Credit Card with MIGS channel as acquirer',
						'class' => 'advanced-migs'
						//'class' => ''
						),
					array(
						'type' => 'text',
						'label' => 'Payment Button CC MIGS fullpayment Display Text',
						'name' => 'MT_TITLE_MIGS_BTN',
						'required' => false,
						'desc' => 'Customize payment button title that will be displayed to your customer when they checkout for CC MIGS fullpayment. e.g: BCA Credit Card, Maybank Credit Card, etc.',
						'class' => 'advanced-migs'
						),
					array(
						'type' => 'text',
						'label' => 'Allowed CC BINs for CC MIGS fullpayment',
						'name' => 'MT_BINS_MIGS_BTN',
						'desc' => 'Allowed Credit Card BINs for MIGS Credit Card payment. Leave blank if you are not sure.',
						'class' => 'advanced-migs'
						),
					array(
						'type' => 'text',
						'label' => 'Acquiring Bank CC MIGS fullpayment',
						'name' => 'MT_ACQ_MIGS_BTN',
						'desc' => 'Specify your acquiring bank for MIGS Credit Card Payment. Leave blank if you are not sure',
						'class' => 'advanced-migs'
						),
					array(						
						'type' => (version_compare(Configuration::get('PS_VERSION_DB'), '1.6') == -1)?'radio':'switch',
						'label' => '<strong>Disable Non MIGS Channel Acquiring button?</strong>',
						'desc' => 'Select yes if you only have MIGS channel acquiring',
						'name' => 'MT_DISABLE_NON_MIGS_BTN',
						'required' => false,
						'is_bool' => true,
						'values' => array(
							array(
								'id' => 'disable_non_migs_btn_yes',
								'value' => 1,
								'label' => 'Yes'
								),
							array(
								'id' => 'disable_non_migs_btn_no',
								'value' => 0,
								'label' => 'No'
								)
							),						
						// 'desc' => 'Enable additional button for Credit Card with MIGS channel as acquirer',
						'class' => 'advanced-migs'
						//'class' => ''
						),
					// Installment MIGS
					array(						
						'type' => (version_compare(Configuration::get('PS_VERSION_DB'), '1.6') == -1)?'radio':'switch',
						'label' => '<strong>Enable Online Installment MIGS Channel?</strong>',
						'name' => 'MT_ENABLED_INSTALLMENTMIGS_BTN',
						'required' => false,
						'is_bool' => true,
						'values' => array(
							array(
								'id' => 'installmentmigs_btn_yes',
								'value' => 1,
								'label' => 'Yes'
								),
							array(
								'id' => 'installmentmigs_btn_no',
								'value' => 0,
								'label' => 'No'
								)
							),						
						// 'desc' => 'Enable online installment (MIGS channel) payment support, please makesure you have complete the business requirements.',
						'class' => 'advanced-insmigs'
						//'class' => ''
						),
					array(
						'type' => 'text',
						'label' => 'Payment Button CC Online Installment MIGS fullpayment Display Text',
						'name' => 'MT_TITLE_INSTALLMENTMIGS_BTN',
						'required' => false,
						'desc' => 'Customize payment button title that will be displayed to your customer when they checkout for CC MIGS online installment. e.g: Installment BCA Credit Card, Maybank Installment, etc.',
						'class' => 'advanced-insmigs'
						),
					array(
						'type' => 'text',
						'label' => 'Allowed CC BINs for CC Online Installment MIGS',
						'name' => 'MT_BINS_INSTALLMENTMIGS_BTN',
						'desc' => 'Allowed Credit Card BINs for MIGS online installment payment. Leave blank if you are not sure.',
						'class' => 'advanced-insmigs'
						),
					array(
						'type' => 'text',
						'label' => 'Acquiring Bank CC Online Installment MIGS',
						'name' => 'MT_ACQ_INSTALLMENTMIGS_BTN',
						'desc' => 'Specify your acquiring bank for MIGS Online Installment. Leave blank if you are not sure.',
						'class' => 'advanced-insmigs'
						),
					// Custom Expiry
					array(						
						'type' => (version_compare(Configuration::get('PS_VERSION_DB'), '1.6') == -1)?'radio':'switch',
						'label' => '<strong>Enable Custom Expiry Feature?</strong>',
						'name' => 'MT_ENABLED_EXPIRY',
						'required' => false,
						'is_bool' => true,
						'values' => array(
							array(
								'id' => 'expiry_btn_yes',
								'value' => 1,
								'label' => 'Yes'
								),
							array(
								'id' => 'expiry_btn_no',
								'value' => 0,
								'label' => 'No'
								)
							),
						'class' => 'advanced-expiry'
						//'class' => ''
						),
					array(
						'type' => 'text',
						'label' => 'Duration',
						'name' => 'MT_EXPIRY_DURATION',
						'required' => false,
						'desc' => 'Duration in number. e.g: 30.',
						'class' => 'advanced-expiry'
						),
					array(
						'type' => 'text',
						'label' => 'Time Unit',
						'name' => 'MT_EXPIRY_UNIT',
						'desc' => 'Time unit of the duration. e.g: minutes',
						'class' => 'advanced-expiry'
						),
					// SaveCard
					array(						
						'type' => (version_compare(Configuration::get('PS_VERSION_DB'), '1.6') == -1)?'radio':'switch',
						'label' => '<strong>Enable Save Card?</strong>',
						'name' => 'MT_ENABLED_SAVECARD',
						'required' => false,
						'is_bool' => true,
						'values' => array(
							array(
								'id' => 'savecard_btn_yes',
								'value' => 1,
								'label' => 'Yes'
								),
							array(
								'id' => 'savecard_btn_no',
								'value' => 0,
								'label' => 'No'
								)
							),
						'class' => 'advanced-savecard'
						//'class' => ''
						),
					// Custom Fields
					array(						
						'type' => (version_compare(Configuration::get('PS_VERSION_DB'), '1.6') == -1)?'radio':'switch',
						'label' => '<strong>Enable Custom Fields Feature?</strong>',
						'name' => 'MT_ENABLED_FIELDS',
						'required' => false,
						'is_bool' => true,
						'values' => array(
							array(
								'id' => 'fields_btn_yes',
								'value' => 1,
								'label' => 'Yes'
								),
							array(
								'id' => 'fields_btn_no',
								'value' => 0,
								'label' => 'No'
								)
							),
						'class' => 'advanced-fields'
						//'class' => ''
						),
					array(
						'type' => 'text',
						'label' => 'Custom Fields',
						'name' => 'MT_FILEDS',
						'required' => false,
						'desc' => 'Up to 3 custom fields separated by coma (,). e.g: Order from web, Prestashop, Processing',
						'class' => 'advanced-fields'
						),
					// Custom VA button
					array(						
						'type' => (version_compare(Configuration::get('PS_VERSION_DB'), '1.6') == -1)?'radio':'switch',
						'label' => '<strong>Enable Separated Bank Transfer Button?</strong>',
						'name' => 'MT_ENABLED_CUSTOMVA_BTN',
						'required' => false,
						'is_bool' => true,
						'values' => array(
							array(
								'id' => 'fields_btn_yes',
								'value' => 1,
								'label' => 'Yes'
								),
							array(
								'id' => 'fields_btn_no',
								'value' => 0,
								'label' => 'No'
								)
							),
						'class' => 'advanced-fields'
						//'class' => ''
						),
					array(
						'type' => 'text',
						'label' => 'Displayed Banks',
						'name' => 'MT_LIST_CUSTOMVA',
						'required' => true,
						'desc' => 'bank names separated by coma (,). e.g: permata,mandiri,bca,other_va',
						'class' => 'advanced-customva'
						),
					// Ignore Deny Notification
					array(						
						'type' => (version_compare(Configuration::get('PS_VERSION_DB'), '1.6') == -1)?'radio':'switch',
						'label' => 'Don\'t change order status when credit card transaction failed?',
						'name' => 'MT_ENABLED_IGNORE_DENY',
						'required' => false,
						'is_bool' => true,
						'values' => array(
							array(
								'id' => 'fields_btn_yes',
								'value' => 1,
								'label' => 'Yes'
								),
							array(
								'id' => 'fields_btn_no',
								'value' => 0,
								'label' => 'No'
								)
							),
						'class' => 'advanced-ignore'
						//'class' => ''
						)
					),
				'submit' => array(
					'title' => $this->l('Save'),
					)
				)
			);
		

		$helper = new HelperForm();
		$helper->show_toolbar = false;
		$helper->table =  $this->table;
		$lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
		$helper->default_form_language = $lang->id;
		$helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
		$this->fields_form = array();
		$helper->id = (int)Tools::getValue('id_carrier');
		$helper->identifier = $this->identifier;
		$helper->submit_action = 'btnSubmit';
		$helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->tpl_vars = array(
			'fields_value' => $this->getConfigFieldsValues(),
			'languages' => $this->context->controller->getLanguages(),
			'id_language' => $this->context->language->id
		);



		$this->_html .= $helper->generateForm(array($fields_form));
		//$this->_html .= $helper->generateForm(array($fields_payment_form));
	}

	private function _displayFormOld()
	{
		$order_states = array();
		foreach (OrderState::getOrderStates($this->context->cookie->id_lang) as $state) {
			array_push($order_states, array(
				'id_option' => $state['id_order_state'],
				'name' => $state['name']
				)
			);
		}

		$this->context->smarty->assign(array(
			'form_url' => Tools::htmlentitiesUTF8($_SERVER['REQUEST_URI']),
			'api_version' => htmlentities(Configuration::get('MT_API_VERSION'), ENT_COMPAT, 'UTF-8'),
			'api_versions' => array(1 => 'v1', 2 => 'v2'),
			//'payment_type' => htmlentities(Configuration::get('MT_PAYMENT_TYPE'), ENT_COMPAT, 'UTF-8'),
			//'payment_types' => array('vtweb' => 'VT-Web', 'vtdirect' => 'VT-Direct'),
			'client_key' => htmlentities(Configuration::get('MT_CLIENT_KEY'), ENT_COMPAT, 'UTF-8'),
			'server_key' => htmlentities(Configuration::get('MT_SERVER_KEY'), ENT_COMPAT, 'UTF-8'),
			'environments' => array(false => 'Development', true => 'Production'),
			'environment' => htmlentities(Configuration::get('MT_ENVIRONMENT'), ENT_COMPAT, 'UTF-8'),
			'enable_3d_secure' => htmlentities(Configuration::get('MT_3D_SECURE'), ENT_COMPAT, 'UTF-8'),
			'enable_sanitized' => htmlentities(Configuration::get('MT_SANITIZED'), ENT_COMPAT, 'UTF-8'),
			'enabled_cimb' => htmlentities(Configuration::get('MT_ENABLED_CIMB'), ENT_COMPAT, 'UTF-8'),
			'enabled_mandiri' => htmlentities(Configuration::get('MT_ENABLED_MANDIRI'), ENT_COMPAT, 'UTF-8'),
			'enabled_permatava' => htmlentities(Configuration::get('MT_ENABLED_PERMATAVA'), ENT_COMPAT, 'UTF-8'),
			'enabled_indomaret' => htmlentities(Configuration::get('MT_ENABLED_INDOMARET'), ENT_COMPAT, 'UTF-8'),
			'enabled_indosat_dompetku' => htmlentities(Configuration::get('MT_ENABLED_INDOSAT_DOMPETKU'), ENT_COMPAT, 'UTF-8'),
			'enabled_mandiri_ecash' => htmlentities(Configuration::get('MT_ENABLED_MANDIRI_ECASH'), ENT_COMPAT, 'UTF-8'),
			'statuses' => $order_states,
			'payment_success_status_map' => htmlentities(Configuration::get('MT_PAYMENT_SUCCESS_STATUS_MAP'), ENT_COMPAT, 'UTF-8'),
			'payment_challenge_status_map' => htmlentities(Configuration::get('MT_PAYMENT_CHALLENGE_STATUS_MAP'), ENT_COMPAT, 'UTF-8'),
			'payment_failure_status_map' => htmlentities(Configuration::get('MT_PAYMENT_FAILURE_STATUS_MAP'), ENT_COMPAT, 'UTF-8'),
			'kurs' => htmlentities(Configuration::get('MT_KURS', $this->midtrans_kurs), ENT_COMPAT, 'UTF-8'),
			//'kurs' => htmlentities(Configuration::get('VT_INSTALLMENTS_BNI', ENT_COMPAT, 'UTF-8'),
			'convenience_fee' => htmlentities(Configuration::get('MT_CONVENIENCE_FEE', $this->midtrans_convenience_fee), ENT_COMPAT, 'UTF-8'),
			'this_path' => $this->_path,
			'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/'
			));
		$output = $this->context->smarty->fetch(__DIR__ . '/views/templates/hook/admin_retro.tpl');
		$this->_html .= $output;
	}

	public function getContent()
	{
		// $this->_html = '<h2>'.$this->displayName.'</h2>';

		if (Tools::isSubmit('btnSubmit'))
		{
			$this->_postValidation();
			if (!count($this->_postErrors))
				$this->_postProcess();
			else
				foreach ($this->_postErrors as $err)
					$this->_html .= '<div class="alert alert-danger error">'. $err . '</div>';
		}
		else
			$this->_html .= '<br />';

		$this->_displayMidtransPay();
		$this->_displayForm();

		return $this->_html;
	}

	public function hookDisplayHeader($params)
	{
		
	}

	// working in 1.5 and 1.6
	public function hookDisplayBackOfficeHeader($params)
	{
		$this->context->controller->addJquery();
		$this->context->controller->addJS($this->_path . 'js/midtrans_admin.js', 'all');
	}

	public function hookPayment($params)
	{
		return $this->hookDisplayPayment($params);				
	}

    public function hookPaymentOptions($params)
    {
        if (!$this->active || !$this->checkCurrency($params['cart'])) {
            return; }
    	$orderTotal = $this->context->cart->getOrderTotal();

        $payment_options = array();

        if (Configuration::get('MT_DISABLE_NON_MIGS_BTN') != 1) {
            $payment_options[] = $this->getSnapFullpaymentOption(); }
    	if (Configuration::get('MT_ENABLED_MIGS_BTN') == 1) {
    		$payment_options[] = $this->getSnapMigsOption(); }
    	if (Configuration::get('MT_ENABLED_PROMO_BTN') == 1) {
    		$payment_options[] = $this->getSnapPromoOption(); }
    	if (Configuration::get('MT_ENABLED_INSTALLMENTMIGS_BTN') == 1 && $orderTotal >= Configuration::get('MT_MINAMOUNT')) {
    		$payment_options[] = $this->getSnapInstallmentMigsOption(); }
    	if (Configuration::get('MT_ENABLED_INSTALLMENTON_BTN') == 1 && $orderTotal >= Configuration::get('MT_MINAMOUNT')) {
    		$payment_options[] = $this->getSnapInstallmentOnlineOption(); }
    	if (Configuration::get('MT_ENABLED_INSTALLMENTOFF_BTN') == 1 && $orderTotal >= Configuration::get('MT_MINAMOUNT')) {
    		$payment_options[] = $this->getSnapInstallmentOfflineOption(); }

        return $payment_options;
    }

    public function getSnapFullpaymentOption()
    {
		$this->context->smarty->assign(array(
				'MT_DISPLAY_DESCRIPTION' =>  $this->l(Configuration::get('MT_DISPLAY_DESCRIPTION')) 
    		)
    	);
    	$snapFullpayment = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
    	$snapFullpayment->setCallToActionText($this->l(Configuration::get('MT_DISPLAY_TITLE')))
    					->setAction($this->context->link->getModuleLink($this->name, 'validation17', array(), true))
    					->setAdditionalInformation($this->context->smarty->fetch('module:'.$this->name.'/views/templates/front/payment_infos.tpl')); // TODO implement payment_infos.tpl
    					// ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/logo.png'));
    	return $snapFullpayment;
    }

    public function getSnapMigsOption()
    {
    	$paymentOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
    	$paymentOption->setCallToActionText($this->l(Configuration::get('MT_TITLE_MIGS_BTN')))
    					->setAction($this->context->link->getModuleLink($this->name, 'validation17',['feature' => 'MT_ENABLED_MIGS_BTN'] , true))
    					->setAdditionalInformation($this->context->smarty->fetch('module:'.$this->name.'/views/templates/front/payment_infos.tpl'));
    					// ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/logo.png'));
    	return $paymentOption;
    }

    public function getSnapPromoOption()
    {
    	$paymentOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
    	$paymentOption->setCallToActionText($this->l(Configuration::get('MT_TITLE_PROMO_BTN')))
    					->setAction($this->context->link->getModuleLink($this->name, 'validation17',['feature' => 'MT_ENABLED_PROMO_BTN'] , true))
    					->setAdditionalInformation($this->context->smarty->fetch('module:'.$this->name.'/views/templates/front/payment_infos.tpl'));
    					// ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/logo.png'));
    	return $paymentOption;
    }

    public function getSnapInstallmentMigsOption()
    {
    	$paymentOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
    	$paymentOption->setCallToActionText($this->l(Configuration::get('MT_TITLE_INSTALLMENTMIGS_BTN')))
    					->setAction($this->context->link->getModuleLink($this->name, 'validation17',['feature' => 'MT_ENABLED_INSTALLMENTMIGS_BTN'] , true))
    					->setAdditionalInformation($this->context->smarty->fetch('module:'.$this->name.'/views/templates/front/payment_infos.tpl'));
    					// ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/logo.png'));
    	return $paymentOption;
    }

    public function getSnapInstallmentOnlineOption()
    {
    	$paymentOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
    	$paymentOption->setCallToActionText($this->l(Configuration::get('MT_TITLE_INSTALLMENTON_BTN')))
    					->setAction($this->context->link->getModuleLink($this->name, 'validation17',['feature' => 'MT_ENABLED_INSTALLMENTON_BTN'] , true))
    					->setAdditionalInformation($this->context->smarty->fetch('module:'.$this->name.'/views/templates/front/payment_infos.tpl'));
    					// ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/logo.png'));
    	return $paymentOption;
    }

    public function getSnapInstallmentOfflineOption()
    {
    	$paymentOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
    	$paymentOption->setCallToActionText($this->l(Configuration::get('MT_TITLE_INSTALLMENTOFF_BTN')))
    					->setAction($this->context->link->getModuleLink($this->name, 'validation17',['feature' => 'MT_ENABLED_INSTALLMENTOFF_BTN'] , true))
    					->setAdditionalInformation($this->context->smarty->fetch('module:'.$this->name.'/views/templates/front/payment_infos.tpl'));
    					// ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/logo.png'));
    	return $paymentOption;
    }

    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }

        $this->smarty->assign(array(
            'shop_name' => $this->context->shop->name,
        ));

        return $this->fetch('module:'.$this->name.'/views/templates/hook/payment_return.tpl');
    }

	public function hookDisplayPayment($params)
	{
		if (!$this->active)
			return;

		if (!$this->checkCurrency($params['cart']))
			return;
		$cart = $this->context->cart;

		// error_log( $cart->getOrderTotal(). " ### " . Configuration::get('MT_MINAMOUNT')); // debugan
		// check if gross amount is above installment amount threshold && installment is enabled
		$installment_note = '';
		$installmentEnabled = false;
		if ( Configuration::get('MT_ENABLED_INSTALLMENTON_BTN') || Configuration::get('MT_ENABLED_INSTALLMENTOFF_BTN') || Configuration::get('MT_ENABLED_INSTALLMENTMIGS_BTN') ){
			$installmentEnabled = true; }
		if ($installmentEnabled && $cart->getOrderTotal() >= Configuration::get('MT_MINAMOUNT')) {
			$installment_note = 'available'; }
		else if ($installmentEnabled){
			$installment_note = 'unavailable'; }

		$this->context->smarty->assign(array(
			'cart' => $cart,
			'MT_DISPLAY_TITLE' => Configuration::get('MT_DISPLAY_TITLE'),
			'installment_note' => $installment_note,
			'MT_ENABLED_MIGS_BTN' => Configuration::get('MT_ENABLED_MIGS_BTN'),
			'MT_TITLE_MIGS_BTN' => Configuration::get('MT_TITLE_MIGS_BTN'),
			'MT_DISABLE_NON_MIGS_BTN' => Configuration::get('MT_DISABLE_NON_MIGS_BTN'),
			'MT_ENABLED_INSTALLMENTMIGS_BTN' => Configuration::get('MT_ENABLED_INSTALLMENTMIGS_BTN'),
			'MT_TITLE_INSTALLMENTMIGS_BTN' => Configuration::get('MT_TITLE_INSTALLMENTMIGS_BTN'),
			'MT_ENABLED_INSTALLMENTOFF_BTN' => Configuration::get('MT_ENABLED_INSTALLMENTOFF_BTN'),
			'MT_TITLE_INSTALLMENTOFF_BTN' => Configuration::get('MT_TITLE_INSTALLMENTOFF_BTN'),
			'MT_ENABLED_INSTALLMENTON_BTN' => Configuration::get('MT_ENABLED_INSTALLMENTON_BTN'),
			'MT_TITLE_INSTALLMENTON_BTN' => Configuration::get('MT_TITLE_INSTALLMENTON_BTN'),
			'MT_ENABLED_PROMO_BTN' => Configuration::get('MT_ENABLED_PROMO_BTN'),
			'MT_ENABLED_CUSTOMVA_BTN' => Configuration::get('MT_ENABLED_CUSTOMVA_BTN'),
			'MT_LIST_CUSTOMVA' => explode(',', Configuration::get('MT_LIST_CUSTOMVA')),
			// 'MT_LIST_CUSTOMVA' => ['permata','bca','other_va'],
			'MT_TITLE_PROMO_BTN' => Configuration::get('MT_TITLE_PROMO_BTN'),
			'MT_MINAMOUNT' => Configuration::get('MT_MINAMOUNT'),
			'this_path' => $this->_path,
			'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/'
		));

		// 1.4 compatibility
		if (version_compare(Configuration::get('PS_VERSION_DB'), '1.5') == -1) {
			return $this->display(__FILE__, 'views/templates/hook/payment.tpl');
		} else
		{
			return $this->display(__FILE__, 'payment.tpl');	
		}
	}

	public function hookOrderConfirmation($params)
	{
		if (!$this->active)
			return;

		if (!$this->checkCurrency($params['cart']))
			return;

		$order = new Order(Tools::getValue('id_order'));
		$history = $order->getHistory($this->context->cookie->id_lang);	

		$history = $history[0];

		$this->context->smarty->assign(array(
			'transaction_status' => $history['id_order_state'],
			'cart' => $this->context->cart,
			'this_path' => $this->_path,
			'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/'
		));

		if (version_compare(Configuration::get('PS_VERSION_DB'), '1.5') == -1) {
			return $this->display(__FILE__, 'views/templates/hook/order_confirmation.tpl');
		} else
		{
			return $this->display(__FILE__, 'order_confirmation.tpl');	
		}
	}
	
	public function checkCurrency($cart)
	{
		$currency_order = new Currency($cart->id_currency);
		$currencies_module = $this->getCurrency($cart->id_currency);

		if (is_array($currencies_module))
			foreach ($currencies_module as $currency_module)
				if ($currency_order->id == $currency_module['id_currency'])
					return true;
		return false;
	}

	// Retrocompatibility 1.4/1.5
	private function initContext()
	{
	  if (class_exists('Context'))
	    $this->context = Context::getContext();
	  else
	  {
	    global $smarty, $cookie;
	    $this->context = new StdClass();
	    $this->context->smarty = $smarty;
	    $this->context->cookie = $cookie;
	    if (array_key_exists('cart', $GLOBALS))
	    {
	    	global $cart;
	    	$this->context->cart = $cart;
	    }
	    if (array_key_exists('link', $GLOBALS))
	    {
	    	global $link;
	    	$this->context->link = $link;
	    }
	  }
	}

	// Retrocompatibility 1.4
	public function execPayment($cart)
	{
		if (!$this->active)
			return ;
		if (!$this->checkCurrency($cart))
			Tools::redirectLink(__PS_BASE_URI__.'order.php');

		$link = new Link();

		global $cookie, $smarty;

		$smarty->assign(array(
			'payment_type' => Configuration::get('MT_PAYMENT_TYPE'),
			'api_version' => Configuration::get('MT_API_VERSION'),
			'error_message' => '',
			'link' => $link,
			'nbProducts' => $cart->nbProducts(),
			'cust_currency' => $cart->id_currency,
			'currencies' => $this->getCurrency((int)$cart->id_currency),
			'total' => $cart->getOrderTotal(true, Cart::BOTH),
			'this_path' => $this->_path,
			'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.((int)Configuration::get('PS_REWRITING_SETTINGS') && count(Language::getLanguages()) > 1 && isset($smarty->ps_language) && !empty($smarty->ps_language) ? $smarty->ps_language->iso_code.'/' : '').'modules/'.$this->name.'/'
		));

		return $this->display(__FILE__, 'views/templates/front/payment_execution.tpl');
	}
//
	public function getTermInstallment($name_bank){
		$ans = array();
		foreach ($this->config_keys as $key) {
			if ( (strpos($key, 'MT_INSTALLMENTS_' . $name_bank ) !== FALSE) && (Configuration::get($key) == 'on') ){
				
				$term = Configuration::get('MT_INSTALLMENTS_'.$name_bank);
				
				$key_array = explode('_', $key);
				//error_log($key); // debug
				//error_log(print_r($key_array,true)); // debug
				$ans[] = $key_array[3];
				//error_log($key_array[3]); // debug
			}
    		
		}
		//return $ans;
		return $term2;
	}

	// Retrocompatibility 1.4
	public function execValidation($cart)
	{
		// error_log( 'execValidation $_GET[] = ' . print_r($_GET,true) ); // debugan
		global $cookie;
		$customer = new Customer($cart->id_customer);

		$veritrans = new Veritrans_Config();
		// Config setup
		Veritrans_Config::$serverKey = Configuration::get('MT_SERVER_KEY');
		Veritrans_Config::$isProduction = Configuration::get('MT_ENVIRONMENT') == 'production' ? true : false;
    	if (Configuration::get('MT_3D_SECURE') == 'on' || Configuration::get('MT_3D_SECURE') == 1)
			Veritrans_Config::$is3ds = true;
		Veritrans_Config::$isSanitized = true;
		$url = Veritrans_Config::getBaseUrl(); 

		$usd = Configuration::get('MT_KURS');
		$shipping_cost = $cart->getTotalShippingCost();

		$currency = new Currency($cookie->id_currency);
		$total = $cart->getOrderTotal(true, Cart::BOTH);
		
		$mailVars = array();
				
		$billing_address = new Address($cart->id_address_invoice);
		$delivery_address = new Address($cart->id_address_delivery);
		
		// Build billing address param
    	$params_billing_address = array(
    			'first_name' => (string)$billing_address->firstname, 
				'last_name' => (string)$billing_address->lastname, 
				'address' => (string)$billing_address->address1,
				'city' => (string)$billing_address->city, 
				'postal_code' => (string)$billing_address->postcode, 
				'phone' => (string)$this->determineValidPhone($billing_address->phone, $billing_address->phone_mobile), 
				'country_code' => 'IDN'
    		);

		if($cart->isVirtualCart()) {
			// Don't need to add shipping address, do nothing
		} else {
			if ($cart->id_address_delivery != $cart->id_address_invoice)
			{
				// Build shipping address param
				$params_shipping_address = array(
					'first_name' => (string)$delivery_address->firstname, 
					'last_name' => (string)$delivery_address->lastname, 
					'address' => (string)$delivery_address->address1,
					'city' => (string)$delivery_address->city,
					'postal_code' => (string)$delivery_address->postcode,
					'phone' => (string)$this->determineValidPhone($delivery_address->phone, $delivery_address->phone_mobile), 
					'country_code' => 'IDN'
					);																								
			} else
			{
				$params_shipping_address = $params_billing_address;
			}
		}  
    	
    	// Build customer details param
		$params_customer_details = array(
			'first_name' => (string)$billing_address->firstname, 
			'last_name' =>  (string)$billing_address->lastname, 
			'email' => (string)$customer->email, 
			'phone' => (string)$this->determineValidPhone($billing_address->phone, $billing_address->phone_mobile), 
			'billing_address' => $params_billing_address, 
			'shipping_address' => $params_shipping_address
			);

		$items = $this->addCommodities($cart, $shipping_cost, $usd);
		
		// convert the currency
		$cart_currency = new Currency($cart->id_currency);
		if ($cart_currency->iso_code != 'IDR') {
			// check whether if the IDR is installed or not
			if (Currency::exists('IDR', null)) {
				// use default rate
				$conversion_func = function($input) use($cart_currency) { return Tools::convertPriceFull($input, $cart_currency, new Currency(Currency::getIdByIsoCode('IDR'))); };
			} else {
				// use rate
				$conversion_func = function($input) { return $input * intval(Configuration::get('MT_KURS')); };
			}

			foreach ($items as &$item) {						
				$item['price'] = intval(ceil(call_user_func($conversion_func, $item['price'])));				
			}
		}else if($cart_currency->iso_code == 'IDR') {
			foreach ($items as &$item) {						
				$item['price'] = intval(ceil($item['price']));				
			}
		}
		
		$gross_amount = 0;
		unset($item);
		foreach ($items as $item) {				
			$gross_amount += $item['price'] * $item['quantity'];
		}	
		
		$isBniInstallment = Configuration::get('MT_ENABLED_BNI_INSTALLMENT') == 1;
		$isMandiriInstallment = Configuration::get('MT_ENABLED_MANDIRI_INSTALLMENT') == 1;
		$warning_redirect = false;
		$fullPayment = true;

		$params_all = array(
			'transaction_details' => array(
				'order_id' => $cart->id, 
				'gross_amount' => $gross_amount
				),
			'item_details' => $items,
			'customer_details' => $params_customer_details
			);

		if( !$warning_redirect && 
			($isBniInstallment || $isMandiriInstallment) && 
			(!$fullPayment)  ){

			$params_all['vtweb']['payment_options'] = $param_payment_option;		
		}

	   	/** 
	    * Add additional features param
	    */
		
		// Promo payment, coupled with validation17.php->addPromoFeature()
		if (isset($_GET['feature']) && $_GET['feature'] == 'MT_ENABLED_PROMO_BTN' && Configuration::get('MT_ENABLED_PROMO_BTN') == 1) {
			$params_all = $this->addPromoParam($params_all);
		}

	    // MIGS CC fullpayment
	    if (isset($_GET['feature']) && $_GET['feature'] == 'MT_ENABLED_MIGS_BTN' && Configuration::get('MT_ENABLED_MIGS_BTN') == 1) {
	    	$params_all = $this->addMIGSFullpaymentParam($params_all);
	    }

	    // Check for eligible installment, then add isntallment param
	    if (isset($_GET['feature']) && $gross_amount >= Configuration::get('MT_MINAMOUNT')){
	    	$params_all = $this->addInstallmentParam($params_all);
	    }

	    // Custom VA Button
	   	if (isset($_GET['feature']) && $_GET['feature'] == 'MT_ENABLED_CUSTOMVA_BTN' && Configuration::get('MT_ENABLED_CUSTOMVA_BTN') == 1) {
			$params_all = $this->addCustomVAparam($params_all,$_GET['bank']);
		}
		
	    // Add custom expiry params
	    if (Configuration::get('MT_ENABLED_EXPIRY') == 1){
	    	$time = time();
	    	$time += 30; // add 30 seconds to allow margin of error
	    	// error_log(date("Y-m-d h:i:s O",$time)); // debugan
	    	$params_all['expiry'] = array(
	    			'start_time' => date("Y-m-d H:i:s O",$time), 
	    			'unit' => Configuration::get('MT_EXPIRY_UNIT'), 
	    			'duration'  => Configuration::get('MT_EXPIRY_DURATION'),
	    		);
	    }

	    // Add custom fields params
	    if (Configuration::get('MT_ENABLED_FIELDS') == 1){
	    	$custom_fields_params = explode(",",Configuration::get('MT_FILEDS'));
			$params_all['custom_field1'] = !empty($custom_fields_params[0]) ? $custom_fields_params[0] : null;
			$params_all['custom_field2'] = !empty($custom_fields_params[1]) ? $custom_fields_params[1] : null;
			$params_all['custom_field3'] = !empty($custom_fields_params[2]) ? $custom_fields_params[2] : null;	
	    }

	    // Add savecard params
	    if (Configuration::get('MT_ENABLED_SAVECARD') == 1 && $this->context->customer->isLogged()){
	    	$params_all['user_id'] = crypt($params_customer_details['email'].$params_customer_details['phone'],Configuration::get('MT_SERVER_KEY'));
	    	$params_all['credit_card']['save_card'] = true;
	    }

	    // error_log(print_r($params_all,true)); // debugan

		// Get SNAP token, then create redirect url
		try {
		    // error_log(print_r($params_all,true)); // debug
		    $this->validateOrder($cart->id, Configuration::get('MT_ORDER_STATE_ID'), $cart->getOrderTotal(true, Cart::BOTH), $this->displayName, NULL, $mailVars, (int)$currency->id, false, $customer->secure_key);
		    $params_all['transaction_details']['order_id'] = $this->currentOrder; // OrderID is only available after validateOrder
		  	$snapToken = Veritrans_Snap::getSnapToken($params_all);
		  	$redirect_url= $this->context->link->getModuleLink($this->name,'snappay',['snap_token' => $snapToken]);
		  	// error_log("redirect_url :".$redirect_url); // debug
		  	$keys['redirect_url'] = $redirect_url;
		} catch (Exception $e) {
		  	$keys['errors'] = $e->getMessage();
		  	echo $e->getMessage();
		}

		return $keys;
	}

	public function addInstallmentParam($params_all)
	{
		if (!isset($_GET['feature'])
			|| !($_GET['feature'] == 'MT_ENABLED_INSTALLMENTMIGS_BTN' || 
				$_GET['feature'] == 'MT_ENABLED_INSTALLMENTOFF_BTN'  ||
				$_GET['feature'] == 'MT_ENABLED_INSTALLMENTON_BTN' )
			)
			return $params_all;

    	$params_all['enabled_payments'][] = 'credit_card';
		$params_all['credit_card']['installment']['required'] = true;
		// Build terms array
		$terms = array(3,6,9,12,15,18,21,24,27,30,33,36);
		
    	// MIGS Installment
	    if ($_GET['feature'] == 'MT_ENABLED_INSTALLMENTMIGS_BTN' && Configuration::get('MT_ENABLED_INSTALLMENTMIGS_BTN') == 1) {
	    	// add bank & channel migs params
	        if (strlen(Configuration::get('MT_ACQ_INSTALLMENTMIGS_BTN')) > 0) {
	        	$params_all['credit_card']['bank'] = strtoupper (Configuration::get('MT_ACQ_INSTALLMENTMIGS_BTN')); }
	        $params_all['credit_card']['channel'] = "migs";
			
			// add bin params
			if (strlen(Configuration::get('MT_BINS_INSTALLMENTMIGS_BTN')) > 0) {
				$params_all['credit_card']['whitelist_bins'] = explode(',', Configuration::get('MT_BINS_INSTALLMENTMIGS_BTN')); }

			// Build installment param
			$params_all['credit_card']['installment']['terms'] = 
			array(
			  'bri' => $terms, 
			  // 'danamon' => $terms, 
			  'maybank' => $terms, 
			  // 'bni' => $terms, 
			  // 'mandiri' => $terms, 
			  // 'cimb' => $terms,
			  'bca' => $terms
			);
	    }
		
    	// Offline Installment
	    if ($_GET['feature'] == 'MT_ENABLED_INSTALLMENTOFF_BTN' && Configuration::get('MT_ENABLED_INSTALLMENTOFF_BTN') == 1) {
			// add bin params
			if (strlen(Configuration::get('MT_BINS_INSTALLMENTOFF_BTN')) > 0) {
				$params_all['credit_card']['whitelist_bins'] = explode(',', Configuration::get('MT_BINS_INSTALLMENTOFF_BTN')); }
			$termsOffline = array(6,12);
			if (strlen(Configuration::get('MT_TERM_INSTALLMENTOFF_BTN')) > 0) {
				$termsOffline = array_map('intval', explode(',', Configuration::get('MT_TERM_INSTALLMENTOFF_BTN')) ); }

			// Build installment param
			$params_all['credit_card']['installment']['terms'] = 
			array(
			  'offline' => $termsOffline
			);
			error_log( print_r($params_all,true) );
		}
		
    	// Online Installment
	    if ($_GET['feature'] == 'MT_ENABLED_INSTALLMENTON_BTN' && Configuration::get('MT_ENABLED_INSTALLMENTON_BTN') == 1) {
			// add bin params
			if (strlen(Configuration::get('MT_BINS_INSTALLMENTON_BTN')) > 0) {
				$params_all['credit_card']['whitelist_bins'] = explode(',', Configuration::get('MT_BINS_INSTALLMENTON_BTN')); }

			// Build installment param
			$params_all['credit_card']['installment']['terms'] = 
			array(
			  // 'bri' => $terms, 
			  'danamon' => $terms, 
			  // 'maybank' => $terms, 
			  'bni' => $terms, 
			  'mandiri' => $terms, 
			  'cimb' => $terms
			  // 'bca' => $terms
			);
	    }

		return $params_all;
	}

	public function addMIGSFullpaymentParam($params_all)
	{
	    if (isset($_GET['feature']) && $_GET['feature'] == 'MT_ENABLED_MIGS_BTN' && Configuration::get('MT_ENABLED_MIGS_BTN') == 1) {
	        // $params_all['enabled_payments'][] = 'credit_card';
	    	
	    	// add bank & channel migs params
	        if (strlen(Configuration::get('MT_ACQ_MIGS_BTN')) > 0) {
	        	$params_all['credit_card']['bank'] = strtoupper (Configuration::get('MT_ACQ_MIGS_BTN')); 
	        }else{
	        	$params_all['credit_card']['bank'] = "BCA";
	        }
	        $params_all['credit_card']['channel'] = "migs";
			
			// add bin params
			if (strlen(Configuration::get('MT_BINS_MIGS_BTN')) > 0) {
				$params['credit_card']['whitelist_bins'] = explode(',', Configuration::get('MT_BINS_MIGS_BTN')); }
	    }

		return $params_all;
	}

	public function addPromoParam($params_all)
	{
		if (!isset($_GET['feature']))
			return $params_all;

    	$params_all['enabled_payments'][] = 'credit_card';

		// Promo Payment
	    if ($_GET['feature'] == 'MT_ENABLED_PROMO_BTN' && Configuration::get('MT_ENABLED_PROMO_BTN') == 1) {
			// add bin params
			if (strlen(Configuration::get('MT_BINS_PROMO_BTN')) > 0) {
				$params_all['credit_card']['whitelist_bins'] = explode(',', Configuration::get('MT_BINS_PROMO_BTN')); }

			// add payment method
			if (strlen(Configuration::get('MT_METHOD_PROMO_BTN')) > 0) {
				$params_all['enabled_payments'] = explode(',', Configuration::get('MT_METHOD_PROMO_BTN')); }
	    }

		return $params_all;
	}

	public function addCustomVAparam($params_all,$bank)
	{
		$bank_mapping = array(
			'permata' => 'permata_va', 
			'bca' => 'bca_va', 
			'mandiri' => 'echannel',
			'other_va' => 'other_va,permata_va'
		);
    	$params_all['enabled_payments'] = explode(',', $bank_mapping[$bank]);
		return $params_all;
	}

	public function setMedia()
	{
		Tools::addJs('function onloadEvent() { document.form_auto_post.submit(); }');
	}

	public function addCommodities($cart, $shipping_cost, $usd)
	{
		
		$products = $cart->getProducts();
		$discount = -1 * $cart->getOrderTotal(true, Cart::ONLY_DISCOUNTS);

		if (version_compare(Configuration::get('PS_VERSION_DB'), '1.5') == -1){ // for 1.4 version, voucher is negative
			$discount *= -1;
		}

		$commodities = array();
		$price = 0;

		foreach ($products as $aProduct) {
			//error_log('detail product'); // debug
			//error_log(print_r($aProduct,true)); // debug
			$commodities[] = array(
				"id" => $aProduct['id_product'],
				"price" =>  $aProduct['price_wt'],
				"quantity" => $aProduct['cart_quantity'],
				"name" => $aProduct['name']				
			);
		}

		if($shipping_cost != 0){
			$commodities[] = array(
				"id" => 'SHIPPING_FEE',
				"price" => $shipping_cost, // defer currency conversion until the very last time
				"quantity" => '1',
				"name" => 'Shipping Cost',				
			);			
		}
		
		if($discount != 0){
			$commodities[] = array(
				"id" => 'DISCOUNT_VOUCHER',
				"price" => $discount, // defer currency conversion until the very last time
				"quantity" => '1',
				"name" => 'Discount from promo',				
			);	
		}
		//error_log(print_r($commodities,true)); // debug
		return $commodities;
	}

	function insertTransaction($customer_id, $id_cart, $id_currency, $request_id, $token_merchant)
	{
		$sql = 'INSERT INTO `'._DB_PREFIX_.'mt_transaction`
				(`id_customer`, `id_cart`, `id_currency`, `request_id`, `token_merchant`)
				VALUES ('.(int)$customer_id.',
					'.(int)$id_cart.',
					'.(int)$id_currency.',
						\''.$request_id.'\',
						\''.$token_merchant.'\')';
		Db::getInstance()->Execute($sql);
	}

	function getTransaction($request_id)
	{
		$sql = 'SELECT *
			FROM `'._DB_PREFIX_.'mt_transaction`
			WHERE `request_id` = \''.$request_id.'\'';
		$result = Db::getInstance()->getRow($sql);
		return $result; 
	}

	// determine the phone number to make Midtrans happy
	function determineValidPhone($home_phone = '', $mobile_phone = '')
	{
		if (empty($home_phone) && !empty($mobile_phone))
		{
			return $mobile_phone;
		} else if (!empty($home_phone) && empty($mobile_phone))
		{
			return $home_phone;
		} else if (!empty($home_phone) && !empty($mobile_phone))
		{
			return $mobile_phone;
		} else
		{
			return '081111111111';
		}
	}

	// Response early with 200 OK status for Midtrans notification & handle HTTP GET
	public function earlyResponse(){
		if ( $_SERVER['REQUEST_METHOD'] == 'GET' ){
			die('This endpoint should not be opened using browser (HTTP GET). This endpoint is for Midtrans notification URL (HTTP POST)');
			exit();
		}

		ob_start();

		$input_source = "php://input";
		$raw_notification = json_decode(file_get_contents($input_source), true);
		echo "<pre> \n";
		echo "Notification Received: \n";
		print_r($raw_notification);
		echo "</pre> \n";
		
		header('Connection: close');
		header('Content-Length: '.ob_get_length());
		ob_end_flush();
		ob_flush();
		flush();
	}


	public function execNotification()
	{
		$veritrans = new Veritrans_Config();
		
		Veritrans_Config::$isProduction = Configuration::get('MT_ENVIRONMENT') == 'production' ? true : false;
		Veritrans_Config::$serverKey = Configuration::get('MT_SERVER_KEY');

		// Response first, then try to create notification object from post notif
		$this->earlyResponse();
		$midtrans_notification = new Veritrans_Notification();

		// $midtrans_notification = new Veritrans_Notification();
		$history = new OrderHistory();
		$history->id_order = (int)$midtrans_notification->order_id;

		// error_log('message notif'); // debug
		// error_log(print_r($midtrans_notification,TRUE)); // debug
		// error_log('=============================================='); // debug
		
		// check if order history already been updated to payment success, then save to array $order_history.
		$order_id_notif = (int)$midtrans_notification->order_id;
		$order = new Order($order_id_notif);
		$order_histories = $order->getHistory($this->context->language->id, Configuration::get('MT_PAYMENT_SUCCESS_STATUS_MAP') );
		// if (empty($order_histories))  // debug
		// 	error_log("not found in DB");  // debug
		// error_log(print_r($order_histories,true));  // debug
		// print_r($order_histories,true);  // debug

		//Validating order
		//if ($midtrans_notification->isVerified())
		//{
		  	//$history->id_order = (int)$midtrans_notification->order_id;		  	
			//error_log('notif verified'); // debug
			//error_log('message notif: '.(int)$midtrans_notification->order_id); // debug
			if ($midtrans_notification->transaction_status == 'capture'){
		     	if ($midtrans_notification->fraud_status== 'accept'){
		     		// if order history !contains payment accepted, then update DB. Else, don't update DB
		     		if (empty($order_histories)){
			       		$history->changeIdOrderState(Configuration::get('MT_PAYMENT_SUCCESS_STATUS_MAP'), $order_id_notif);
			       		echo 'Valid success notification accepted.';
		       		} else{
		       			error_log("########## Transaction has already been updated to success status once, no need to update again"); // debug
		       		}
		       	} else if ($midtrans_notification->fraud_status== 'challenge'){
		       		$history->changeIdOrderState(Configuration::get('MT_PAYMENT_CHALLENGE_STATUS_MAP'), $order_id_notif);
		       		echo 'Valid challenge notification accepted.';
		     	} 		       	
		     } else if ($midtrans_notification->transaction_status == 'settlement'){

		     	if($midtrans_notification->payment_type != 'credit_card'){
		     		// if order history !contains payment accepted, then update DB. Else, don't update DB
		     		if (empty($order_histories)){
				     	$history->changeIdOrderState(Configuration::get('MT_PAYMENT_SUCCESS_STATUS_MAP'), $order_id_notif);
				       	echo 'Valid success notification accepted.';
				    }else{
		       			error_log("########## Transaction has already been updated to success status once, no need to update again"); // debug
		       		}
		     	}
		     	else{
		     		echo 'Credit card settlement notification accepted.';	
		     	}

		     }else if ($midtrans_notification->transaction_status == 'pending'){
		     	$history->changeIdOrderState(Configuration::get('MT_PAYMENT_CHALLENGE_STATUS_MAP'), $order_id_notif);
		       	echo 'Valid Pending notification accepted.';
		     }else if ($midtrans_notification->transaction_status == 'cancel'){
		     	$history->changeIdOrderState(Configuration::get('MT_PAYMENT_FAILURE_STATUS_MAP'), $order_id_notif);
		       	echo 'Valid Cancel notification accepted.';
		     }else if ($midtrans_notification->transaction_status == 'deny'){
		     	$history->changeIdOrderState(Configuration::get('MT_PAYMENT_FAILURE_STATUS_MAP'), $order_id_notif);
		       	echo 'Valid Deny notification accepted.';
		     }else if ($midtrans_notification->transaction_status == 'expire'){
		     	$history->changeIdOrderState(Configuration::get('MT_PAYMENT_FAILURE_STATUS_MAP'), $order_id_notif);
		       	echo 'Valid Expire notification accepted.';
		     }else{
		       if(Configuration::get('MT_ENABLED_IGNORE_DENY' == 1)){exit;return;}
		       $history->changeIdOrderState(Configuration::get('MT_PAYMENT_FAILURE_STATUS_MAP'), $order_id_notif);
		       echo 'Valid failure notification accepted';
		     }

		    try{
		    	$history->add(true);		     			  
		    } catch(Exception $e) {
				echo 'Order history not added: ' .$e->getMessage();
				exit;
			}
		//}
		exit;
	}
}
