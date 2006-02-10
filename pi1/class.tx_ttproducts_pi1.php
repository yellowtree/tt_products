<?php
/***************************************************************
*  Copyright notice
*
*  (c) 1999-2006 Kasper Skaarhoj (kasperYYYY@typo3.com)
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*  A copy is found in the textfile GPL.txt and important notices to the license
*  from the author is found in LICENSE.txt distributed with these scripts.
*
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
 * Part of the tt_products (Shopping System) extension.
 *
 * Creates a list of products for the shopping basket in TYPO3.
 * Also controls basket, searching and payment.
 *
 *
 * $Id$
 *
 * @author	Kasper Sk�rh�j <kasperYYYY@typo3.com>
 * @author	Ren� Fritz <r.fritz@colorcube.de>
 * @author	Franz Holzinger <kontakt@fholzinger.com>
 * @author	Klaus Zierer <zierer@pz-systeme.de>
 * @author	Milosz Klosowicz <typo3@miklobit.com>
 * @author	Els Verberne <verberne@bendoo.nl>
 * @package TYPO3
 * @subpackage tt_products
 * @see file tt_products/ext_typoscript_constants.txt
 * @see TSref
 *
 *
 */

//require_once(PATH_tslib.'class.tslib_pibase.php');
require_once(PATH_BE_fh_library.'/sysext/cms/tslib/class.fhlibrary_pibase.php');

require_once(PATH_t3lib.'class.t3lib_parsehtml.php');
require_once(PATH_BE_ttproducts.'pi1/class.tx_ttproducts_htmlmail.php');

require_once(PATH_BE_table.'lib/class.tx_table_db.php');
require_once(PATH_BE_table.'lib/class.tx_table_db_access.php');

require_once (PATH_BE_ttproducts.'lib/class.tx_ttproducts_basket.php');
require_once (PATH_BE_ttproducts.'lib/class.tx_ttproducts_basket_view.php');
require_once (PATH_BE_ttproducts.'lib/class.tx_ttproducts_category.php');
require_once (PATH_BE_ttproducts.'lib/class.tx_ttproducts_content.php');
require_once (PATH_BE_ttproducts.'lib/class.tx_ttproducts_creditpoints_div.php');
require_once (PATH_BE_ttproducts.'lib/class.tx_ttproducts_div.php');
require_once (PATH_BE_ttproducts.'lib/class.tx_ttproducts_email.php');
require_once (PATH_BE_ttproducts.'lib/class.tx_ttproducts_gifts_div.php');
require_once (PATH_BE_ttproducts.'lib/class.tx_ttproducts_marker.php');
require_once (PATH_BE_ttproducts.'lib/class.tx_ttproducts_order.php');
require_once (PATH_BE_ttproducts.'lib/class.tx_ttproducts_page.php');
require_once (PATH_BE_ttproducts.'lib/class.tx_ttproducts_paymentshipping.php');
require_once (PATH_BE_ttproducts.'lib/class.tx_ttproducts_price.php');
require_once (PATH_BE_ttproducts.'lib/class.tx_ttproducts_pricecalc.php');
require_once (PATH_BE_ttproducts.'lib/class.tx_ttproducts_product.php');
require_once (PATH_BE_ttproducts.'lib/class.tx_ttproducts_single_view.php');


class tx_ttproducts_pi1 extends fhlibrary_pibase {
	var $prefixId = 'tx_ttproducts_pi1';	// Same as class name
	var $scriptRelPath = 'pi1/class.tx_ttproducts_pi1.php';	// Path to this script relative to the extension dir.
	var $extKey = TT_PRODUCTS_EXTkey;	// The extension key.

	var $cObj;		// The backReference to the mother cObj object set at call time

		// Internal
	//var $pid_list='';

	var $uid_list='';					// List of existing uid's from the basket, set by initBasket()
	var $orderRecord = array();			// Will hold the order record if fetched.

