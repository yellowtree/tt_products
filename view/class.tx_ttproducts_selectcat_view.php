<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2006-2007 Franz Holzinger <kontakt@fholzinger.com>
*  All rights reserved
*
*  This script is part of the Typo3 project. The Typo3 project is
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
 * Part of the tt_products (Shop System) extension.
 *
 * AJAX control over select boxes for categories
 *
 * $Id$
 *
 * @author	Franz Holzinger <kontakt@fholzinger.com>
 * @maintainer	Franz Holzinger <kontakt@fholzinger.com> 
 * @package TYPO3
 * @subpackage tt_products
 *
 *
 */


global $TYPO3_CONF_VARS;

class tx_ttproducts_selectcat_view {
	var $pibase; // reference to object of pibase
	var $cnf;
	var $conf;
	var $basket;
	var $page;
	var $tt_content; // element of class tx_table_db
	var $categoryTable; // element of class tx_table_db
	var $javascript;
	var $pid; // pid where to go
	var $marker; // marker functions



	function init(&$pibase, &$cnf, &$basket, &$pid_list, &$tt_content, &$categoryTable, &$javascript, $pid) {
		$this->pibase = &$pibase;
		$this->cnf = &$cnf;
		$this->conf = &$this->cnf->conf;
		$this->basket = &$basket;
		$this->tt_content = &$tt_content;
		$this->categoryTable = &$categoryTable;
		$this->javascript = &$javascript;

		$this->pid = $pid;	
		$this->page = tx_ttproducts_page::createPageTable(
			$this->pibase,
			$this->cnf,
			$this->tt_content,
			$this->pibase->LLkey,
			$this->conf['table.']['pages'], 
			$this->conf['conf.']['pages.'],
			$this->page,
			$pid_list,
			99
		);
		$this->marker = t3lib_div::makeInstance('tx_ttproducts_marker');
		$this->marker->init($pibase, $cnf, $basket);
	}

