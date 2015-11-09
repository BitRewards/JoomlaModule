<?php

defined('_JEXEC') or die('Direct Access not allowed.');

require_once JPATH_ROOT . '/plugins/system/giftd/lib/GiftdApiClient.php';

use Joomla\Registry\Registry;

class PlgSystemGiftd extends JPlugin
{
	private $options;

	public function __construct (& $subject, $config)
	{
		parent::__construct($subject, $config);

		$shop = $this->params->get('shop', 'virtuemart');

		require_once JPATH_ROOT . '/plugins/system/giftd/includes/'.$shop.'.php';

		$this->options = array(
			'com_virtuemart', 'com_hikashop', 'com_jshopping',
		);
	}

	public function onExtensionBeforeSave($context, $table, $isNew)
	{
		if(!($context == 'com_plugins.plugin' && $table->element == 'giftd'))
		{
			return true;
		}
		$app = JFactory::getApplication();
		$input = $app->input;
		$data = $input->get('jform', array(), 'array');
		$params = $data['params'];
		$userId = $this->params->get('user_id', '');
		$apiKey = $this->params->get('api_key', '');

		if((empty($params['api_key']) && !empty($userId)) || (empty($params['api_key']) && !empty($apiKey))){
			$client = new Giftd_Client($userId, $apiKey);
			$client->query("joomla/uninstall");
			return true;
		}

		if((!empty($params['api_key']) && $params['api_key'] == $this->params->get('api_key')) || empty($params['user_id'])){
			return true;
		}

		$user = JFactory::getUser();
		$jconfig = \JFactory::getConfig();

		$data = array(
			'email' => $user->get('email', ''),
			'phone' => '',
			'name' => $user->get('name', ''),
			'url' => JUri::root(),
			'title' => $jconfig->get('sitename', ''),
			'joomla_version' => JVERSION
		);

		$this->loadShopData($data);


		$client = new Giftd_Client($params['user_id'], $params['api_key']);
		$result = $client->query("joomla/install", $data);

		if($result['type'] == 'data')
		{
			if(!empty($result['data']))
			{
				$requestData = $result['data'];
				if(empty($requestData['partner_token_prefix']) && empty($requestData['partner_code']))
				{
					if(!empty($requestData['code'])){
						$app->setUserState('plugins.system.giftd.code', $requestData['code']);
					}
					if(!empty($requestData['token_prefix'])){
						$app->setUserState('plugins.system.giftd.token_prefix', $requestData['token_prefix']);
					}
				}
			}

		}
		else
		{
			$app->enqueueMessage(JText::_('PLG_SYSTEM_GIFT_ERROR_QUERY'), 'error');
		}
		return true;
	}

	public function onExtensionAfterSave($context, $table, $isNew)
	{
		if(!($context == 'com_plugins.plugin' && $table->element == 'giftd'))
		{
			return true;
		}
		$app = JFactory::getApplication();
		$code = $app->getUserState('plugins.system.giftd.code', '');
		$token_prefix = $app->getUserState('plugins.system.giftd.token_prefix', '');
		$app->setUserState('plugins.system.giftd.code', '');
		$app->setUserState('plugins.system.giftd.token_prefix', '');

		if(!empty($code) || !empty($token_prefix))
		{
			$db = JFactory::getDbo();
			$query = $db->getQuery(true);
			$query->select($db->quoteName('params'))
				->from($db->quoteName('#__extensions'))
				->where($db->quoteName('element') . ' = ' . $db->quote('giftd'))
				->where($db->quoteName('type') . ' = ' . $db->quote('plugin'));

			$params = new Registry($db->setQuery($query,0,1)->loadResult());

			if(!empty($code))
				$params->set('partner_code', $code);

			if(!empty($token_prefix))
				$params->set('partner_token_prefix', $token_prefix);


			$query->clear()->update($db->quoteName('#__extensions'));
			$query->set($db->quoteName('params') . '= ' . $db->quote((string)$params));
			$query->where($db->quoteName('element') . ' = ' . $db->quote('giftd'));
			$query->where($db->quoteName('type') . ' = ' . $db->quote('plugin'));
			$db->setQuery($query);
			$db->execute();
		}

		return true;
	}

	public function onAfterRender()
	{
		if(JFactory::getApplication()->input->getInt('giftd-update-js', 0) == 1){
			$this->updateJavaScript();
		}
	}

