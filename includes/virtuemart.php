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

class GiftdVirtuemartHelper
{
    static function loadShopData(&$data)
    {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $query->select('*')
        	->from('#__virtuemart_userinfos')
        	->where('address_type = '.$db->quote('BT'));
        $result = $db->setQuery($query,0,1)->loadObject();

        if(!empty($result->phone_1 )){
            $data['phone'] = $result->phone_1;
        }
        else if(!empty($result->phone_2 )){
            $data['phone'] = $result->phone_2;
        }

        if(!empty($result->first_name)){
            $data['name'] = $result->first_name;
            if(!empty($result->last_name)){
                $data['name'] .= ' '.$result->last_name;
            }
        }

        if(!empty($result->company)){
            $data['title'] = $result->company;
        }
    }

    static function getCouponAmount($code)
    {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $query->select('coupon_value')
            ->from('#__virtuemart_coupons')
            ->where('coupon_code = '.$db->quote($code));
        return (float)$db->setQuery($query,0,1)->loadResult();
    }

    static function deleteOrder($orderId)
    {
        JTable::addIncludePath(JPATH_ROOT.'/administrator/components/com_virtuemart/tables');
        $orders = JTable::getInstance('Orders', 'Table');
        $orders->delete($orderId);
    }

    static function checkVMCoupon($coupon_code, $summ)
    {
        if (!class_exists('CouponHelper')) {
            require(VMPATH_SITE . DS . 'helpers' . DS . 'coupon.php');
        }

        $msg = CouponHelper::ValidateCouponCode($coupon_code, $summ);
        return $msg;
    }
}