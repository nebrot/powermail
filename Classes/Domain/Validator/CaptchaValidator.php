<?php
namespace In2code\Powermail\Domain\Validator;

use In2code\Powermail\Domain\Model\Answer;
use In2code\Powermail\Domain\Model\Field;
use In2code\Powermail\Domain\Model\Form;
use In2code\Powermail\Domain\Model\Mail;
use In2code\Powermail\Utility\TypoScriptUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * CaptchaValidator
 *
 * @package powermail
 * @license http://www.gnu.org/licenses/lgpl.html
 * GNU Lesser General Public License, version 3 or later
 */
class CaptchaValidator extends AbstractValidator {

	/**
	 * @var \In2code\Powermail\Domain\Service\CalculatingCaptchaService
	 * @inject
	 */
	protected $calculatingCaptchaService;

	/**
	 * Captcha Session clean (only if mail is out)
	 *
	 * @var bool
	 */
	protected $clearSession = TRUE;

	/**
	 * Captcha arguments found
	 *
	 * @var bool
	 */
	protected $captchaArgumentFound = FALSE;

	/**
	 * Validation of given Params
	 *
	 * @param Mail $mail
	 * @return bool
	 */
	public function isValid($mail) {
		if (!$this->formHasCaptcha($mail->getForm())) {
			return TRUE;
		}

		foreach ($mail->getAnswers() as $answer) {
			/** @var Answer $answer */
			if ($answer->getField()->getType() !== 'captcha') {
				continue;
			}

			$this->setCaptchaArgumentFound(TRUE);
			if (!$this->validCodePreflight($answer->getValue(), $answer->getField())) {
				$this->setErrorAndMessage($answer->getField(), 'captcha');
			}

		}

		// if no captcha arguments given (maybe deleted from DOM)
		if (!$this->getCaptchaArgumentFound()) {
			$this->addError('captcha', 0);
			$this->setIsValid(FALSE);
		}

		return $this->getIsValid();

	}

	/**
	 * Check if given string is correct
	 *
	 * @param string $value
	 * @param Field $field
	 * @return bool
	 */
	protected function validCodePreflight($value, $field) {
		switch (TypoScriptUtility::getCaptchaExtensionFromSettings($this->settings)) {
			case 'captcha':
				session_start();
				$generatedCaptchaString = $_SESSION['tx_captcha_string'];
				if ($this->getClearSession()) {
					$_SESSION['tx_captcha_string'] = '';
				}
				if (!empty($value) && $generatedCaptchaString === $value) {
					return TRUE;
				}
				break;

			default:
				if ($this->calculatingCaptchaService->validCode($value, $field, $this->getClearSession())) {
					return TRUE;
				}
		}

		return FALSE;
	}

	/**
	 * Checks if given form has a captcha
	 *
	 * @param \In2code\Powermail\Domain\Model\Form $form
	 * @return boolean
	 */
	protected function formHasCaptcha(Form $form) {
		$form = $this->formRepository->hasCaptcha($form);
		return count($form) ? TRUE : FALSE;
	}

	/**
	 * @return boolean
	 */
	public function getClearSession() {
		return $this->clearSession;
	}

	/**
	 * @param boolean $clearSession
	 * @return void
	 */
	public function setClearSession($clearSession) {
		$this->clearSession = $clearSession;
	}

	/**
	 * @return boolean
	 */
	public function getCaptchaArgumentFound() {
		return $this->captchaArgumentFound;
	}

	/**
	 * @param boolean $captchaArgumentFound
	 * @return void
	 */
	public function setCaptchaArgumentFound($captchaArgumentFound) {
		$this->captchaArgumentFound = $captchaArgumentFound;
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		$pluginVariables = GeneralUtility::_GET('tx_powermail_pi1');
		// clear captcha only on create action
		$this->setClearSession(($pluginVariables['action'] === 'create' ? TRUE : FALSE));
	}
}