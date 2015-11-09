<?php
/**
 * Giftd for Joomla
 *
 * @version 	1.0
 * @author		Arkadiy Sedelnikov, Joomline
 * @copyright	© 2015. All rights reserved.
 * @license 	GNU/GPL v.2 or later.
 */
defined('_JEXEC') or die('Direct Access not allowed.');

require_once JPATH_ROOT . '/administrator/components/com_hikashop/helpers/helper.php';

class GiftdHikashopHelper
{
    static function loadShopData(&$data)
    {

    }

    static function getCoupon($code){
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $query->select('discount_code')
            ->from('#__hikashop_discount')
            ->where('discount_code = '.$db->quote($code));
        $result = $db->setQuery($query)->loadResult();
        return $result;
    }
    static function getCouponData($code){
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $query->select('*')
            ->from('#__hikashop_discount')
            ->where('discount_code = '.$db->quote($code));
        $result = $db->setQuery($query,0,1)->loadObject();
        return $result;
    }

    static function createCoupon($code, $card, $discountCurrencyId)
    {
        if(empty($code))
        {
            return false;
        }

        $created = new JDate($card->created);
        $created = $created->toUnix();
        $expires = new JDate($card->expires);
        $expires = $expires->toUnix();

        $class = hikashop_get('class.discount');

        $discount = new stdClass();
        $discount->discount_id = hikashop_getCID('discount_id');

        $discount->discount_start = $created;
        $discount->discount_end = $expires;
        $discount->discount_code = $code;
        $discount->discount_type = 'coupon';
        $discount->discount_flat_amount = $card->amount_available;
        $discount->discount_minimum_order = $card->min_amount_total;
        $discount->discount_quota = 0;
        $discount->discount_used_times = 0;
        $discount->discount_published = 1;
        $discount->discount_currency_id = $discountCurrencyId;
        $discount->discount_category_childs = 0;
        $discount->discount_auto_load  = 0;
        $discount->discount_access = 'all';
        $discount->discount_tax_id = 0;
        $discount->discount_minimum_products = 0;
        $discount->discount_quota_per_user = 0;
        $discount->discount_affiliate = 0;

        $status = $class->save($discount);
        if($status){
            return self::getCouponData($code);
        }
        return false;
    }

    static function getCartSumm(){
        $config =& hikashop_config();
        $main_currency = (int)$config->get('main_currency',1);
        $zone_id = hikashop_getZone('shipping');
        if($config->get('tax_zone_type','shipping')=='billing'){
            $tax_zone_id=hikashop_getZone('billing');
        }else{
            $tax_zone_id=$zone_id;
        }
        $discount_before_tax = (int)$config->get('discount_before_tax',0);

        $cart = hikashop_get('class.cart');
        $cartInfo = $cart->loadCart();
        $cart_id = $cartInfo->cart_id;
        if($cart_id == 0){
            return 0;
        }

        $products = $cart->get($cart_id);
        $total = null;
        $currencyClass = hikashop_get('class.currency');
        $currency_id = hikashop_getCurrency();

        $ids = array();
        $mainIds = array();
        foreach($products as $product){
            $ids[]=$product->product_id;
        }

        $currencyClass->getPrices($products,$ids,$currency_id,$main_currency,$tax_zone_id,$discount_before_tax);

        foreach($products as $k => $row){
            unset($products[$k]->cart_modified);
            unset($products[$k]->cart_coupon);

            $currencyClass->calculateProductPriceForQuantity($products[$k]);
        }

        $currencyClass->calculateTotal($products, $total, $currency_id);

        if(isset($total->prices) && isset($total->prices[0]) && isset($total->prices[0]->price_value) && !empty($total->prices[0]->price_value)){
            $sum = $total->prices[0]->price_value;
        }
        else
        {
            $sum = 0;
        }

        return $sum;
    }


    static function deleteOrder($orderId)
    {
        $orderId = (int)$orderId;
        if($orderId == 0){
            return false;
        }
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);

        $query->clear()->delete('#__hikashop_order')
            ->where('order_id = '.$orderId);
        $db->setQuery($query)->execute();

        $query->clear()->delete('#__hikashop_history')
        	->where('history_order_id = '.$orderId);
        $db->setQuery($query)->execute();

        $query->clear()->delete('#__hikashop_order_product')
        	->where('order_id = '.$orderId);
        $db->setQuery($query)->execute();
        return true;
    }
    static function deleteCoupon($couponId)
    {
        if(empty($couponId)){
            return false;
        }

        $db = JFactory::getDbo();
        $query = $db->getQuery(true);

        $query->delete('#__hikashop_discount')
            ->where('discount_code = '.$db->quote($couponId));
        $db->setQuery($query)->execute();

        return true;
    }
}