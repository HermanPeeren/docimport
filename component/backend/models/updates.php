<?php
/**
 * @package   DocImport
 * @copyright Copyright (c)2010-2016 Nicholas K. Dionysopoulos
 * @license   GNU General Public License version 3, or later
 */

// Protect from unauthorized access
defined('_JEXEC') or die();

/**
 * The updates provisioning Model
 */
class DocimportModelUpdates extends F0FUtilsUpdate
{
	/**
	 * Public constructor. Initialises the protected members as well.
	 *
	 * @param array $config
	 */
	public function __construct($config = array())
	{
		parent::__construct($config);

		$this->updateSiteName = 'Akeeba DocImport';
		$this->updateSite = 'http://cdn.akeebabackup.com/updates/docimport.xml';
	}

	public function autoupdate()
	{
		$return = array(
			'message' => ''
		);

		// First of all let's check if there are any updates
		$updateInfo = (object)$this->getUpdates(true);

		// There are no updates, there's no point in continuing
		if (!$updateInfo->hasUpdate)
		{
			return array(
				'message' => array("No available updates found")
			);
		}

		$return['message'][] = "Update detected, version: " . $updateInfo->version;

		// Ok, an update is found, what should I do?
		$params = JComponentHelper::getParams('com_docimport');
		$autoupdate = $params->get('autoupdateCli', 1);

		// Let's notifiy the user
		if ($autoupdate == 1 || $autoupdate == 2)
		{
			$email = $params->get('notificationEmail');

			if (!$email)
			{
				$return['message'][] = "There isn't an email for notifications, no notification will be sent.";
			}
			else
			{
				// Ok, I can send it out, but before let's check if the user set any frequency limit
				$numfreq = $params->get('notificationFreq', 1);
				$freqtime = $params->get('notificationTime', 'day');
				$lastSend = $this->getLastSend();
				$shouldSend = false;

				if (!$numfreq)
				{
					$shouldSend = true;
				}
				else
				{
					$check = strtotime('-' . $numfreq . ' ' . $freqtime);

					if ($lastSend < $check)
					{
						$shouldSend = true;
					}
					else
					{
						$return['message'][] = "Frequency limit hit, I won't send any email";
					}
				}

				if ($shouldSend)
				{
					if ($this->sendNotificationEmail($updateInfo->version, $email))
					{
						$return['message'][] = "E-mail(s) correctly sent";
					}
					else
					{
						$return['message'][] = "An error occurred while sending e-mail(s). Please double check your settings";
					}

					$this->setLastSend();
				}
			}
		}

		// Let's download and install the latest version
		if ($autoupdate == 1 || $autoupdate == 3)
		{
			$return['message'][] = $this->updateComponent();
		}

		return $return;
	}

	private function sendNotificationEmail($version, $email)
	{
		$email_subject = <<<ENDSUBJECT
THIS EMAIL IS SENT FROM YOUR SITE "[SITENAME]" - Update available
ENDSUBJECT;

		$email_body = <<<ENDBODY
This email IS NOT sent by the authors of Akeeba Docimport. It is sent automatically
by your own site, [SITENAME]

================================================================================
UPDATE INFORMATION
================================================================================

Your site has determined that there is an updated version of Akeeba Docimport
available for download.

New version number: [VERSION]

This email is sent to you by your site to remind you of this fact. The authors
of the software will never contact you about available updates.

================================================================================
WHY AM I RECEIVING THIS EMAIL?
================================================================================

This email has been automatically sent by a CLI script you, or the person who built
or manages your site, has installed and explicitly activated. This script looks
for updated versions of the software and sends an email notification to all
Super Users. You will receive several similar emails from your site, up to 6
times per day, until you either update the software or disable these emails.

To disable these emails, please contact your site administrator.

If you do not understand what this means, please do not contact the authors of
the software. They are NOT sending you this email and they cannot help you.
Instead, please contact the person who built or manages your site.

================================================================================
WHO SENT ME THIS EMAIL?
================================================================================

This email is sent to you by your own site, [SITENAME]

ENDBODY;

		$jconfig = JFactory::getConfig();
		$sitename = $jconfig->get('sitename');

		$substitutions = array(
			'[VERSION]'  => $version,
			'[SITENAME]' => $sitename
		);

		$email_subject = str_replace(array_keys($substitutions), array_values($substitutions), $email_subject);
		$email_body = str_replace(array_keys($substitutions), array_values($substitutions), $email_body);

		$mailer = JFactory::getMailer();

		$mailfrom = $jconfig->get('mailfrom');
		$fromname = $jconfig->get('fromname');

		$mailer->setSender(array($mailfrom, $fromname));
		$mailer->addRecipient($email);
		$mailer->setSubject($email_subject);
		$mailer->setBody($email_body);

		return $mailer->Send();
	}

	private function updateComponent()
	{
		JLoader::import('joomla.updater.update');

		$db = JFactory::getDbo();

		$updateSiteIDs = $this->getUpdateSiteIds();
		$update_site   = array_shift($updateSiteIDs);

		$query = $db->getQuery(true)
			->select($db->qn('update_id'))
			->from($db->qn('#__updates'))
			->where($db->qn('update_site_id') . ' = ' . $update_site);

		$uid = $db->setQuery($query)->loadResult();

		$update = new JUpdate();
		$instance = JTable::getInstance('update');
		$instance->load($uid);
		$update->loadFromXML($instance->detailsurl);

		if (isset($update->get('downloadurl')->_data))
		{
			$url = trim($update->downloadurl->_data);
		}
		else
		{
			return "No download URL found inside XML manifest";
		}

		$config = JFactory::getConfig();
		$tmp_dest = $config->get('tmp_path');

		if (!$tmp_dest)
		{
			return "Joomla temp directory is empty, please set it before continuing";
		}
		elseif (!JFolder::exists($tmp_dest))
		{
			return "Joomla temp directory does not exists, please set the correct path before continuing";
		}

		$p_file = JInstallerHelper::downloadPackage($url);

		if (!$p_file)
		{
			return "An error occurred while trying to download the latest version, double check your Download ID";
		}

		// Unpack the downloaded package file
		$package = JInstallerHelper::unpack($tmp_dest . '/' . $p_file);

		if (!$package)
		{
			return "An error occurred while unpacking the file, please double check your Joomla temp directory";
		}

		$installer = new JInstaller;
		$installed = $installer->install($package['extractdir']);

		// Let's cleanup the downloaded archive and the temp folder
		if (JFolder::exists($package['extractdir']))
		{
			JFolder::delete($package['extractdir']);
		}

		if (JFile::exists($package['packagefile']))
		{
			JFile::delete($package['packagefile']);
		}

		if ($installed)
		{
			return "Component successfully updated";
		}
		else
		{
			return "An error occurred while trying to update the component";
		}
	}

	private function getLastSend()
	{
		$params = JComponentHelper::getParams('com_docimport');

		return $params->get('docimport_autoupdate_lastsend', 0);
	}

	private function setLastSend()
	{
		$db = JFactory::getDbo();
		$params = JComponentHelper::getParams('com_docimport');

		$params->set('docimport_autoupdate_lastsend', time());
		$data = $params->toString();

		$query = $db->getQuery(true)
			->update($db->qn('#__extensions'))
			->set($db->qn('params') . ' = ' . $db->q($data))
			->where($db->qn('extension_id') . ' = ' . $db->q($this->extension_id));
		$db->setQuery($query)->execute();
	}
}