<?php
/**
 * @package    CMC
 * @author     Compojoom <contact-us@compojoom.com>
 * @date       2016-04-15
 *
 * @copyright  Copyright (C) 2008 - 2016 compojoom.com - Daniel Dimitrov, Yves Hoppe. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

// No direct access
defined('_JEXEC') or die('Restricted access');

JLoader::discover('CmcHelper', JPATH_ADMINISTRATOR . '/components/com_cmc/helpers/');

/**
 * Class plgSystemECom360Virtuemart
 *
 * @since  1.3
 */
class plgSystemECom360Virtuemart extends JPlugin
{
	/**
	 * @param $cart
	 * @param $order
	 *
	 * @return bool
	 */
	public function plgVmConfirmedOrder($cart, $order)
	{
		$app = JFactory::getApplication();

		// This plugin is only intended for the frontend
		if ($app->isAdmin())
		{
			return true;
		}

		$session = JFactory::getSession();

		// Trigger plugin only if user comes from Mailchimp
		if (!$session->get('mc', '0'))
		{
			return;
		}

		$shop_id = $this->params->get("store_id", 42);

		$products = array();

		foreach ($order['items'] as $item)
		{
			$products[] = array(
				"product_id" => $item->virtuemart_product_id, "sku" => $item->order_item_sku, "product_name" => $item->order_item_name,
				"category_id" => $item->virtuemart_category_id, "category_name" => "", "qty" => (double) $item->product_quantity,
				"cost" => $item->product_final_price
			);
		}

		$chimp = new CmcHelperChimp;

		return $chimp->addEcomOrder(
			$session->get('mc_cid', '0'),
			$shop_id,
			$order["details"]["BT"]->virtuemart_order_id,
			$order["details"]["BT"]->currency_code,
			$order["details"]["BT"]->order_total,
			$order["details"]["BT"]->order_tax,
			$products
		);
	}
}
