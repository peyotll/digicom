<?php
/**
 * @package		DigiCom
 * @author 		ThemeXpert http://www.themexpert.com
 * @copyright	Copyright (c) 2010-2015 ThemeXpert. All rights reserved.
 * @license 	GNU General Public License version 3 or later; see LICENSE.txt
 * @since 		1.0.0
 */

defined('_JEXEC') or die;

use Joomla\Registry\Registry;
use Joomla\String\String;

/**
 * Ordernew model.
 *
 * @since  1.0.0
 */
class DigiComModelOrderNew extends JModelAdmin
{
	/**
	 * The type alias for this content type.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public $typeAlias = 'com_digicom.order';

	/**
	 * The prefix to use with controller messages.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	protected $text_prefix = 'COM_DIGICOM_ORDER';

	/**
	 * Method to get a table object, load it if necessary.
	 *
	 * @param   string  $type    The table name. Optional.
	 * @param   string  $prefix  The class prefix. Optional.
	 * @param   array   $config  Configuration array for model. Optional.
	 *
	 * @return  JTable  A JTable object
	 *
	 * @since   1.0.0
	 */
	public function getTable($type = 'Order', $prefix = 'Table', $config = array())
	{
		return JTable::getInstance($type, $prefix, $config);
	}

	/**
	 * Abstract method for getting the form from the model.
	 *
	 * @param   array    $data      Data for the form.
	 * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
	 *
	 * @return  mixed  A JForm object on success, false on failure
	 *
	 * @since   1.0.0
	 */
	public function getForm($data = array(), $loadData = true)
	{
		// Get the form.
		$form = $this->loadForm('com_digicom.order', 'order', array('control' => 'jform', 'load_data' => $loadData));

		if (empty($form))
		{
			return false;
		}

		return $form;
	}

	/**
	 * Method to get the data that should be injected in the form.
	 *
	 * @return  array  The default data is an empty array.
	 *
	 * @since   1.0.0
	 */
	protected function loadFormData()
	{
		// Check the session for previously entered form data.
		$data = JFactory::getApplication()->getUserState('com_digicom.edit.order.data', array());

		if (empty($data))
		{
			$data = $this->getItem();
		}

		$this->preprocessData('com_digicom.order', $data);

		return $data;
	}

	/**
	 * Method to get a single record.
	 *
	 * @param   integer  $pk  The id of the primary key.
	 *
	 * @return  mixed  Object on success, false on failure.
	 *
	 * @since   1.0.0
	 */
	public function getItem($pk = null)
	{
		$item = parent::getItem($pk);
		$item->products = array();
		return $item;
	}

	/**
	 * Method to validate the form data.
	 *
	 * @param   JForm   $form   The form to validate against.
	 * @param   array   $data   The data to validate.
	 * @param   string  $group  The name of the field group to validate.
	 *
	 * @return  mixed  Array of filtered data if valid, false otherwise.
	 *
	 * @see     JFormRule
	 * @see     JFilterInput
	 * @since   12.2
	 */
	public function validate($form, $data, $group = null)
	{

		// Filter and validate the form data.
		$data = $form->filter($data);
		$return = $form->validate($data, $group);

		// Check for an error.
		if ($return instanceof Exception)
		{
			$this->setError($return->getMessage());

			return false;
		}

		// Check the validation results.
		if ($return === false)
		{
			// Get the validation messages from the form.
			foreach ($form->getErrors() as $message)
			{
				$this->setError($message);
			}

			return false;
		}

		$config = JFactory::getConfig();
		$tzoffset = $config->get('offset');
		if(isset($data['order_date'])&& $data['order_date']){
			$date = JFactory::getDate($data['order_date']);
			$purchase_date = $date->toSql();
			$order_date = $date->toUNIX();
		} else{
			$purchase_date = date('Y-m-d H:i:s', time() + $tzoffset);
			$date = JFactory::getDate();
			$order_date = $date->toUNIX();
		}

		$data['order_date'] = $order_date;
		$data['promocodeid'] = $this->getPromocodeByCode( $data['discount'] );
		$data['number_of_products'] = count( $data['product_id'] );
		$data['published'] = '1';

		return $data;
	}

