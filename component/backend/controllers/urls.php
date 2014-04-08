<?php
/**
 *  @package DocImport
 *  @copyright Copyright (c)2010-2014 Nicholas K. Dionysopoulos
 *  @license GNU General Public License version 3, or later
 */

// Protect from unauthorized access
defined('_JEXEC') or die();

JLoader::import('joomla.application.component.controller');

if(!class_exists('JoomlaCompatController')) {
	if(interface_exists('JController')) {
		abstract class JoomlaCompatController extends JControllerLegacy {}
	} else {
		class JoomlaCompatController extends JController {}
	}
}

class DocimportControllerUrls extends JoomlaCompatController
{
	public function nuke()
	{
		F0FModel::getAnInstance('Urls','DocimportModel')
			->nuke();

		$this->setRedirect('index.php?option=com_docimport', JText::_('COM_DOCIMPORT_CPANEL_NUKEURLS_DONE'));
	}
}