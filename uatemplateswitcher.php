<?php
/**
 * ---------------------------------------------------------------------------------------------------------
 * User Agent Template Switcher
 *
 * Version 1.0.0
 *
 * Copyright (C) 2016 Rene Kreijveld. All rights reserved.
 * Based on the original work of BlackRed Designs.
 *
 * User Agent Template Switcher is free software and is distributed under the GNU General Public License,
 * and as distributed it may include or be derivative of works licensed under the GNU
 * General Public License or other free or open source software licenses.
 * ---------------------------------------------------------------------------------------------------------
 **/

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport('joomla.plugin.plugin');

class  plgSystemUATemplateSwitcher extends JPlugin
{

	public function onAfterRoute()
	{
		$app = JFactory::getApplication();

		if ($app->isAdmin()) return;

		$enabledJts = (int)$this->params->get('mobile_switch_enabled', 0);

		// check for change to desktop template request
		// If request come from url via uatpl url parameter?
		$changeTplRequest = JRequest::getInt('uatpl', 0);
		$doChangeReq = false;
		if ($changeTplRequest < 1)
		{
			// Check current user session
			$changeTplRequest = $app->getUserStateFromRequest('uatpl', 'uatpl', 0, 'int');
			if ($changeTplRequest > 0)
			{
				$doChangeReq = true;
			}
			else
			{
				// Check cookie?
				jimport('joomla.utilities.utility');
				$hash = JApplication::getHash('UATEMPLATESWITCHER_TPL');
				$changeTplRequest = JRequest::getInt($hash, 0, 'cookie', JREQUEST_ALLOWRAW | JREQUEST_NOTRIM);
				if ($changeTplRequest > 0)
				{
					$doChangeReq = true;
				}
			}
		}
		else
		{
			$doChangeReq = true;
		}

		$tpl = false;
		if ($doChangeReq)
		{
			// Apply this change tpl request
			$force = JRequest::getInt('force', -1);
			if ($force == 0)
			{
				// oh, you want to switch to default template
				$tpl = false;
				// Remove cookie + session
				$config = JFactory::getConfig();
				$cookie_domain = $config->get('cookie_domain', '');
				$cookie_path = $config->get('cookie_path', '/');
				$lifetime = time() + 365 * 24 * 60 * 60;
				setcookie(JApplication::getHash('UATEMPLATESWITCHER_TPL'), -1, $lifetime, $cookie_path, $cookie_domain);
				$app->setUserState('autpl', -1);
			}
			else
			{
				$tpl = $this->getTemplateById($changeTplRequest);
			}
		}
		else
		{
			// If we have enable mobile detect function? Detect if users are browsing from a mobile device!
			if ($enabledJts == 1)
			{
				$isMobile = self::isMobileRequest();
				if ($isMobile)
				{
					$mobileTpl = $this->params->get('mobile_template', '');
					$tpl = $this->getTemplateByName($mobileTpl);
				}
			}
		}

		// We have new template to apply
		if ($tpl)
		{
			// Apply this template
			$this->_setTemplate($tpl->template);
			$app->getTemplate(true)->params = new JRegistry($tpl->params);
			$app->setUserState('uatpl', $tpl->id);

			// Save to cookie
			$config = JFactory::getConfig();
			$cookie_domain = $config->get('cookie_domain', '');
			$cookie_path = $config->get('cookie_path', '/');
			$lifetime = time() + 365 * 24 * 60 * 60;
			setcookie(JApplication::getHash('UATEMPLATESWITCHER_TPL'), $tpl->id, $lifetime, $cookie_path, $cookie_domain);
		}
	}

	/**
	 * Check if users come from mobile devices or not
	 */
	public function isMobileRequest()
	{
		$isMobile = false;

		if (!class_exists('Mobile_Detect'))
		{
			include_once(dirname(__FILE__) . '/lib/Mobile_Detect.php');
		}

		$detect = new Mobile_Detect;

		// Check if visitor has a phone or a tablet
		if ($detect->isMobile()) $isMobile = true;

		// If tablets are included, test of the device is a tablet
		if ($this->params->get('include_tablets', 0) == 1)
		{
			if ($detect->isTablet())
			{
				$isMobile = true;
			}
			else
			{
				$isMobile = false;
			}
		}
		else
		{
			// Tablets are to be excluded, so if device is a tablet, exclude it.
			if ($detect->isTablet())
			{
				$isMobile = false;
			}
		}

		return $isMobile;
	}

	/**
	 * Get default menu ItemID
	 * @return number
	 */
	public static function getDefaultPageId()
	{
		$defaultID = 0;
		$db = JFactory::getDbo();
		$query = $db->getQuery(true)
			->select($db->quoteName('id'))
			->from($db->quoteName('#__menu'))
			->where($db->quoteName('home') . ' = 1');
		$db->setQuery($query);
		$menuItem = $db->loadObject();
		if ($menuItem)
		{
			$defaultId = $menuItem->id;
		}

		return $defaultId;
	}

	/**
	 * Cached function for getCachedDefaultPageId
	 */
	public function getCachedDefaultPageId()
	{
		$cache = & JFactory::getCache();
		$cache->setCaching(1);

		return $cache->call(array('plgSystemMobiledetector', 'getDefaultPageId'));
	}

	/**
	 * Get template info by provide its ID
	 * @param Integer $tplId
	 */
	private function getTemplateById($tplId)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true)
			->select($db->quoteName(array('id','template','params')))
			->from($db->quoteName('#__template_styles'))
			->where($db->quoteName('client_id') . ' = 0')
			->where($db->quoteName('id') . ' = ' . (int)$tplId);
		$db->setQuery($query);
		$template = $db->loadObject();
		if (!$template)
		{
			return false;
		}
		else
		{
			return $template;
		}
	}

	/**
	 * Get template info by provide its name
	 * @param string $tplName
	 */
	private function getTemplateByName($tplName)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true)
			->select($db->quoteName(array('id','template','params')))
			->from($db->quoteName('#__template_styles'))
			->where($db->quoteName('client_id') . ' = 0')
			->where($db->quoteName('template') . ' LIKE "' . $tplName . '"');
		$db->setQuery($query);
		$template = $db->loadObject();
		if (!$template)
		{
			return false;
		}
		else
		{
			return $template;
		}
	}

	/**
	 * Set template that apply to the whole system
	 * @param object $tpl
	 */
	protected function _setTemplate($tpl = null)
	{
		if (empty($tpl))
		{
			return;
		}
		else
		{
			$app = &JFactory::getApplication();
			$app->setTemplate($tpl);

			// For sh404SEF
			if (!defined('SHMOBILE_MOBILE_TEMPLATE_SWITCHED')) {
				define('SHMOBILE_MOBILE_TEMPLATE_SWITCHED', 1);
			}
		}
	}
}