<?php
/**
 * Created by PhpStorm.
 * User: asede
 * Date: 22.10.2015
 * Time: 14:18
 */
defined('_JEXEC') or die('Direct Access not allowed.');

require_once JPATH_ROOT . '/components/com_jshopping/lib/factory.php';

class GiftdJoomshoppingHelper
{
    static function loadShopData(&$data)
    {
        $main_vendor = JSFactory::getTable('vendor', 'jshop');
        $main_vendor->loadMain();

        if(!empty($main_vendor->phone )){
            $data['phone'] = $main_vendor->phone;
        }
        else if(!empty($main_vendor->fax )){
            $data['phone'] = $main_vendor->fax;
        }

        if(!empty($main_vendor->f_name)){
            $data['name'] = $main_vendor->f_name;
            if(!empty($main_vendor->l_name)){
                $data['name'] .= ' '.$main_vendor->l_name;
            }
        }

        if(!empty($main_vendor->company)){
            $data['title'] = $main_vendor->company;
        }

        if(!empty($main_vendor->email)){
            $data['email'] = $main_vendor->email;
        }
    }

    static function deleteOrder($orderId)
    {
        JTable::addIncludePath(JPATH_ROOT.'/components/com_jshopping/tables');
        $orders = JTable::getInstance('Order', 'jshop');
        $orders->delete($orderId);
    }

    static function getCouponCodeById($id)
    {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $query->select('coupon_code')
        	->from('#__jshopping_coupons')
        	->where('coupon_id = '.(int)$id);
        return $db->setQuery($query,0,1)->loadResult();
    }

    static function deleteCoupon($id)
    {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $query
        	->delete('#__jshopping_coupons')
        	->where('coupon_id = '.(int)$id);
        return $db->setQuery($query)->execute();
    }
}