<?php
/**
 * @package    CMC
 * @author     Compojoom <contact-us@compojoom.com>
 * @date       2016-04-15
 *
 * @copyright  Copyright (C) 2008 - 2016 compojoom.com - Daniel Dimitrov, Yves Hoppe. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die('Restricted access');

// TODO move to autoloader
require_once  JPATH_ADMINISTRATOR . "/components/com_cmc/libraries/drewm/mailchimp-api/MailChimp.php";
require_once  JPATH_ADMINISTRATOR . "/components/com_cmc/libraries/drewm/mailchimp-api/Batch.php";

/**
 * Class cmcHelperChimp
 *
 * This class will work as a small abstraction over the MCAPI class.
 * I got too tired of typing the $key all the time :)
 *
 * @since  1.0
 */
class CmcHelperChimp extends \DrewM\MailChimp\MailChimp
{
	/**
	 * The MailChimp API Key
	 *
	 * @var    null|string
	 *
	 * @since  1.0.0
	 */
	public $api_key = null;

	/**
	 * Verify SSL Certificate of MailChimp
	 *
	 * @var    bool
	 *
	 * @since  1.0.0
	 */
	public $verify_ssl = false;

	/**
	 * The constructor
	 *
	 * @param   string  $key     - the mailchimp api key
	 */
	public function __construct($key = '')
	{
		if (!$key)
		{
			$key = JComponentHelper::getParams('com_cmc')->get('api_key', '');
		}

		$this->api_key = $key;

		// Add logging
		JLog::addLogger(
			array(
				'text_file' => 'com_cmc.errors.php'
			),
			JLog::ERROR,
			array('com_cmc')
		);

		parent::__construct($key);
	}

	/**
	 * Get the account details (/) of the mailchimp account
	 *
	 * @return  array|false
	 */
	public function getAccountDetails()
	{
		return $this->get("/");
	}

	/**
	 * Get the lists information
	 *
	 * @param   string|null  $ids  The listid or null for all
	 *
	 * @return  array|false  The list information
	 */
	public function lists($ids = null)
	{
		if (!$ids)
		{
			$lists = $this->get('/lists', array('count' => 1000));
		}
		else
		{
			$lists = array();

			if (is_array($ids))
			{
				foreach ($ids as $id)
				{
					$lists[] = $this->get('/lists/' . $id, array('count' => 1000));
				}
			}
			else
			{
				$lists[] = $this->get('/lists/' . $ids, array('count' => 1000));
			}
		}

		return $lists;
	}

	/**
	 * Get member details
	 *
	 * @param   string  $listid  The list  id
	 * @param   string  $status  The subscription status
	 * @param   integer     $offset  The offset where to begin with
	 * @param   integer     $limit   The limit
	 *
	 * @return  array|false  The member details
	 */
	public function listMembers($listid, $status, $offset = 0, $limit = 50)
	{
		$args = array();

		if ($status)
		{
			$args["status"] = $status;
		}

		$args["offset"] = $offset;
		$args["count"] = $limit;

		$members = $this->get('/lists/' . $listid . '/members', $args);
		$members = $members['members'];

		// Add email address to merge vars
		for ($i = 0; $i < count($members); $i++)
		{
			$members[$i]['merge_fields'] = array_merge(array("EMAIL" => $members[$i]['email_address']), $members[$i]['merge_fields']);

			// Always get interests
			if (!isset($members[$i]['interests']))
			{
				$members[$i]['interests'] = array();
			}
		}

		return $members;
	}

	/**
	 * Get the member info for the given email address and list
	 *
	 * @param   string  $listid  The list id
	 * @param   string  $email   The email
	 *
	 * @return  array|false
	 */
	public function listMemberInfo($listid, $email)
	{
		$subscriber_hash = $this->subscriberHash($email);

		return $this->get('/lists/' . $listid . '/members/' . $subscriber_hash);
	}

	/**
	 * Get the merge vars for the listid
	 *
	 * @param   string  $listid  The list id
	 *
	 * @return  mixed
	 */
	public function listMergeVars($listid)
	{
		// Normally this should be enough..
		$params = array('count' => 1000);

		// We need to merge the email field here
		$email = array(
			'merge_id' => 1,
			'tag' => 'EMAIL',
			'name' => JText::_('COM_CMC_EMAIL'),
			'type' => 'text',
			'required' => true,
			'public' => true,
			'display_order' => 1,
			'list_id' => $listid
		);

		$fields = $this->get('/lists/' . $listid . '/merge-fields', $params);

		$fields = array_merge(array($email), $fields['merge_fields']);

		return $fields;
	}

