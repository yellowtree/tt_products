<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2005-2009 Franz Holzinger <franz@ttproducts.de>
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
 * Part of the tt_products (Shop System) extension.
 *
 * functions for the display of forms
 *
 * $Id$
 *
 * @author  Franz Holzinger <franz@ttproducts.de>
 * @maintainer	Franz Holzinger <franz@ttproducts.de>
 * @package TYPO3
 * @subpackage tt_products
 *
 *
 */

class tx_ttproducts_form_div {


	public function createSelect (
		&$langObj,
		&$valueArray,
		$name,
		$selectedKey,
		$bSelectTags=TRUE,
		$bTranslateText=TRUE,
		$allowedArray=array(),
		$type='select',
		$mainAttributeArray=array(),
		$layout='',
		$imageFileArray=''
	) {
		global $TSFE;

		$bUseXHTML = $TSFE->config['config']['xhtmlDoctype'] != '';

		if (is_array($valueArray))	{
			$totaltext = '';

			foreach ($valueArray as $key => $parts) {

				if (is_array($parts))	{
					$selectKey = $parts[1];
					$selectValue = $parts[0];
				} else {
					$selectKey = $key;
					$selectValue = $parts;
				}

				if ($bTranslateText)	{
					$tmp = tx_div2007_alpha::sL_fh001($selectValue);
					$text = tx_div2007_alpha::getLL($langObj, $tmp);
				} else {
					$text = '';
				}
				if ($text == '')	{
					if (strpos($selectValue,'LLL:EXT') === 0)	{
						continue;
					}
					$text = $selectValue;
				}

				if (!count($allowedArray) || in_array($selectKey, $allowedArray))	{
					$nameText = htmlentities(trim($text),ENT_QUOTES,$TSFE->renderCharset);
					$valueText = htmlentities($selectKey,ENT_QUOTES,$TSFE->renderCharset);
					$selectedText = '';
					$paramArray = array();
					$preParamArray = array();

					if (strcmp($selectKey,$selectedKey)==0)	{
						switch ($type)	{
							case 'select':
								$selectedText = ($bUseXHTML ? ' selected="selected"' : ' selected');
								$paramArray['selected'] = 'selected';
								break;
							case 'checkbox':
							case 'radio':
								$selectedText = ($bUseXHTML ? ' checked="checked"' : ' checked');
								$paramArray['checked'] = 'checked';
								break;
						}
					}

					switch ($type)	{
						case 'select':
							$inputText = '<option value="' . $valueText . '"' . $selectedText . '>' . $nameText . '</option>';
							break;
						case 'checkbox':
						case 'radio':
							$preParamArray['type'] = $type;
							$inputText = self::createTag('input', $name, $valueText, $preParamArray, $paramArray);

// '<input type="'.$type.'" name="'.$name.'" value="'.$valueText.'" '.$selectedText.'>.'
							$inputText .=  ' ' . $nameText . '<br '. ($bUseXHTML ? '/' : '') . '/>';
							break;
					}
					if ($layout == '')	{
						$totaltext .= $inputText;
					} else	{
						$tmpText = str_replace('###INPUT###',$inputText,$layout);
						if (is_array($imageFileArray) && isset($imageFileArray[$key]))	{
							$tmpText = str_replace('###IMAGE###',$imageFileArray[$key],$tmpText);
						}
						$totaltext .= $tmpText;
					}
				}
			} // foreach ($valueArray as $key => $parts) {
			if ($bSelectTags && $type == 'select' && $name!='')	{
				$mainAttributes = self::getAttributeString($mainAttributeArray);
				$text = '<select name="' . $name . '"' . $mainAttributes . '>' . $totaltext . '</select>';
			} else {
				$text = $totaltext;
			}
		} else {
			$text = FALSE;
		}
		return $text;
	}


	// fetches the valueArray needed for the functions of this class form a valueArray setup
	public function fetchValueArray ($confArray)	{
		$rcArray = array();
		if (is_array($confArray))	{
			foreach ($confArray as $k => $vArray)	{
				$rcArray [] = array(0 => $vArray['label'], 1 => $vArray['value']);
			}
		}
		return $rcArray;
	}


	public function getKeyValueArray ($valueArray)	{
		$rc = array();

		foreach ($valueArray as $k => $row)	{
			$rc[$row[1]] = $row[0];
		}
		return $rc;
	}


	protected function getAttributeString ($mainAttributeArray)	{
		global $TSFE;

		$bUseXHTML = $TSFE->config['config']['xhtmlDoctype'] != '';
		$rc = '';
		if (count($mainAttributeArray))	{

			foreach ($mainAttributeArray as $attribute => $value)	{
				if ($bUseXHTML || $attribute != 'checked' && $attribute != 'selected')	{
					$rc .= ' ' . $attribute . '="' . $value . '"';
				} else {
					$rc .= ' ' . $attribute;
				}
			}
		}
		return $rc;
	}


	public function createTag ($tag, $name, $value, $preMainAttributes='', $mainAttributes='')	{
		global $TSFE;

		$bUseXHTML = $TSFE->config['config']['xhtmlDoctype'] != '';
		$attributeTextArray = array();
		$attributeArray = array();
		$attributeArray['pre'] = $preMainAttributes;
		$attributeArray['post'] = $mainAttributes;

		foreach ($attributeArray as $k => $attributes)	{
			if (is_array($attributes))	{
				$attributeTextArray[$k] = self::getAttributeString($attributes);
			} else {
				if ($attributes != '' && $attributes{0} != ' ')	{
					$attributeTextArray[$k] = ' ' . $attributes;
				}
			}
		}
		$rc = '<' . $tag . $attributeTextArray['pre'] . ' name="' . $name . '" value="' . $value . '"' . $attributeTextArray['post'] . ($bUseXHTML ? '/' : '') . '>';
		return $rc;
	}
}


?>
