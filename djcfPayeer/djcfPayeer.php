<?php

/**
* @version		1.0
* @package		DJ Classifieds
* @subpackage	DJ Classifieds Payment Plugin
* @copyright	Copyright (C) 2010 DJ-Extensions.com LTD, All rights reserved.
* @license		http://www.gnu.org/licenses GNU/GPL
* @autor url    https://payeer.com
* @autor email  info@payeer.com
* @Developer    Payeer
*  
* 
* DJ Classifieds is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* DJ Classifieds is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with DJ Classifieds. If not, see <http://www.gnu.org/licenses/>.
* 
*/

defined('_JEXEC') or die('Restricted access');
jimport('joomla.event.plugin');
$lang = JFactory::getLanguage();
$lang->load('plg_djclassifiedspayment_djcfPayeer', JPATH_ADMINISTRATOR);

class plgdjclassifiedspaymentdjcfPayeer extends JPlugin
{
	function plgdjclassifiedspaymentdjcfPayeer(&$subject, $config)
	{
		parent::__construct($subject, $config);
		
		$this->loadLanguage('plg_djcfPayeer');
		$params['plugin_name'] = 'djcfPayeer';
		$params['icon'] = 'logo.png';
		$params['logo'] = 'logo.png';
		$params['description'] = JText::_('PLG_DJCFPAYEER_PAYMENT_METHOD_DESC');
		$params['payment_method'] = JText::_('PLG_DJCFPAYEER_PAYMENT_METHOD_NAME');
		$params['merchant_url'] = $this->params->get('merchant_url');
		$params['merchant_id'] = $this->params->get('merchant_id');
		$params['secret_key'] = $this->params->get('secret_key');
		$params['payment_currency'] = $this->params->get('payment_currency');
		$params['ip_filter'] = $this->params->get('ip_filter');
		$params['admin_email'] = $this->params->get('admin_email');
		$params['log_file'] = $this->params->get('log_file');
		$this->params = $params;
	}
	
	function onPaymentMethodList($val)
	{
		$html = '';
		$user = JFactory::getUser();
		$type = '';
		
		if ($val['type'])
		{
			$type = '&type=' . $val['type'];
		}
		
		if ($this->params['merchant_url'] != '' 
			&& $this->params['merchant_id'] != ''
			&& $this->params['secret_key'] != '')
		{
			$paymentLogoPath = JURI::root() . 'plugins/djclassifiedspayment/' . $this->params['plugin_name'] . '/' . $this->params['plugin_name'] . '/images/' . $this->params['logo'];
			$form_action = JRoute::_('index.php?option=com_djclassifieds&task=processPayment&ptype=' . $this->params['plugin_name'] . '&pactiontype=process&id=' . $val['id'] . $type, false);
			$html = '<table cellpadding="5" cellspacing="0" width="100%" border="0"><tr>';
			
			if ($this->params['logo'] != '')
			{
				$html .= '<td class="td1" width="160" align="center">
					<img src="' . $paymentLogoPath . '" title="' . $this->params['payment_method'] . '"/>
				</td>';
			}
			
			$html .= '<td class="td2">
				<h2>Payeer</h2>
				<p style="text-align:justify;">' . $this->params['description'] . '</p>';
			
			if ($user->id == 0)
			{
				$html .= '<div class="email_box"><span>' . JText::_('JGLOBAL_EMAIL') . ':*</span> <input size="50" class="validate-email required" type="text" name="email" value=""></div>';
			}
			
			$html .= '</td>
				<td class="td3" width="130" align="center">
					<a class="button" style="text-decoration:none;" href="' . $form_action . '">' . JText::_('COM_DJCLASSIFIEDS_BUY_NOW') . '</a>
				</td>
			</tr>
			</table>';
		}
		
		return $html;
	}	
	
	function onProcessPayment()
	{
		$ptype = JRequest::getVar('ptype', '');
		$id = JRequest::getInt('id', '0');
		$html = '';

		if ($ptype == $this->params['plugin_name'])
		{
			$action = JRequest::getVar('pactiontype', '');
			
			switch ($action)
			{
				case 'process':
				
					$html = $this->process($id);
					break;
					
				case 'notify':
				
					$html = $this->_notify_url();
					break;
					
				case 'paymentmessage':
				
					$html = $this->_paymentsuccess();
					break;
					
				default:
					
					$html =  $this->process($id);
					break;
			}
		}
		
		return $html;
	}
	