	public function onAfterRoute()
	{
		$app = JFactory::getApplication();
		$input = $app->input;
		if(!$app->isAdmin() && in_array($input->getCmd('option',''), $this->options)){
			ob_start();
			require JPATH_ROOT . '/plugins/system/giftd/assets/js/giftd.js';
			$js = ob_get_clean();
			JFactory::getDocument()->addScriptDeclaration($js);
		}
	}

    //Virtuemart create Coupon
	public function plgVmValidateCouponCode($_code, $_billTotal)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('coupon_code')
			->from('#__virtuemart_coupons')
			->where('coupon_code = '.$db->quote($_code));
		$result = $db->setQuery($query,0,1)->loadResult();

		if($result == $_code){
			return null;
		}

        $card = $this->getGiftCard($_code);

        if(is_null($card)){
            return null;
        }

		$now = new JDate();
		$now = $now->toSql();
		$created = new JDate($card->created);
		$created = $created->toSql();
		$expires = new JDate($card->expires);
		$expires = $expires->toSql();
		$user = $this->params->get('user', 0);
		$type = $card->charge_type == 'multiple' ? 'permanent' : 'gift';

		$object = new stdClass();
		$object->virtuemart_vendor_id = $this->params->get('virtuemart_vendor_id', 0);
		$object->coupon_code = $_code;
		$object->percent_or_total = 'total';
		$object->coupon_type = $type;
		$object->coupon_value = $card->amount_available;
		$object->coupon_start_date = $created;
		$object->coupon_expiry_date = $expires;
		$object->coupon_value_valid = (float)$card->min_amount_total;
		$object->coupon_used = '0';
		$object->published = 1;
		$object->created_on = $now;
		$object->created_by = $user;
		$object->modified_on = $now;
		$object->modified_by = $user;
		$object->locked_on = '0000-00-00 00:00:00';
		$object->locked_by = '0';
		$db->insertObject('#__virtuemart_coupons', $object);
		return null;
	}

    //Virtuemart use Coupon
	public function plgVmCouponInUse($code){

		$input = JFactory::getApplication()->input;
		$orderId = $input->getInt('virtuemart_order_id',0);
		$amount = GiftdVirtuemartHelper::getCouponAmount($code);
		$amountTotal = 0;
		if($orderId > 0){
			$db = JFactory::getDbo();
			$query = $db->getQuery(true);
			$query->select('*')
				->from('#__virtuemart_orders')
				->where('virtuemart_order_id = '.$orderId);
			$result = $db->setQuery($query,0,1)->loadObject();
			$amountTotal = !empty($result->order_salesPrice) ? $result->order_salesPrice : 0;
			if(!empty($result->order_billDiscountAmount)){
				$amountTotal += $result->order_billDiscountAmount;
			}
			$amountTotal = round($amountTotal, 2);
		}

        return $this->useCoupon($code, $amount, $amountTotal, $orderId);
	}

	/** Virtuemart on delete from cart
	 * @param $cart
	 * @param $prod_id
	 */