		// Internal: init():
	var $templateCode='';				// In init(), set to the content of the templateFile. Used by default in basketView->getView()

	var $config=array();				// updated configuration
	var $tt_product_single='';
	var $globalMarkerArray=array();
	var $externalCObject='';
		// mkl - multicurrency support
	var $currency = '';				// currency iso code for selected currency
	var $baseCurrency = '';			// currency iso code for default shop currency
	var $xrate = 1.0;				// currency exchange rate (currency/baseCurrency)

	var $mkl; 					// if compatible to mkl_products
	var $tt_products; 				// object of the type tx_ttproducts_product
	var $tt_products_articles;		// object of the type tx_table_db
	var $tt_products_cat; 					// object of the type tx_ttproducts_category

	var $tt_content; 					// object of the type tx_ttproducts_content
	var $order;	 					// object of the type tx_ttproducts_order
	var $page;	 					// object of the type tx_ttproducts_page
	var $pid_list;					// list of page ids
	var $paymentshipping; 			// object of the type tx_ttproducts_paymentshipping
	var $price;	 					// object for price functions

	var $basket;					// basket object
	var $singleView;				// single view object
	
	var $pi_checkCHash = TRUE;
	var $pid;						// the page to which the script shall go

	/**
	 * Main method. Call this from TypoScript by a USER cObject.
	 */
	function main_products($content,$conf)	{
		global $TSFE;

		$error_code = array();
		
		// page where to go usually
		$this->pid = ($conf['PIDbasket'] && $conf['clickIntoBasket'] ? $conf['PIDbasket'] : (t3lib_div::_GP('backPID') ? t3lib_div::_GP('backPID') : $TSFE->id));
		
		$this->init ($content, $conf, $this->config);

		$codes=t3lib_div::trimExplode(',', $this->config['code'],1);
		if (!count($codes))	 $codes=array('HELP');
		$codes = $this->pi_sortCodes($codes);

		if (t3lib_div::_GP('mode_update'))
			$updateMode = 1;
		else
			$updateMode = 0;

		if (!$this->errorMessage) {
			$bStoreBasket = (count($codes) == 1 && $codes[0]=='OVERVIEW' ? false : true); 
			$this->basket->init($this, $this->conf, $this->config, $TSFE->fe_user->getKey('ses','recs'), $updateMode, 
				$this->pid_list, $this->tt_content, $this->tt_products, $this->tt_products_cat, $this->price, $this->paymentshipping, $bStoreBasket);
		}

		// *************************************
		// *** Listing items:
		// *************************************
		if (!$this->errorMessage) {
			$this->basketView->init ($this->basket, $this->order, $this->templateCode);
			$content .= $this->basketView->printView($codes, $this->errorMessage);
		}
		reset($codes);
		//$TSFE->set_no_cache(); // uncomment this line if you have a problem with the cache
		while(!$this->errorMessage && list(,$theCode)=each($codes))	{
			$theCode = (string) trim($theCode);
			$contentTmp = '';
			switch($theCode)	{
				case 'SEARCH':
					$TSFE->set_no_cache();
					// no break!
				case 'LIST':
				case 'LISTGIFTS':
				case 'LISTHIGHLIGHTS':
				case 'LISTNEWITEMS':
				case 'LISTOFFERS':
					if ($this->tt_product_single || !$this->conf['NoSingleViewOnList']) {
						$TSFE->set_no_cache();
					}
					if (count($this->basket->itemArray) || $this->tt_product_single) {
						$TSFE->set_no_cache();
					}
					$contentTmp=$this->products_display($theCode, $this->errorMessage, $error_code);
				break;
				case 'LISTCAT':
					// TODO: Code for listing of categories
					$TSFE->set_no_cache();
					$contentTmp = '<p>List of the categories</p>';
				break;
				case 'SINGLE':
					if (count($this->basket->itemArray) || !$this->conf['NoSingleViewOnList']) {
						$TSFE->set_no_cache();
					}
					$contentTmp=$this->products_display($theCode, $this->errorMessage, $error_code);				
				break;
				case 'OVERVIEW':
					if (count($this->basket->itemArray)) {
						$TSFE->set_no_cache();
					}
					break;
				case 'BASKET':
				case 'FINALIZE':
				case 'INFO':
				case 'PAYMENT':
					$TSFE->set_no_cache();
						// nothing here any more. This work is done in the call of $this->basketView->printView before
						// This is necessary because some activities might have overriden these CODEs
				break;
				case 'BILL':
				case 'DELIVERY':
					include_once (PATH_BE_ttproducts.'lib/class.tx_ttproducts_billdelivery.php');
					$TSFE->set_no_cache();
					$contentTmp=$this->products_tracking($theCode);
				break;
				case 'TRACKING':
					include_once (PATH_BE_ttproducts.'lib/class.tx_ttproducts_tracking.php');
					$TSFE->set_no_cache();
					$contentTmp=$this->products_tracking($theCode);
				break;
				case 'MEMO':
					include_once (PATH_BE_ttproducts.'lib/class.tx_ttproducts_memo_view.php');
					$TSFE->set_no_cache();
						// memo view
					$memoView = t3lib_div::makeInstance('tx_ttproducts_memo_view');
					$memoView->init($this, $this->conf, $this->config, $this->basket, $this->pid_list, $this->tt_content, $this->tt_products, $this->tt_products_cat, $this->pid);
					$contentTmp=$memoView->printView($this->templateCode, $error_code);
				break;
				case 'CURRENCY':
					include_once (PATH_BE_ttproducts.'lib/class.tx_ttproducts_currency_view.php');
					$TSFE->set_no_cache();
						// currency view
					$currencyView = t3lib_div::makeInstance('tx_ttproducts_currency_view');
					$currencyView->init($this, $this->conf, $this->config, $this->basket);

					$contentTmp=$currencyView->printView();
				break;
				case 'ORDERS':
					include_once (PATH_BE_ttproducts.'lib/class.tx_ttproducts_order_view.php');
					$TSFE->set_no_cache();

						// order view
					$orderView = t3lib_div::makeInstance('tx_ttproducts_order_view');
					$orderView->init($this, $this->conf, $this->config, $this->basket);
					$contentTmp=$orderView->printView();
				break;
				default:	// 'HELP'
					$TSFE->set_no_cache();
					$contentTmp = 'error';
				break;
			}

			if ($error_code[0]) {
				$messageArr = array(); 
				if ($error_code[1]) {
					$i = 0;
					foreach ($error_code as $key => $indice) {
						if ($key == 0) {
							$messageArr =  explode('|', $message = $this->pi_getLL($indice));
							$contentTmp.=$messageArr[0];
						} else {
							$contentTmp.=$indice.$messageArr[$i];
						}
						$i++;
					}
				}
				// $contentTmp.=$messageArr[0].intval($this->uid) .$messageArr[1];
			}
			
			if ($contentTmp == 'error') {
					$content .= $this->pi_displayHelpPage($this->cObj->fileResource('EXT:'.TT_PRODUCTS_EXTkey.'/template/products_help.tmpl'));
					unset($this->errorMessage);
					break; // while
			} else {
				$content.=$contentTmp;
			}
		}

		if ($this->errorMessage) {
			$content = '<p><b>'.$this->errorMessage.'</b></p>';
		}
		return $this->pi_wrapInBaseClass($content);
	}