	// returns the products list view
	function &printView(&$categoryView, &$templateCode, $theCode, &$error_code, $templateArea = 'ITEM_CATEGORY_SELECT_TEMPLATE', $pageAsCategory, $templateSuffix = '') {
		global $TSFE, $TCA, $TYPO3_CONF_VARS;
		$content='';
		$out='';
		$where='';
		$bSeparated = false;
		$categoryArray = array();
		$htmlTagMain = 'select';
		$htmlTagElement = 'option';

		$rootCat = $this->categoryTable->getRootCat();
		if ($pageAsCategory)	{
			$excludeCat = $this->pibase->cObj->data['pages'];
			if (!$rootCat)	{
				$rootCat = $excludeCat;
			}
		} else {
				// read in all categories
			$this->categoryTable->get(0, $this->page->pid_list);	// read all categories
			ksort ($this->categoryTable->dataArray);
			$excludeCat = 0;
		}
		
		$currentCat = $this->categoryTable->getParamDefault();
		$categoryArray = $this->categoryTable->getRelationArray($excludeCat,$currentCat);
		$rootpathArray = $this->categoryTable->getRootpathArray($categoryArray, $rootCat, $currentCat);

		$t = array();
		$t['listFrameWork'] = $this->pibase->cObj->getSubpart($templateCode,$this->marker->spMarker('###'.$templateArea.$templateSuffix.'###'));
		$t['categoryFrameWork'] = $this->pibase->cObj->getSubpart($t['listFrameWork'],'###CATEGORY_SINGLE###');
		$t['linkCategoryFrameWork'] = $this->pibase->cObj->getSubpart($t['categoryFrameWork'],'###LINK_CATEGORY###');
		$subpartArray = array();
		$subpartArray['###LINK_CATEGORY###'] = '###CATEGORY_TMP###';
		$tmp = $this->pibase->cObj->substituteMarkerArrayCached($t['categoryFrameWork'],array(),$subpartArray);
		$htmlParts = t3lib_div::trimExplode ('###CATEGORY_TMP###', $tmp);
		$htmlPartsMarkers = array('###ITEM_SINGLE_PRE_HTML###', '###ITEM_SINGLE_POST_HTML###');
		
		if($pos = strstr($t['listFrameWork'],'###CATEGORY_SINGLE_'))	{
			$bSeparated = true;
		}
		
		$maxDepth = 3;
		$rootArray = $this->categoryTable->getRootArray($rootCat,$categoryArray);
		$categoryView->setDepths($rootArray, $categoryArray);

		$count = 0;
		$depth = 1;
		$catArray = array();
		$catArray [(int) $depth] = &$rootArray;

		$menu = $this->conf['CSS.'][$this->categoryTable->table->name.'.']['menu'];
		$menu = ($menu ? $menu : $this->categoryTable->piVar.$depth);
		$fill = '';
		if ($bSeparated)	{
			$fill = ' onchange="fillSelect(this,2,1);"';
		}

		$selectArray = array();
		if (is_array($this->conf['form.'][$theCode.'.']) && is_array($this->conf['form.'][$theCode.'.']['dataArray.']))	{
			foreach ($this->conf['form.'][$theCode.'.']['dataArray.'] as $k => $setting)	{
				if (is_array($setting))	{
					$selectArray[$k] = array();
					$type = $setting['type'];
					if ($type)	{
						$parts = t3lib_div::trimExplode('=', $type);
						if ($parts[1] == 'select')	{
							$selectArray[$k]['name'] = $parts[0];
						}
					}
					$label = $setting['label'];
					if ($label)	{
						$selectArray[$k]['label'] = $label;
					}
					$params = $setting['params'];
					if ($params)	{
						$selectArray[$k]['params'] = $params;
					}
				}
			}
		}

		$select = current($selectArray);
		if (is_array($select))	{
			if ($select['name'])	{
				$name = 'name="'.$select['name'].'" ';
			}
			if ($select['label'])	{
				$label = $select['label'].' ';
			}
			if ($select['params'])	{
				$params = $select['params'];
			}
		}
		$out = $label.'<'.$htmlTagMain.' id="'.$menu.'" '.$name.$fill.$params.'>';
		// empty select entry at the beginning
		$out .= '<option value="0"></option>';
		$out = str_replace($htmlPartsMarkers[0], $out, $htmlParts[0]);

		foreach ($catArray[$depth] as $k => $actCategory)	{
			$css = ($actCategory == $currentCat ? 'class="act"' : $css);
			$preOut = '<'.$htmlTagElement.($css ? ' '.$css : '').' value="'.$actCategory.'">';
			$out .= str_replace($htmlPartsMarkers[0], $preOut, $htmlParts[0]);
			$linkOut = htmlspecialchars($categoryArray[$actCategory]['title']);
			$out .= str_replace('###LIST_LINK###', $linkOut, $t['linkCategoryFrameWork']);
			$postOut = '</'.$htmlTagElement.'>';
			$out .= str_replace($htmlPartsMarkers[1], $postOut, $htmlParts[1]);
		}

		$out .= '</'.$htmlTagMain.'>';
		$markerArray = array();
		$subpartArray = array();
		$wrappedSubpartArray = array();

		$markerArray = $this->marker->addURLMarkers($this->conf['PIDsearch'],$markerArray);
		$this->marker->getWrappedSubpartArray($wrappedSubpartArray);
		$subpartArray['###CATEGORY_SINGLE###'] = $out;
		if ($bSeparated)	{
			$count = intval(substr_count($t['listFrameWork'], '###CATEGORY_SINGLE_') / 2);
			if ($this->pibase->pageAsCategory == 2)	{
				// $catid = 'pid';
				$parentFieldArray = array('pid');
			} else {
				// $catid = 'cat';
				$parentFieldArray = array('parent_category');
			}
			$piVar = $this->categoryTable->piVar;
			$this->javascript->set('selectcat', array($categoryArray), 1+$count, $piVar, $parentFieldArray, array($catid), array(), 'clickShow');
			
			for ($i = 2; $i <= 1+$count; ++$i)	{
				$menu = $this->categoryTable->piVar.$i;
				$bShowSubcategories = ($i < 1+$count ? 1 : 0); 
				$boxNumber = ($i < 1+$count ? ($i+1) : 0);
				$fill = ' onchange="fillSelect(this, '.$boxNumber.','.$bShowSubcategories.');"';
				$tmp = '<'.$htmlTagMain.' id="'.$menu.'"'.$fill.'>';
				$tmp .= '<option value="0"></option>';
				$tmp .= '</'.$htmlTagMain.'>';
				$subpartArray['###CATEGORY_SINGLE_'.$i.'###'] = $tmp;
			}
			// $subpartArray['###CATEGORY_SINGLE_BUTTON'] = '<input type="button" value="Laden" onclick="fillSelect(0, '.$boxNumber.','.$bShowSubcategories.');">';
		}

		$out = $this->pibase->cObj->substituteMarkerArrayCached($t['listFrameWork'],$markerArray,$subpartArray,$wrappedSubpartArray);
		$content = $out;
		return $content;
	}
	
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/tt_products/view/class.tx_ttproducts_selectcat_view.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/tt_products/view/class.tx_ttproducts_selectcat_view.php']);
}

?>