	function _notify_url()
	{
		$this->loadLanguage('plg_djcfPayeer');
		
		if (isset($_POST['m_operation_id']) && isset($_POST['m_sign']))
		{
			$err = false;
			$message = '';
			
			// запись логов
			
			$log_text = 
				"--------------------------------------------------------\n" .
				"operation id       " . JRequest::getVar('m_operation_id') . "\n" .
				"operation ps       " . JRequest::getVar('m_operation_ps') . "\n" .
				"operation date     " . JRequest::getVar('m_operation_date') . "\n" .
				"operation pay date " . JRequest::getVar('m_operation_pay_date') . "\n" .
				"shop               " . JRequest::getVar('m_shop') . "\n" .
				"order id           " . JRequest::getVar('m_orderid') . "\n" .
				"amount             " . JRequest::getVar('m_amount') . "\n" .
				"currency           " . JRequest::getVar('m_curr') . "\n" .
				"description        " . base64_decode(JRequest::getVar('m_desc')) . "\n" .
				"status             " . JRequest::getVar('m_status') . "\n" .
				"sign               " . JRequest::getVar('m_sign') . "\n\n";
			
			$log_file = $this->params['log_file'];
			
			if (!empty($log_file))
			{
				file_put_contents($_SERVER['DOCUMENT_ROOT'] . $log_file, $log_text, FILE_APPEND);
			}
			
			// проверка цифровой подписи и ip

			$sign_hash = strtoupper(hash('sha256', implode(":", array(
				JRequest::getVar('m_operation_id'),
				JRequest::getVar('m_operation_ps'),
				JRequest::getVar('m_operation_date'),
				JRequest::getVar('m_operation_pay_date'),
				JRequest::getVar('m_shop'),
				JRequest::getVar('m_orderid'),
				JRequest::getVar('m_amount'),
				JRequest::getVar('m_curr'),
				JRequest::getVar('m_desc'),
				JRequest::getVar('m_status'),
				$this->params['secret_key']
			))));
			
			$valid_ip = true;
			$sIP = str_replace(' ', '', $this->params['ip_filter']);
			
			if (!empty($sIP))
			{
				$arrIP = explode('.', $_SERVER['REMOTE_ADDR']);
				if (!preg_match('/(^|,)(' . $arrIP[0] . '|\*{1})(\.)' .
				'(' . $arrIP[1] . '|\*{1})(\.)' .
				'(' . $arrIP[2] . '|\*{1})(\.)' .
				'(' . $arrIP[3] . '|\*{1})($|,)/', $sIP))
				{
					$valid_ip = false;
				}
			}
			
			if (!$valid_ip)
			{
				$message .= JText::_('DJCFPAYMENT_PAYEER_EMAIL_BODY2') .
				JText::_('DJCFPAYMENT_PAYEER_EMAIL_BODY3') . $sIP . "\n" .
				JText::_('DJCFPAYMENT_PAYEER_EMAIL_BODY4') . $_SERVER['REMOTE_ADDR'] . "\n";
				$err = true;
			}

			if (JRequest::getVar('m_sign') != $sign_hash)
			{
				$message .= JText::_('DJCFPAYMENT_PAYEER_EMAIL_BODY5');
				$err = true;
			}
			
			if (!$err)
			{
				$db = JFactory::getDBO();
				$par = JComponentHelper::getParams('com_djclassifieds');
		
				// загрузка заказа
				
				$id	= intval(JRequest::getInt('m_orderid'));
				$query = "SELECT p.*  FROM #__djcf_payments p WHERE p.id='" . $id . "'";
		    	$db->setQuery($query);
		    	$payment = $db->loadObject();
				
				if (!$payment)
				{
					$message .= JText::_('DJCFPAYMENT_PAYEER_EMAIL_BODY7');
					$err = true;
				}
				
				$order_curr = ($this->params['payment_currency'] == 'RUR') ? 'RUB' : $this->params['payment_currency'];
				$order_amount = number_format($payment->price, 2, '.', '');
				
				// проверка суммы и валюты
			
				if (JRequest::getVar('m_amount') != $order_amount)
				{
					$message .= JText::_('DJCFPAYMENT_PAYEER_EMAIL_BODY8');
					$err = true;
				}

				if (JRequest::getVar('m_curr') != $order_curr)
				{
					$message .= JText::_('DJCFPAYMENT_PAYEER_EMAIL_BODY9');
					$err = true;
				}
				
				// проверка статуса
				
				if (!$err)
				{
					switch (JRequest::getVar('m_status'))
					{
						case 'success':
						
							if ($payment->status != 'Completed')
							{
								$query = "UPDATE #__djcf_payments SET status='Completed',transaction_id='" . $id . "' "
									. "WHERE id=" . $id . " AND method='djcfPayeer'";					
								$db->setQuery($query);
								$db->query();
								
								if ($payment->type == 2)
								{
									$date_sort = date('Y-m-d H:i:s');
									$query = "UPDATE #__djcf_items SET date_sort='" . $date_sort . "' WHERE id=" . $payment->item_id;
									$db->setQuery($query);
									$db->query();
								}
								else if ($payment->type == 1)
								{
									$query = "SELECT p.points  FROM #__djcf_points p WHERE p.id='" . $payment->item_id . "'";					
									$db->setQuery($query);
									$points = $db->loadResult();
									
									$query = "INSERT INTO #__djcf_users_points (`user_id`,`points`,`description`) "
										. "VALUES ('" . $payment->user_id . "','" . $points . "','" . JText::_('COM_DJCLASSIFIEDS_POINTS_PACKAGE')." Payeer " . JText::_('COM_DJCLASSIFIEDS_PAYMENT_ID') . ' ' . $payment->id . "')";					
									$db->setQuery($query);
									$db->query();																		
								}
								else
								{
									$query = "SELECT c.*  FROM #__djcf_items i, #__djcf_categories c "
										. "WHERE i.cat_id=c.id AND i.id='" . $payment->item_id . "'";					
									$db->setQuery($query);
									$cat = $db->loadObject();
									
									$pub=0;
									
									if (($cat->autopublish=='1') || ($cat->autopublish=='0' && $par->get('autopublish')=='1'))
									{					
										$pub = 1;							 						
									}
							
									$query = "UPDATE #__djcf_items SET payed=1, pay_type='', published='" . $pub . "' "
										. "WHERE id=" . $payment->item_id;					
									$db->setQuery($query);
									$db->query();			
								}
							}
							
							break;
							
						default:
						
							if ($payment->status != 'Cancelled')
							{
								$query = "UPDATE #__djcf_payments SET status='Cancelled',transaction_id='" . $id . "' "
									. "WHERE id=" . $id . " AND method='djcfPayeer'";
								$db->setQuery($query);
								$db->query();
							}

							$message .= JText::_('DJCFPAYMENT_PAYEER_EMAIL_BODY6');
							$err = true;
							break;
					}
				}
			}
			
			if ($err)
			{
				$to = $method->admin_email;

				if (!empty($to))
				{
					$message = JText::_('DJCFPAYMENT_PAYEER_EMAIL_BODY1') . $message . "\n" . $log_text;
					$headers = "From: no-reply@" . $_SERVER['HTTP_HOST'] . "\r\n" . 
					"Content-type: text/plain; charset=utf-8 \r\n";
					mail($to, JText::_('DJCFPAYMENT_PAYEER_EMAIL_SUBJECT'), $message, $headers);
				}
				
				echo JRequest::getVar('m_orderid') . '|error';
			}
			else
			{
				echo JRequest::getVar('m_orderid') . '|success';
			}
		}
	}
	