	/**
	 * does the initialization stuff
	 *
	 * @param		string		  content string
	 * @param		string		  configuration array
	 * @param		string		  modified configuration array
	 * @return	  void
 	 */
	function init (&$content,&$conf, &$config) {
		global $TSFE;

			// getting configuration values:
		$this->conf=$conf;
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL('../locallang_db.xml');

			// get all extending TCAs
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][TT_PRODUCTS_EXTkey]['extendingTCA']))	{
			$this->pi_mergeExtendingTCAs($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][TT_PRODUCTS_EXTkey]['extendingTCA']);
		}

		$this->initTables();

		// $TSFE->set_no_cache();
 
		// *************************************
		// *** getting configuration values:
		// *************************************

		$this->pi_initPIflexForm();
		$config['code'] = 
			$this->pi_getSetupOrFFvalue(
	 			$this->conf['code'], 
	 			$this->conf['code.'], 
				$this->conf['defaultCode'], 
				$this->cObj->data['pi_flexform'],
				'display_mode',
				$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][TT_PRODUCTS_EXTkey]['useFlexforms']
				);

		$config['limit'] = $this->conf['limit'] ? $this->conf['limit'] : 50;
		$config['limitImage'] = t3lib_div::intInRange($this->conf['limitImage'],0,15);
		$config['limitImage'] = $config['limitImage'] ? $config['limitImage'] : 1;
		$config['limitImageSingle'] = t3lib_div::intInRange($this->conf['limitImageSingle'],0,15);
		$config['limitImageSingle'] = $config['limitImageSingle'] ? $config['limitImageSingle'] : 1;

		$config['recursive'] = t3lib_div::intInRange($this->conf['recursive'],0,100);
		$config['storeRootPid'] = $this->conf['PIDstoreRoot'] ? $this->conf['PIDstoreRoot'] : $TSFE->tmpl->rootLine[0]['uid'];
		$config['priceNoReseller'] = $this->conf['priceNoReseller'] ? t3lib_div::intInRange($this->conf['priceNoReseller'],2,2) : NULL;

		$pid_list = $config['pid_list'] = trim($this->cObj->stdWrap($this->conf['pid_list'],$this->conf['pid_list.']));
		$this->pid_list = ($pid_list ? $pid_list : $config['storeRootPid']);
		//$config['pid_list'] = $this->config['pid_list'] ? $config['pid_list'] : $TSFE->id;

			// If the current record should be displayed.
		$config['displayCurrentRecord'] = $this->conf['displayCurrentRecord'];
		if ($config['displayCurrentRecord'])	{
			$config['code']='SINGLE';
			$this->tt_product_single = true;
		} else {
			$this->tt_product_single = t3lib_div::_GP('tt_products');
		}

		if ($this->conf['templateFile']) {
			// template file is fetched. The whole template file from which the various subpart are extracted.
			$this->templateCode = $this->cObj->fileResource($this->conf['templateFile']);
		}

		if (!$this->conf['templateFile'] || empty($this->templateCode)) {
			$this->errorMessage .= $this->pi_getLL('no_template').' tt_products.file.templateFile.';
			$this->errorMessage .= ($this->conf['templateFile'] ? "'".$this->conf['templateFile']."'" : '');
		}

		// mkl - multicurrency support
		if (t3lib_extMgm::isLoaded('mkl_currxrate')) {
			include_once(t3lib_extMgm::extPath('mkl_currxrate').'pi1/class.tx_mklcurrxrate_pi1.php');
			$this->baseCurrency = $TSFE->tmpl->setup['plugin.']['tx_mklcurrxrate_pi1.']['currencyCode'];
			$this->currency = t3lib_div::GPvar('C') ? 	t3lib_div::GPvar('C') : $this->baseCurrency;

			// mkl - Initialise exchange rate library and get

			$this->exchangeRate = t3lib_div::makeInstance('tx_mklcurrxrate_pi1');
			$this->exchangeRate->init();
			$result = $this->exchangeRate->getExchangeRate($this->baseCurrency, $this->currency) ;
			$this->xrate = floatval ( $result['rate'] );
		}

		if (t3lib_extMgm::isLoaded('sr_static_info')) {
			include_once(t3lib_extMgm::extPath('sr_static_info').'pi1/class.tx_srstaticinfo_pi1.php');
			// Initialise static info library
			$this->staticInfo = t3lib_div::makeInstance('tx_srstaticinfo_pi1');
			$this->staticInfo->init();
		}

			// globally substituted markers, fonts and colors.
		$splitMark = md5(microtime());
		$globalMarkerArray=array();
		list($globalMarkerArray['###GW1B###'],$globalMarkerArray['###GW1E###']) = explode($splitMark,$this->cObj->stdWrap($splitMark,$this->conf['wrap1.']));
		list($globalMarkerArray['###GW2B###'],$globalMarkerArray['###GW2E###']) = explode($splitMark,$this->cObj->stdWrap($splitMark,$this->conf['wrap2.']));
		$globalMarkerArray['###GC1###'] = $this->cObj->stdWrap($this->conf['color1'],$this->conf['color1.']);
		$globalMarkerArray['###GC2###'] = $this->cObj->stdWrap($this->conf['color2'],$this->conf['color2.']);
		$globalMarkerArray['###GC3###'] = $this->cObj->stdWrap($this->conf['color3'],$this->conf['color3.']);
		$globalMarkerArray['###DOMAIN###'] = $this->conf['domain'];
		$globalMarkerArray['###PID_BASKET###'] = $this->conf['PIDbasket'];
		$globalMarkerArray['###PID_BILLING###'] = $this->conf['PIDbilling'];
		$globalMarkerArray['###PID_DELIVERY###'] = $this->conf['PIDdelivery'];
		$globalMarkerArray['###PID_TRACKING###'] = $this->conf['PIDtracking'];
		$globalMarkerArray['###SHOPADMIN_EMAIL###'] = $this->conf['orderEmail_from'];
		
		if (is_array($this->conf['marks.']))	{
				// Substitute Marker Array from TypoScript Setup
			foreach ($this->conf['marks.'] as $key => $value)	{
				$globalMarkerArray['###'.$key.'###'] = $value;
			}
		}

			// Substitute Global Marker Array
		$this->templateCode= $this->cObj->substituteMarkerArrayCached($this->templateCode, $globalMarkerArray);
		
			// This cObject may be used to call a function which manipulates the shopping basket based on settings in an external order system. The output is included in the top of the order (HTML) on the basket-page.
		$this->externalCObject = $this->getExternalCObject('externalProcessing');

			// Initializes object
		$this->globalMarkerArray = $globalMarkerArray;
		
			// basket
		$this->basket = t3lib_div::makeInstance('tx_ttproducts_basket');

			// basket view
		$this->basketView = t3lib_div::makeInstance('tx_ttproducts_basket_view');
		
			// price
		$this->price = t3lib_div::makeInstance('tx_ttproducts_price');
		$this->price->init($this, $this->conf, $config);
	
			// paymentshipping
		$this->paymentshipping = t3lib_div::makeInstance('tx_ttproducts_paymentshipping');
		$this->paymentshipping->init($this, $this->conf, $config, $this->basket, $this->basketView);

			// order
		$this->order = t3lib_div::makeInstance('tx_ttproducts_order');
		$this->order->init($this, $this->conf, $this->basket);
		
	} // init



	/**
	 * Getting the table definitions
	 */
	function initTables()	{
		$this->tt_products_articles = t3lib_div::makeInstance('tx_table_db');
		$this->tt_products_articles->setTCAFieldArray('tt_products_articles','articles');
		$this->tt_products_cat = t3lib_div::makeInstance('tx_ttproducts_category');
		$this->tt_products_cat->init($this->LLkey);
		$this->tt_content = t3lib_div::makeInstance('tx_ttproducts_content');
		$this->tt_content->init();
		$this->tt_products = t3lib_div::makeInstance('tx_ttproducts_product');
		$this->tt_products->init($this,$this->LLkey, $this->conf['table.']['tt_products'], $this->conf['table.']['tt_products.']);	
	} // initTables


	/**
	 * Order tracking
	 *
	 *
	 * @param	integer		Code: TRACKING, BILL or DELIVERY
	 * @return	void
	 * @see enableFields()
	 */

	function products_tracking($theCode)	{ // t3lib_div::_GP('tracking')
		global $TSFE;

//		if (strcmp($trackingCode, 'TRACKING')!=0) { // bill and delivery tracking need more data
//			$basket->mapPersonIntoToDelivery();	// This maps the billing address into the blank fields of the delivery address
//			$this->page->setPidlist($this->config['storeRootPid']);	// Set list of page id's to the storeRootPid.
//			$this->page->initRecursive(999);		// This add's all subpart ids to the pid_list based on the rootPid set in previous line
//			//tx_ttproducts_page_div::generatePageArray();		// Creates an array with page titles from the internal pid_list. Used for the display of category titles.
//		}
		$marker = t3lib_div::makeInstance('tx_ttproducts_marker');
		$marker->init($this, $this->conf, $this->config, $this->basket);

		$trackingCode = t3lib_div::_GP('tracking');
		$admin = $this->shopAdmin($updateCode = '');
			
		$msgSubpart = '';
		if ($trackingCode || $admin)	{		// Tracking number must be set
			$orderRow = $this->order->getOrderRecord('',$trackingCode);
			if (is_array($orderRow) || $admin)	{		// If order is associated with tracking id.
				if (!is_array($orderRow)) {
					$orderRow=array('uid'=>0);
				}
				switch ($theCode) {
					case 'TRACKING':
				 		$tracking = t3lib_div::makeInstance('tx_ttproducts_tracking');
				 		$tracking->init($this,$this->conf,$this->basket,$this->order,$this->price);
						$orderRecord = t3lib_div::_GP('orderRecord');
						$content = $tracking->getTrackingInformation($orderRow, $this->templateCode, $trackingCode, $updateCode, $orderRecord, $admin);
						break;
					case 'BILL':
				 		$bill = t3lib_div::makeInstance('tx_ttproducts_billdelivery');
				 		$bill->init($this,$this->conf,$this->config,$this->basket,$this->tt_products, $this->tt_products_cat, $this->tt_content, $this->order, $this->price,'bill');
				 		
						$content = $bill->getInformation($orderRow, $this->templateCode,$trackingCode);
						break;
					case 'DELIVERY':
				 		$delivery = t3lib_div::makeInstance('tx_ttproducts_billdelivery');
				 		$delivery->init($this,$this->conf,$this->config,$this->basket,$this->tt_products, $this->tt_products_cat, $this->tt_content, $this->order, $this->price,'delivery');
						$content = $delivery->getInformation($orderRow, $this->templateCode,$trackingCode);
						break;
					default:
						debug('error in '.TT_PRODUCTS_EXTkey.' calling function products_tracking with $theCode = "'.$theCode.'"');
				}
			} else {	// ... else output error page
				$msgSubpart = '###TRACKING_WRONG_NUMBER###';
			}
		} else {	// No tracking number - show form with tracking number
			$msgSubpart = '###TRACKING_ENTER_NUMBER###';
		}

		if ($msgSubpart)	{
			$content=$this->cObj->getSubpart($this->templateCode,$marker->spMarker($msgSubpart));
			if (!$TSFE->beUserLogin)	{
				$content = $this->cObj->substituteSubpart($content,'###ADMIN_CONTROL###','');
			}			
		}
		
		$markerArray=array();
		$markerArray['###FORM_URL###'] = $this->pi_getPageLink($TSFE->id,'',$marker->getLinkParams()) ; // $this->getLinkUrl();	// Add FORM_URL to globalMarkerArray, linking to self.
		$content= $this->cObj->substituteMarkerArray($content, $markerArray);

		return $content;
	}  // products_tracking


	/**
	 * Returns 1 if user is a shop admin
	 */
	function shopAdmin(&$updateCode)	{
		$admin=0;
		if ($GLOBALS['TSFE']->beUserLogin)	{
			$updateCode = t3lib_div::_GP('update_code');
			if ($updateCode == $this->conf['update_code'])	{
				$admin= 1;		// Means that the administrator of the website is authenticated.
			}
		}
		return $admin;
	}


	/**
	 * returns the codes in the order in which they have to be processed
	 *
	 * @param		string		  $fieldname is the field in the table you want to create a JavaScript for
	 * @return	  void
 	 */
	function pi_sortCodes($codes)	{
		$retCodes = array();
		$codeArray =  array (
			'1' =>  'OVERVIEW', 'BASKET', 'LIST', 'LISTOFFERS', 'LISTHIGHLIGHTS',
			'LISTNEWITEMS', 'SINGLE', 'SEARCH',
			'MEMO', 'INFO',
			'PAYMENT', 'FINALIZE',
			'TRACKING', 'BILL', 'DELIVERY',
			'CURRENCY', 'ORDERS',
			'LISTGIFTS', 'HELP',
			);

		if (is_array($codes)) {
			foreach ($codes as $k => $code) {
				$theCode = trim($code);
				$key = array_search($theCode, $codeArray);
				if ($key!=false) {
					$retCodes[$key-1] = $theCode;
				} else { // retain the wrong code to get an error message later
					$retCodes[-100] = $theCode;
				}
			}
		}
		ksort($retCodes);
		return ($retCodes);
	}


	/**
	 * Get External CObjects
	 */
	function getExternalCObject($mConfKey)	{
		if ($this->conf[$mConfKey] && $this->conf[$mConfKey.'.'])	{
			$this->cObj->regObj = &$this;
			return $this->cObj->cObjGetSingle($this->conf[$mConfKey],$this->conf[$mConfKey.'.'],'/'.$mConfKey.'/').'';
		}
	}


	function load_noLinkExtCobj()	{
		if ($this->conf['externalProcessing_final'] || is_array($this->conf['externalProcessing_final.']))	{	// If there is given another cObject for the final order confirmation template!
			$this->externalCObject = $this->getExternalCObject('externalProcessing_final');
		}
	} // load_noLinkExtCobj


	/**
	 * Displaying single products/ the products list / searching
	 */
	function products_display($theCode, &$errorMessage, $error_code)	{
		global $TSFE;

		$memoItems='';

		$this->page = tx_ttproducts_page::createPageTable($this,$this->page,$this->pid_list,$this->config['recursive']);

		if (($theCode=='SINGLE') || ($this->tt_product_single && !$this->conf['NoSingleViewOnList'])) {
			if (!$this->tt_product_single) {
				$this->tt_product_single = $this->conf['defaultProductID'];
			}
			$extVars= t3lib_div::_GP('ttp_extvars');
				// performing query:
		
			// $this->page->initRecursive($this->config['recursive']);
			//tx_ttproducts_page_div::generatePageArray();

			if (!is_object($this->singleView)) {
				// List single product:
				$this->singleView = t3lib_div::makeInstance('tx_ttproducts_single_view');
				$this->singleView->init ($this, $this->conf, $this->config, $this->basket, $this->page, $this->tt_content, 
					$this->tt_products, $this->tt_products_cat, $this->tt_product_single, $extVars, $this->pid);

				$content = $this->singleView->printView($this->templateCode, $error_code);
			}
		} else {
			include_once (PATH_BE_ttproducts.'lib/class.tx_ttproducts_list_view.php');

			// List all products:
			$listView = t3lib_div::makeInstance('tx_ttproducts_list_view');
			$listView->init ($this, $this->conf, $this->config, $this->basket, $this->page, $this->tt_content, $this->tt_products, $this->tt_products_cat, $this->pid);			
			$content = $listView->printView($this->templateCode, $theCode, $memoItems, $error_code);
		}		

		return $content;
	}	// products_display
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/tt_products/pi1/class.tx_ttproducts_pi1.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/tt_products/pi1/class.tx_ttproducts_pi1.php']);
}


?>