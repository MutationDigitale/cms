<?php
namespace Blocks;

/**
 * Handles email message tasks.
 */
class EmailMessagesController extends BaseController
{
	/**
	 * Saves an email message
	 */
	public function actionSaveMessage()
	{
		$this->requirePostRequest();
		$this->requireAjaxRequest();

		$message = new EmailMessageModel();
		$message->key = blx()->request->getRequiredPost('key');
		$message->subject = blx()->request->getRequiredPost('subject');
		$message->body = blx()->request->getRequiredPost('body');

		if (Blocks::hasPackage(BlocksPackage::Language))
		{
			$message->language = blx()->request->getPost('language');
		}
		else
		{
			$message->language = Blocks::getLanguage();
		}

		if (blx()->emailMessages->saveMessage($message))
		{
			$this->returnJson(array('success' => true));
		}
		else
		{
			$this->returnErrorJson(Blocks::t('There was a problem saving your message.'));
		}
	}
}