	function process($id)
	{
		header('Content-type: text/html; charset=utf-8');
		$db = JFactory::getDBO();
		$app = JFactory::getApplication();
		$Itemid = JRequest::getInt('Itemid', '0');
		$par = JComponentHelper::getParams('com_djclassifieds');
		$user = JFactory::getUser();
		$ptype = JRequest::getVar('ptype');
		$type = JRequest::getVar('type', '');
		$row = &JTable::getInstance('Payments', 'DJClassifiedsTable');
		
		if ($type == 'prom_top')
		{        	        	
        	$query = "SELECT i.* FROM #__djcf_items i WHERE i.id=" . $id . " LIMIT 1";
        	$db->setQuery($query);
        	$item = $db->loadObject();
        	
			if (!isset($item))
			{
        		$message = JText::_('COM_DJCLASSIFIEDS_WRONG_AD');
        		$redirect='index.php?option=com_djclassifieds&view=items&cid=0';
        	}        						 

       		$row->item_id = $id;
       		$row->user_id = $user->id;
      		$row->method = $ptype;
       		$row->status = 'Start';
      		$row->ip_address = $_SERVER['REMOTE_ADDR'];
       		$row->price = $par->get('promotion_move_top_price',0);
       		$row->type = 2;
       		$row->store();

       		$amount = $par->get('promotion_move_top_price',0);
      		$itemname = $item->name;
       		$item_id = $row->id;
       		$item_cid = '&cid=' . $item->cat_id;
        }
		else if ($type == 'points')
		{
			$query = "SELECT p.* FROM #__djcf_points p WHERE p.id=" . $id . " LIMIT 1";
			$db->setQuery($query);
			$points = $db->loadObject();
			
			if (!isset($item))
			{
				$message = JText::_('COM_DJCLASSIFIEDS_WRONG_POINTS_PACKAGE');
				$redirect = 'index.php?option=com_djclassifieds&view=items&cid=0';
			}

			$row->item_id = $id;
			$row->user_id = $user->id;
			$row->method = $ptype;
			$row->status = 'Start';
			$row->ip_address = $_SERVER['REMOTE_ADDR'];
			$row->price = $points->price;
			$row->type = 1;
			$row->store();

			$amount = $points->price;
			$itemname = $points->name;
			$item_id = $row->id;
			$item_cid = '';
		}
		else
		{
			$query = "SELECT i.*, c.price as c_price FROM #__djcf_items i "
				. "LEFT JOIN #__djcf_categories c ON c.id=i.cat_id "
				. "WHERE i.id=" . $id . " LIMIT 1";
			$db->setQuery($query);
			$item = $db->loadObject();
			
			if (!isset($item))
			{
				$message = JText::_('COM_DJCLASSIFIEDS_WRONG_AD');
				$redirect = 'index.php?option=com_djclassifieds&view=items&cid=0';
			}

			$amount = 0;
		
			if (strstr($item->pay_type, 'cat'))
			{
				$amount += $item->c_price / 100;
			}
			
			if (strstr($item->pay_type, 'duration_renew'))
			{
				$query = "SELECT d.price_renew FROM #__djcf_days d WHERE d.days=" . $item->exp_days;
				$db->setQuery($query);
				$amount += $db->loadResult();
			}
			else if (strstr($item->pay_type, 'duration'))
			{
				$query = "SELECT d.price FROM #__djcf_days d WHERE d.days=" . $item->exp_days;
				$db->setQuery($query);
				$amount += $db->loadResult();
			}
		
			$query = "SELECT p.* FROM #__djcf_promotions p WHERE p.published=1 ORDER BY p.id";
			$db->setQuery($query);
			$promotions = $db->loadObjectList();
			
			foreach ($promotions as $prom)
			{
				if (strstr($item->pay_type, $prom->name))
				{
					$amount += $prom->price;
				}
			}

			$row->item_id = $id;
			$row->user_id = $user->id;
			$row->method = $ptype;
			$row->status = 'Start';
			$row->ip_address = $_SERVER['REMOTE_ADDR'];
			$row->price = $amount;
			$row->type = 0;
			$row->store();

			$itemname = $item->name;
			$item_id = $row->id;
			$item_cid = '&cid=' . $item->cat_id;
		}

		echo JText::_('PLG_DJCFPAYEER_REDIRECTING_PLEASE_WAIT');
		
		$m_url = $this->params['merchant_url'];
		$m_shop = $this->params['merchant_id'];
		$m_orderid = $item_id;
		$m_amount = number_format($amount, 2, '.', '');
		$m_curr = $this->params['payment_currency'];
		$m_desc = base64_encode($itemname);
		$m_key = $this->params['secret_key'];
		
		$arHash = array(
			$m_shop,
			$m_orderid,
			$m_amount,
			$m_curr,
			$m_desc,
			$m_key
		);
		
		$sign = strtoupper(hash('sha256', implode(":", $arHash)));

		$form = '<form id="payeerform" action="' . $m_url . '" method="POST">		
			<input type="hidden" name="m_shop" value="' . $m_shop . '">
			<input type="hidden" id="m_orderid" name="m_orderid" value="' . $m_orderid . '">
			<input type="hidden" name="m_amount" value="' . $m_amount . '">
			<input type="hidden" name="m_curr" value="' . $m_curr  . '">
			<input type="hidden" name="m_desc" value="' . $m_desc . '">
			<input type="hidden" name="m_sign" value="' . $sign . '">
		</form>';
		
		echo $form;
		
		?>
			<script type="text/javascript">
				
				callpayment();
				
				function callpayment(){
					
					var id = document.getElementById('m_orderid').value;
					
					if (id > 0 && id != '')
					{
						document.getElementById('payeerform').submit();
					}
				}
			</script>
		<?php

		die();
	}
}

?>