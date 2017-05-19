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

require_once JPATH_ADMINISTRATOR . '/components/com_virtuemart/helpers/config.php';
require_once JPATH_ADMINISTRATOR . '/components/com_virtuemart/helpers/vmmodel.php';

/**
 * Class plgSystemECom360Virtuemart
 *
 * @since  1.3
 */
class plgSystemECom360Virtuemart extends JPlugin
{
	/**
	 * The shop object
	 *
	 * @var    object
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	private $shop;

	/**
	 * Chimp API
	 *
	 * @var    CmcHelperChimp
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	private $chimp;

	/**
	 * plgSystemECom360Virtuemart constructor.
	 *
	 * @param   object  $subject  Subject
	 * @param   array   $config   Config
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	public function __construct($subject, array $config = array())
	{
		parent::__construct($subject, $config);
	}

	/**
	 * Load the shop
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected function loadShop()
	{
		$shopId      = $this->params->get('store_id', 1);
		$this->shop  = CmcHelperShop::getShop($shopId);
		$this->chimp = new CmcHelperChimp;
	}

	/**
	 * Add Order to MailChimp
	 *
	 * @param   object  $cart   The cart object
	 * @param   object  $order  The order
	 *
	 * @return  bool
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function plgVmConfirmedOrder($cart, $order)
	{
		$this->loadShop();

		$session = JFactory::getSession();

		$customerId = $cart['BT']->customer_number;

		if (!empty($order->virtuemart_user_id))
		{
			$customerId = $order->virtuemart_user_id;
		}

		$customer = CmcHelperShop::getCustomerObject(
			$cart->BT['email'],
			$customerId,
			$cart->BT['company'],
			$cart->BT['email'],
			$cart->BT['last_name']
		);

		$lines = array();

		foreach ($order['items'] as $item)
		{
			$line = new CmcMailChimpLine;

			$line->id                    = 'order_vm_line_' . $item->virtuemart_order_item_id;
			$line->title                 = $item->order_item_name;
			$line->product_id            = 'product_vm_' . $item->virtuemart_product_id;
			$line->product_variant_id    = 'product_vm_' . $item->virtuemart_product_id;
			$line->product_variant_title = $item->order_item_name;
			$line->quantity              = (int) $item->product_quantity;
			$line->price                 = (double) $item->product_final_price;

			$lines[] = $line;
		}

		// The order data
		$mOrder           = new CmcMailChimpOrder;
		$mOrder->id       = CmcHelperShop::PREFIX_ORDER . $order["details"]["BT"]->virtuemart_order_id;
		$mOrder->customer = $customer;

		// Currency
		/** @var VirtueMartModelCurrency $curModel */
		$curModel = VmModel::getModel('currency');

		$currency = $curModel->getCurrency($cart->BT['order_currency']);
		$currencyCode = !empty($currency->currency_code_2) ? $currency->currency_code_2 : $currency->currency_code_3;

		$mOrder->currency_code        = $currencyCode;
		$mOrder->payment_tax          = (double) $order["details"]["BT"]->order_tax;
		$mOrder->order_total          = (double) $order["details"]["BT"]->order_total;
		$mOrder->processed_at_foreign = JFactory::getDate($order->order_created)->toSql();

		$mOrder->lines       = $lines;
		$mOrder->campaign_id = $session->get('mc_cid', '');

		return $this->chimp->addOrder($this->shop->shop_id, $mOrder);
	}

	/**
	 * Clone a product
	 *
	 * @param   object  $data  Data for the product
	 *
	 * @return  void
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	public function plgVmCloneProduct($data)
	{
		// TODO
		$this->loadShop();

	}

	/**
	 * Delete a product
	 *
	 * @param   object  $id  Id of the product
	 *
	 * @return  array|false
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	public function plgVmOnDeleteProduct($id, $ok)
	{
		$this->loadShop();

		return $this->chimp->deleteProduct($this->shop->shop_id, CmcHelperShop::PREFIX_PRODUCT . $id);
	}

	/**
	 * Clone a product
	 *
	 * @param   object  $data  Data for the product
	 *
	 * @return  void
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	public function plgVmOnAfterSaveProduct($data)
	{
		$this->loadShop();
	}

	/**
	 * Store user
	 *
	 * @param   object  $user  User
	 *
	 * @return  array|false
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function plgVmOnUserStore($user)
	{
		$this->loadShop();

		$customer = CmcHelperShop::getCustomerObject(
			$user['email'],
			$user['virtuemart_user_id'],
			$user['company'],
			$user['first_name'],
			$user['last_name']
		);

		$result = $this->chimp->addCustomer($this->shop->shop_id, $customer);

		var_dump($result);
		die('wtf');

		return $result;
	}
}