	/**
	 * Method to save the form data.
	 *
	 * @param   array  $data  The form data.
	 *
	 * @return  boolean  True on success.
	 *
	 * @since	3.1
	 */
	public function save($data)
	{
		$userid = $data['userid'];
		$table = $this->getTable('Customer');
		$table->loadCustommer($userid);

		if(empty($table->id) or $table->id < 0){
			$user = JFactory::getUser($userid);
			$name = explode(' ',$user->name);

			$cust = new stdClass();
			$cust->id = $user->id;
			$cust->firstname = $name[0];
			$cust->lastname =  (!empty($name[1]) ? $name[1] : '');
			$table->bind($cust);
			$table->store();
		}
		// prepare the data
		$status = $data['status'];
		if($status == 'Paid'){
			$data['amount_paid'] = $data['amount'];
			$data['status'] = 'Active';
		}
		$data['price'] = $data['amount'];
		$data['amount'] = $data['amount'] - $data['discount'];
		$data['promocodeid'] = $this->getPromocodeByCode($data['promocode']);

		//DigiComSiteHelperLicense::addLicenceSubscription($data['product_id'], $data['userid'], 1, $data['status']);

		if(parent::save($data)){

			//hook the files here
			$recordId = $this->getState('ordernew.id');
			//we have to add orderdetails now;
			$this->addOrderDetails($data['product_id'], $recordId, $data['userid'], $data['status']);

			$info = array(
				'orderid' => $recordId,
				'status' => $status,
				'now_paid' => $data['amount_paid'],
				'customer' => $cust->firstname ,
				'username' => JFactory::getUser()->username
			);

			DigiComSiteHelperLog::setLog('purchase', 'admin ordernew save', 'Admin created order#'.$recordId.', status: '.$status.', paid: '.$data['amount_paid'], json_encode($info),$status);

			// $orders = $this->getInstance( "Orders", "DigiComModel" );
			// $orders->updateLicensesStatus($data['id'], $type);
			if($data['status'] == 'Active'){
				$type = 'complete_order';
			}else{
				$type = $data['status'];
			}
			DigiComSiteHelperLicense::addLicenceSubscription($data['product_id'], $data['userid'], $recordId, $type);

			return true;

		}

		return false;

	}
	/*
	* add order details
	*/

	function addOrderDetails($items, $orderid, $customer, $status = "Active")
	{

		if($status != "Pending")
			$published = 1;
		else
			$published = 0;

		$database = JFactory::getDBO();
		$jconfig = JFactory::getConfig();

		$user_id = $customer;

		if($user_id == 0){
			return false;
		}

		$product = $this->getTable('Product');
		// start foreach
		foreach($items as $key=>$item)
		{
			if($key >= 0)
			{
				$product->load($item);
				$price = $product->price;
				$date = JFactory::getDate();
				$purchase_date = $date->toSql();
				$expire_string = "0000-00-00 00:00:00";
				$package_type = (!empty($product->bundle_source) ? $product->bundle_source : 'reguler');
				$sql = "insert into #__digicom_orders_details(userid, productid,quantity,price, orderid, amount_paid, published, package_type, purchase_date, expires) "
						. "values ('{$user_id}', '{$item}', '1','{$price}','".$orderid."', '0', ".$published.", '".$package_type."', '".$purchase_date."', '".$expire_string."')";
				//print_r($sql);die;
				$database->setQuery($sql);
				$database->query();
				//
				// $site_config = JFactory::getConfig();
				// $tzoffset = $site_config->get('offset');
				// $buy_date = date('Y-m-d H:i:s', time() + $tzoffset);
				// $sql = "insert into #__digicom_logs (`userid`, `productid`, `buy_date`, `buy_type`)
				// 		values (".$user_id.", ". $item .", '".$buy_date."', 'new')";
				// $database->setQuery($sql);
				// $database->query();


				$sql = "update #__digicom_products set used=used+1 where id = '" . $item . "'";
				$database->setQuery( $sql );
				$database->query();

			}
		}
		// end foreach

		return true;
	}

	/*
		method to get discount code
	*/
	function getPromocodeByCode($code){
		$sql = "SELECT id FROM #__digicom_promocodes WHERE code = '" . $code . "'";
		$this->_db->setQuery( $sql );
		$promocode_id = $this->_db->loadResult();

		if ( $promocode_id ) {
			return $promocode_id;
		} else {
			return "0";
		}

	}

	function getConfigs() {
		$comInfo = JComponentHelper::getComponent('com_digicom');
		return $comInfo->params;
	}
}
