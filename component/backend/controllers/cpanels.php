<?php
/**
 *  @package DocImport
 *  @copyright Copyright (c)2010-2014 Nicholas K. Dionysopoulos
 *  @license GNU General Public License version 3, or later
 */

// Protect from unauthorized access
defined('_JEXEC') or die();

class DocimportControllerCpanels extends FOFController
{
	public function execute($task) {
		$task = 'browse';
		parent::execute($task);
	}

	public function onBeforeBrowse()
	{
		// Run maintainance tasks
		$this->getThisModel()
			->updateMagicParameters()
			->checkAndFixDatabase();

		FOFModel::getTmpInstance('Xsl','DocimportModel')
			->scanCategories();

		return true;
	}
}