	/**
	 * Get the interest groups
	 *
	 * @param   string  $listid  The list id
	 *
	 * @return  array|false
	 */
	public function listInterestGroupings($listid)
	{
		$params = array('count' => 1000);

		$interests = $this->get('/lists/' . $listid . '/interest-categories', $params);

		return isset($interests['categories']) ? $interests['categories'] : null;
	}

	/**
	 * Get field details (options etc.)
	 *
	 * @param   string  $listId   The list id
	 * @param   string  $fieldId  The field id
	 *
	 * @return  array|null
	 */
	public function listIntegerestGroupingsField($listId, $fieldId)
	{
		$params = array('count' => 1000);

		$fields = $this->get('/lists/' . $listId . '/interest-categories/' . $fieldId . "/interests", $params);

		return isset($fields['interests']) ? $fields['interests'] : null;
	}

	/**
	 * Unsubscribe from list
	 *
	 * @param   string  $listid  The list id
	 * @param   string  $email   The email
	 *
	 * @return  array|false
	 */
	public function listUnsubscribe($listid, $email)
	{
		$subscriber_hash = $this->subscriberHash($email);

		return $this->delete('/lists/' . $listid . '/members/' . $subscriber_hash);
	}

	/**
	 * Add ecommerce order
	 *
	 * @param  object  $order  The order details
	 *
	 * @throws  Exception
	 * 
	 * @return  array
	 */
	public function ecommOrderAdd($order)
	{
		return $this->post('/ecommerce/stores/' . $order['store_id'] . '/orders', $order);
	}

	/**
	 * Unimplemented in v3 API!
	 *
	 * @param   string  $email   The email
	 *
	 * @deprecated  Not implemented in v3
	 *
	 * @throws Exception
	 */
	public function listsForEmail($email)
	{
		throw new Exception("Unimplemented not available in v3", 500);
	}

	/**
	 * Is the user subscribed
	 *
	 * @param   string  $listId  The list id
	 * @param   string  $email   The mail address
	 *
	 * @return  bool true if yes
	 *
	 * @since   3.0.0
	 */
	public function isSubscribed($listId, $email)
	{
		$subscriber_hash = $this->subscriberHash($email);

		$result = $this->get('/lists/' . $listId . "/members/" . $subscriber_hash);

		// Not existing member
		if ($result['status'] == 404)
		{
			return false;
		}

		return in_array($result['status'], array('subscribed', 'pending', 'unsubscribed', 'transactional')) ? true : false;
	}

	/**
	 * Subscribe / Update some one to the list
	 *
	 * @param   string  $listId          The list id
	 * @param   string  $email_address   The email
	 * @param   array   $merge_vars      Merge vars
	 * @param   array   $interests       Merge vars
	 *
	 * @return array|false
	 */
	public function listSubscribe($listId,
	                              $email_address,
	                              $merge_vars = null,
	                              $interests = null,
	                              $email_type = 'html',
	                              $double_optin = true,
	                              $update_existing = false,
	                              $replace_interests = true,
	                              $send_welcome = false)
	{
		$status = 'subscribed';

		if ($double_optin)
		{
			$status = 'pending';
		}

		// We need to unset that
		if (isset($merge_vars['GROUPINGS']))
		{
			unset($merge_vars['GROUPINGS']);
		}

		$args = array(
			'email_address' => $email_address,
			'status'        => $status,
			'merge_fields'  => $merge_vars,
			'interests'     => count($interests) ? $interests : new stdClass,
			'email_type'    => $email_type
		);

		if ($update_existing && $this->isSubscribed($listId, $email_address))
		{
			// Update user ..
			return $this->listUpdateSubscribe(
				$listId,
				$email_address,
				$merge_vars,
				$interests,
				$email_type
			);
		}

		$result = $this->post("/lists/" . $listId . "/members", $args);

		return $result;
	}