//	public function plgVmOnRemoveFromCart($cart,$prod_id)
//	{
//		echo '<pre>'; var_dump($cart); echo '</pre>'; die;
//		$summ = $cart->cartPrices['salesPrice'];
//		$coupon = '';
//		GiftdVirtuemartHelper::checkVMCoupon($coupon, $summ);
//	}
	/** Virtuemart on refresh cart
	 * @param $virtuemart_paymentmethod_id
	 * @param $paymentCurrency
	 */
	public function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrency)
	{
		$app = JFactory::getApplication();
		$task = $app->input->getCmd('task', '');
		$cart = VirtueMartCart::getCart();
		$code = $cart->couponCode;

		$partner_token_prefix = $this->params->get('partner_token_prefix', '');

		if(empty($code) || empty($partner_token_prefix) || strpos($code, $partner_token_prefix) !== 0)
		{
			return;
		}
//echo '<pre>'; var_dump($_REQUEST); echo '</pre>'; die;
		// task=updatecart
		$summ = $cart->cartPrices['salesPrice'];

		$msg = GiftdVirtuemartHelper::checkVMCoupon($code, $summ);

		if(!empty($msg))
		{
			$cart->couponCode = '';
			$cart->setCartIntoSession();
			$layoutName = $cart->getLayoutUrlString();

			if($task == 'updatecart'){
				$app->redirect('index.php?option=com_virtuemart&view=cart'.$layoutName , $msg);
			}
		}
	}

    //JoomShopping Create coupon
    public function onLoadDiscountSave(){
        $code = JFactory::getApplication()->input->getString('rabatt');

        $coupon = JSFactory::getTable('coupon', 'jshop');
        if ($coupon->getEnableCode($code)){
            return;
        }

        $card = $this->getGiftCard($code);

        if(is_null($card)){
            return;
        }

        $cart = JSFactory::getModel('cart', 'jshop');
        $cart->load();
        $sum = $cart->getPriceBruttoProducts();

        if($sum < $card->min_amount_total){
            return;
        }

        $created = new JDate($card->created);
        $created = $created->toSql();
        $expires = new JDate($card->expires);
        $expires = $expires->toSql();

        $coupon = JSFactory::getTable('coupon', 'jshop');
        $coupon->coupon_type = 1;
        $coupon->coupon_code = $code;
        $coupon->coupon_value = $card->amount_available;
        $coupon->tax_id = 0;
        $coupon->used = 0;
        $coupon->for_user_id = 0;
        $coupon->coupon_start_date = $created;
        $coupon->coupon_expire_date = $expires;
        $coupon->finished_after_used = 1;
        $coupon->coupon_publish = 1;
        $coupon->store();
    }

    //JoomShopping use coupon
    public function onAfterCreateOrder(&$order, &$cart)
    {
        if (!empty($order->coupon_id))
        {
            $coupon = JSFactory::getTable('coupon', 'jshop');
            $coupon->load($order->coupon_id);
            if(!empty($coupon->coupon_code))
            {
                return $this->useCoupon($coupon->coupon_code, $coupon->coupon_value, $order->order_subtotal, $order->order_id);
            }
        }
        return true;
    }

	//JoomShopping check cart summ
	public function onAfterRefreshProductInCart(&$quantity, &$cart)
	{
		if(empty($cart->rabatt_id)){
			return;
		}

		$code = GiftdJoomshoppingHelper::getCouponCodeById($cart->rabatt_id);

		if(is_null($code)){
			return;
		}

		$card = $this->getGiftCard($code);

		if(!isset($card->min_amount_total)){
			return;
		}

		if($cart->price_product >= $card->min_amount_total){
			return;
		}

		GiftdJoomshoppingHelper::deleteCoupon($cart->rabatt_id);

		$cart->rabatt_id = 0;
		$cart->rabatt_value = 0;
		$cart->rabatt_summ = 0;
		$cart->saveToSession();
	}

    //Hikashop Create coupon
    public function onBeforeCouponLoad(&$code, &$do)
    {
        if(!defined('GIFTD_HIKA_CART'))
        {
            define('GIFTD_HIKA_CART', 1);

            if(empty($code))
            {
                return null;
            }

            $couponCode = GiftdHikashopHelper::getCoupon($code);

            if($couponCode == $code){
                return null;
            }

            $card = $this->getGiftCard($code);

            if(is_null($card)){
                return null;
            }

            $sum = GiftdHikashopHelper::getCartSumm();

            if($sum < $card->min_amount_total)
            {
                return null;
            }

            $discountCurrencyId = $this->params->get('discount_currency_id', 1);

            GiftdHikashopHelper::createCoupon($code, $card, $discountCurrencyId);
        }

        $coupon = GiftdHikashopHelper::getCouponData($code);

        if(!is_null($coupon))
        {
            $do = false;
        }

        return $coupon;
    }

	public function onAfterCartUpdate( &$cartClass, &$cart, &$product_id, &$quantity, &$add, &$type, &$resetCartWhenUpdate, &$force, &$updated)
	{
		$coupon = JFactory::getApplication()->input->getString('coupon', '');

		if($cartClass->cart_type != 'cart' || $type != 'item' || !empty($coupon)){
			return;
		}

		$cart_id = $cartClass->cart_type.'_id';
		$keepEmptyCart = false;
		$cartContent = $cartClass->get($cartClass->$cart_id,$keepEmptyCart,$cartClass->cart_type);
		$cartContent = current($cartContent);

		if(empty($cartContent->cart_coupon))
		{
			return;
		}

		$card = $this->getGiftCard($cartContent->cart_coupon);

		if(is_null($card)){
			return null;
		}

		$sum = GiftdHikashopHelper::getCartSumm();

		if($sum >= $card->min_amount_total)
		{
			return;
		}
		GiftdHikashopHelper::deleteCoupon($cartContent->cart_coupon);

	}

    /** Hikashop order
     * @param $order
     * @param $send_email
     * @throws Exception
     */
    public function onAfterOrderCreate(&$order, &$send_email)
    {
        $input = JFactory::getApplication()->input;
        $task = $input->getCmd('task', '');
        $ctrl = $input->getCmd('ctrl', '');
        $option = $input->getCmd('option', '');
        $app = JFactory::getApplication();
        if($option != 'com_hikashop' || !($ctrl == 'checkout' && $task == 'step' && !$app->isAdmin()))
        {
            return;
        }

        $currencyClass = hikashop_get('class.currency');
        $order_total = $order->order_discount_price - $order->order_shipping_price - $order->order_payment_price;

        $coupon = GiftdHikashopHelper::getCouponData($order->order_discount_code);
        $amount = !empty($coupon->discount_flat_amount) ? (float)$coupon->discount_flat_amount : 0;

        $this->useCoupon($order->order_discount_code, $amount, $order->order_discount_price, $order->order_id);
    }
	private function useCoupon($code, $amount, $amountTotal=null, $orderId = null, $comment = null){
		$userId = $this->params->get('user_id', '');
		$apiKey = $this->params->get('api_key', '');
		$partner_token_prefix = $this->params->get('partner_token_prefix', '');

		if(empty($partner_token_prefix) || strpos($code, $partner_token_prefix) !== 0)
		{
			return true;
		}

		$client = new Giftd_Client($userId, $apiKey);

		try
        {
			$data = $client->charge($code, $amount, $amountTotal, $orderId, $comment);
		}
		catch(Exception $e)
        {
            switch($this->params->get('shop', 'virtuemart')){
                case 'hikashop':
                    GiftdHikashopHelper::deleteOrder($orderId);
                    JFactory::getApplication()->redirect(JRoute::_('index.php?option=com_hikashop&ctrl=checkout&task=step&step=1'), $e->getMessage(), 'error');
                    break;
                case 'joomshopping':
                    GiftdJoomshoppingHelper::deleteOrder($orderId);
                    JFactory::getApplication()->redirect(JRoute::_('index.php?option=com_jshopping&controller=cart&task=view'), $e->getMessage(), 'error');
                    break;
                default:
                    GiftdVirtuemartHelper::deleteOrder($orderId);
                    JFactory::getApplication()->redirect(JRoute::_('index.php?option=com_virtuemart&view=cart'), $e->getMessage(), 'error');
                    break;
            }

            return false;
		}

		if(is_null($data) || !in_array($data->status, array('ready', 'received')))
		{
			return false;
		}

		return true;
	}

	private function getGiftCard($code)
	{
		$userId = $this->params->get('user_id', '');
		$apiKey = $this->params->get('api_key', '');
		$partner_token_prefix = $this->params->get('partner_token_prefix', '');

		if(empty($partner_token_prefix) || strpos($code, $partner_token_prefix) !== 0)
		{
			return null;
		}
		$client = new Giftd_Client($userId, $apiKey);
		$data = $client->checkByToken($code);

		if(is_null($data) || !in_array($data->status, array('ready', 'received')))
		{
			return null;
		}

		return $data;
	}

	private function updateJavaScript()
	{
		try {
			$userId = $this->params->get('user_id', '');
			$apiKey = $this->params->get('api_key', '');
			$client = new Giftd_Client($userId, $apiKey);
			$result = $client->query('partner/getJs');
			$code = isset($result['data']['js']) ? $result['data']['js'] : null;
			if ($code) {
				$file = JPATH_ROOT . '/plugins/system/giftd/assets/js/giftd.js';
				JFile::write($file, $code);
			}
		} catch (Exception $e) {

		}
	}

	private function loadShopData(&$data)
	{
		switch($this->params->get('shop', 'virtuemart')){
			case 'hikashop':
                GiftdHikashopHelper::loadShopData($data);
				break;
			case 'joomshopping':
                GiftdJoomshoppingHelper::loadShopData($data);
				break;
			default:
				GiftdVirtuemartHelper::loadShopData($data);
				break;
		}
	}
}