	/**
	 * Update to subscribtion
	 *
	 * @param   string  $listId        The list id
	 * @param   string  $email_address The email
	 * @param   array   $merge_vars    Merge vars
	 * @param   array   $interests     Merge vars
	 * @param   string  $email_type    HTML or Text?
	 * @param   string  $status        subscribed or pending??
	 *
	 * @return array|false
	 */
	public function listUpdateSubscribe($listId,
										$email_address,
										$merge_vars = null,
										$interests = null,
										$email_type = 'html',
										$status = 'subscribed'
										)
	{
		$subscriber_hash = $this->subscriberHash($email_address);

		$args = array(
			'email_address' => $email_address,
			'status' => $status,
			'merge_fields'  => $merge_vars,
			'interests'     => $interests,
			'email_type'    => $email_type
		);

		// The updated object
		$result = $this->put('/lists/' . $listId . "/members/" . $subscriber_hash, $args);

		return $result;
	}

	/**
	 * Send Ecommerce request
	 *
	 * @param       $mcId
	 * @param object $store
	 * @param       $order
	 * @param array $lines
	 * @param array $customer
	 *
	 * @return array|false
	 *
	 * @since version
	 */
	public function addEcomOrder($mcId, $store, $order, $lines = array(), $customer)
	{
		$order->id = (string) $order->id;
		$order->order_total = (double) $order->order_total;
		$order->payment_tax = (double) $order->payment_tax;

		if(!$this->storeExists($store->id))
		{
			if(!$this->storeCreate($store))
			{
				JLog::add('Couldn\'t create store with ID ' . $store->id, Jlog::ERROR, 'com_cmc');

				return false;
			};
		}

		foreach($lines as $key => $product)
		{
			if(!$this->productExists($store->id, $product['product_id']))
			{
				// Try to create the product
				if(!$this->createProduct($store->id, $product))
				{
					unset($lines[$key]);
				}
			}

			if(count($lines) == 0)
			{
				// we couldn't create the products at this stage
				return false;
			}
		}

		$args = (array) $order;
		$args['lines'] = $lines;
		$args['campaign_id'] = $mcId;
		$args['customer'] = $customer;

		$result = $this->post('/ecommerce/stores/' . $store->id . "/orders", $args);

		return $result;
	}

	/**
	 * Create a new shop
	 *
	 * @param   object  $shop  Shop
	 *
	 * @return  bool|string
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function createShop($shop)
	{
		if ($this->storeExists($shop->id))
		{
			return true;
		}

		return $this->storeCreate($shop);
	}

	/**
	 * Check if the store exists
	 *
	 * @param  string  $storeId  - the store id
	 *
	 * @return bool
	 */
	public function storeExists($storeId)
	{
		$this->get('/ecommerce/stores/' . $storeId);

		$lastResponse = $this->getLastResponse();

		if ($lastResponse['headers']['http_code'] == 404)
		{
			return false;
		}

		return true;
	}

	/**
	 * Create a store if doesn't exist
	 *
	 * @param   object  $store  - the store object as described here http://developer.mailchimp.com/documentation/mailchimp/reference/ecommerce/stores/
	 *
	 * @return array|false
	 */
	public function storeCreate($store)
	{
		$result = $this->post('/ecommerce/stores', $store);

		return $result;
	}

	/**
	 * Update a shop
	 *
	 * @param   object  $shop  - the store object as described here http://developer.mailchimp.com/documentation/mailchimp/reference/ecommerce/stores/
	 *
	 * @return  array|false
	 *
 	 * @since   __DEPLOY_VERSION__
	 */
	public function updateShop($shop)
	{
		return $this->patch('/ecommerce/stores/' . $shop->id, $shop);
	}

	/**
	 * DELETE /ecommerce/stores/{store_id}
	 *
	 * @param   string  $shopId  Store id (e.g. 1)
	 *
	 * @return  array|false
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function deleteShop($shopId)
	{
		return $this->delete('/ecommerce/stores/' . $shopId);
	}

	/**
	 * Create a product
	 *
	 * @param   string  $storeId  - the store id
	 * @param   object  $product  - the product object as described here http://developer.mailchimp.com/documentation/mailchimp/reference/ecommerce/stores/products/
	 *
	 * @return array|false
	 */
	public function createProduct($storeId, $product)
	{
		$variants = array (
			'id' => $product['product_id'],
			'title' => $product['title']
		);

		$product['id'] = $product['product_id'];
		$product['variants'][] = $variants;

		$result = $this->post('/ecommerce/stores/' . $storeId . '/products', $product);

		return $result;
	}

	/**
	 * Batch subscribe users to mailchimp
	 *
	 * @param   string  $list   - the list id
	 * @param   array   $users  - the users to subscribe to the list (the merges)
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function listBatchSubscribe($list, $users)
	{
		$config = JComponentHelper::getParams('com_cmc');

		$batch = new \DrewM\MailChimp\Batch($this);
		$status = 'subscribed';

		// If double opt-in is on, then add the user as pending and let mailchimp send him a confirmation mail
		if($config->get('opt_in', true))
		{
			$status = 'pending';
		}

		foreach($users as $user)
		{
			$args = array(
				'email_address' => $user['EMAIL'],
				"status_if_new" => $status,
				'merge_fields'  => $user,
				'interests'     => new stdClass,
				'email_type'    => 'html'
			);

			$batch->put('my-id',  'lists/' . $list . '/members/' . md5($user['EMAIL']), $args);
		}

		// Send the request
		$batch->execute();
	}

	/**
	 * Add a new product to the shop
	 *
	 * @param   string               $shopId   Store id (e.g. 1)
	 * @param   CmcMailChimpProduct  $product  Product
	 *
	 * @return  array|false
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function addProduct($shopId, CmcMailChimpProduct $product)
	{
		$exists = $this->productExists($shopId, $product);

		if ($exists)
		{
			return $this->updateProduct($shopId, $product);
		}

		return $this->post('/ecommerce/stores/' . $shopId . '/products', $product);
	}

	/**
	 * Checks if a product (with id) is existing
	 *
	 * @param   string               $shopId    StoreId
	 * @param   CmcMailChimpProduct  $product   Product
	 *
	 * @return  bool
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function productExists($shopId, CmcMailChimpProduct $product)
	{
		$result = $this->get('/ecommerce/stores/' . $shopId . '/products/' . $product->id);

		$lastResponse = $this->getLastResponse();

		if ($lastResponse['headers']['http_code'] == 404)
		{
			return false;
		}

		return true;
	}

	/**
	 * DELETE /ecommerce/stores/{store_id}/products/{product_id}
	 *
	 * @param   string  $shopId     Store id (e.g. 1)
	 * @param   string  $productId  Product id (e.g. vm_product_)
	 *
	 * @return  array|false
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function deleteProduct($shopId, $productId)
	{
		$result = $this->delete('/ecommerce/stores/' . $shopId . '/products/' . $productId);

		return $result;
	}

	/**
	 * DELETE /ecommerce/stores/{store_id}/products/{product_id}
	 *
	 * @param   string               $shopId     Store id (e.g. 1)
	 * @param   CmcMailChimpProduct  $product    Product
	 *
	 * @return  array|false
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function updateProduct($shopId, CmcMailChimpProduct $product)
	{
		$result = $this->patch('/ecommerce/stores/' . $shopId . '/products/' . $product->id, $product);

		return $result;
	}

	/**
	 * Add a new customer to the shop
	 *
	 * @param   string                $shopId    Store id (e.g. 1)
	 * @param   CmcMailChimpCustomer  $customer  CustomerObject
	 *
	 * @return  array|false
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function addCustomer($shopId, CmcMailChimpCustomer $customer)
	{
		$exists = $this->customerExists($shopId, $customer);

		if ($exists)
		{
			return $this->updateCustomer($shopId, $customer);
		}

		return $this->post('/ecommerce/stores/' . $shopId . '/customers', $customer);
	}

	/**
	 * Update a customer
	 *
	 * @param   string                $shopId    Store id (e.g. 1)
	 * @param   CmcMailChimpCustomer  $customer  CustomerObject
	 *
	 * @return  array|false
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function updateCustomer($shopId, CmcMailChimpCustomer $customer)
	{
		return $this->patch('/ecommerce/stores/' . $shopId . '/customers/' . $customer->id, $customer);
	}

	/**
	 * Checks if a customer (with id) is existing
	 *
	 * @param   string                $shopId    StoreId
	 * @param   CmcMailChimpCustomer  $customer  Customer
	 *
	 * @return  bool
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function customerExists($shopId, CmcMailChimpCustomer $customer)
	{
		$result = $this->get('/ecommerce/stores/' . $shopId . '/customers/' . $customer->id);

		$lastResponse = $this->getLastResponse();

		if ($lastResponse['headers']['http_code'] == 404)
		{
			return false;
		}

		return true;
	}

	/**
	 * DELETE /ecommerce/stores/{store_id}/customers/{product_id}
	 *
	 * @param   string  $shopId     Store id (e.g. 1)
	 * @param   string  $productId  Product id (e.g. vm_customer_42)
	 *
	 * @return  array|false
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function deleteCustomer($shopId, $id)
	{
		return $this->delete('/ecommerce/stores/' . $shopId . '/customers/' . $id);
	}

	/**
	 * Add a new order to the shop
	 *
	 * @param   string             $shopId   Store id (e.g. vm_1)
	 * @param   CmcMailChimpOrder  $order    Order
	 *
	 * @return  array|false
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function addOrder($shopId, CmcMailChimpOrder $order)
	{
		$exists = $this->orderExists($shopId, $order);

		if ($exists)
		{
			return $this->updateOrder($shopId, $order);
		}

		return $this->post('/ecommerce/stores/' . $shopId . '/orders', $order);
	}

	/**
	 * Update an order
	 *
	 * @param   string             $shopId  Store id (e.g. vm_1)
	 * @param   CmcMailChimpOrder  $order   Order
	 *
	 * @return  array|false
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function updateOrder($shopId, CmcMailChimpOrder $order)
	{
		return $this->patch('/ecommerce/stores/' . $shopId . '/orders/' . $order->id, $order);
	}

	/**
	 * Checks if a order (with id) is existing
	 *
	 * @param   string             $shopId  StoreId
	 * @param   CmcMailChimpOrder  $order   Order
	 *
	 * @return  bool
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function orderExists($shopId, CmcMailChimpOrder $order)
	{
		$result = $this->get('/ecommerce/stores/' . $shopId . '/orders/' . $order->id);

		$lastResponse = $this->getLastResponse();

		if ($lastResponse['headers']['http_code'] == 404)
		{
			return false;
		}

		return true;
	}

	/**
	 * DELETE /ecommerce/stores/{store_id}/orders/{product_id}
	 *
	 * @param   string  $shopId  Store id (e.g. 1)
	 * @param   string  $id      Order id (e.g. vm_order_42)
	 *
	 * @return  array|false
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function deleteOrder($shopId, $id)
	{
		return $this->delete('/ecommerce/stores/' . $shopId . '/orders/' . $id);
	}

	/**
	 * Add a new cart to the shop
	 *
	 * @param   string            $shopId   Store id (e.g. vm_1)
	 * @param   CmcMailChimpCart  $cart     Cart item
	 *
	 * @return  array|false
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function addCart($shopId, CmcMailChimpCart $cart)
	{
		$exists = $this->cartExists($shopId, $cart);

		if ($exists)
		{
			return $this->updateCart($shopId, $cart);
		}

		return $this->post('/ecommerce/stores/' . $shopId . '/carts', $cart);
	}

	/**
	 * Update an cart
	 *
	 * @param   string            $shopId  Store id (e.g. vm_1)
	 * @param   CmcMailChimpCart  $cart    Cart
	 *
	 * @return  array|false
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function updateCart($shopId, CmcMailChimpCart $cart)
	{
		return $this->patch('/ecommerce/stores/' . $shopId . '/carts/' . $cart->id, $cart);
	}

	/**
	 * Checks if a cart (with id) is existing
	 *
	 * @param   string            $shopId  Store id (e.g. vm_1)
	 * @param   CmcMailChimpCart  $cart    Cart
	 *
	 * @return  bool
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function cartExists($shopId, CmcMailChimpCart $cart)
	{
		$result = $this->get('/ecommerce/stores/' . $shopId . '/carts/' . $cart->id);

		$lastResponse = $this->getLastResponse();

		if ($lastResponse['headers']['http_code'] == 404)
		{
			return false;
		}

		return true;
	}

	/**
	 * DELETE /ecommerce/stores/{store_id}/carts/{product_id}
	 *
	 * @param   string  $shopId  Store id (e.g. 1)
	 * @param   string  $id      Cart id (e.g. vm_cart_42)
	 *
	 * @return  array|false
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function deleteCart($shopId, $id)
	{
		return $this->delete('/ecommerce/stores/' . $shopId . '/carts/' . $id);
	}


	/**
	 * CREATE list POST /lists
	 *
	 * @param   array  $list  The list
	 *
	 * @return  array|false
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function createList($list)
	{
		return $this->post('/lists', $list);
	}
}
