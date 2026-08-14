<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Content.Exclusivecontent
 *
 * @copyright   Copyright (C) 2026
 * @license     GNU General Public License version 2 or later
 */

namespace Joomla\Plugin\Content\Exclusivecontent\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\SubscriberInterface;
use Joomla\CMS\Event\Content\ContentPrepareEvent;
use Joomla\CMS\Event\Model\PrepareFormEvent;
use Joomla\Registry\Registry;

final class Exclusivecontent extends CMSPlugin implements SubscriberInterface
{
	protected $autoloadLanguage = true;

	public static function getSubscribedEvents(): array
	{
		return [
			'onContentPrepareForm' => 'onContentPrepareForm',
			'onContentPrepare'     => 'onContentPrepare',
		];
	}

	/**
	 * Injects the per-article "Exclusive Content" switch into the article form.
	 */
	public function onContentPrepareForm(PrepareFormEvent $event): void
	{
		$form = $event->getForm();

		if (!$form instanceof Form) {
			return;
		}

		if ($form->getName() !== 'com_content.article') {
			return;
		}

		Form::addFormPath(JPATH_PLUGINS . '/content/exclusivecontent/forms');
		$form->loadFile('exclusivecontent', false);
	}

	/**
	 * Main processing: remove restricted content + inject login form.
	 * SECURITY: Restricted text is NEVER sent to the browser for unauthorized users.
	 */
	public function onContentPrepare(ContentPrepareEvent $event): void
	{
		if (!$this->getApplication()->isClient('site')) {
			return;
		}

		if (!(int) $this->params->get('enabled', 1)) {
			return;
		}

		$context = $event->getContext();
		$article = $event->getItem();

		if (empty($article->text)) {
			return;
		}

		$user      = $this->getApplication()->getIdentity();
		$hasAccess = $this->userHasAccess($user);

		// 1. Process shortcodes {exclusive}...{/exclusive}
		//    → Content inside is completely removed if user has no access
		$article->text = $this->processShortcodes($article->text, $hasAccess);

		// 2. Full article restriction (per-article flag)
		if (strpos($context, 'com_content.') === 0) {
			$articleParams   = new Registry($article->attribs ?? '{}');
			$isFullExclusive = (int) $articleParams->get('exclusive_content', 0);

			if ($isFullExclusive === 1 && !$hasAccess) {
				$this->applyFullArticleRestriction($article);
			}
		}

		// Load CSS/JS only when the form is actually present on the page
		if (strpos($article->text, 'exclusive-content-wrapper') !== false) {
			static $assetsLoaded = false;
			if (!$assetsLoaded) {
				$this->loadAssets();
				$assetsLoaded = true;
			}
		}
	}

	/**
	 * Checks if the current user has access according to plugin settings.
	 */
	private function userHasAccess($user): bool
	{
		$restrictMode = $this->params->get('restrict_mode', 'guest');

		if ($restrictMode === 'guest') {
			return !$user->guest;
		}

		// Specific groups mode
		$allowedGroups = array_map('intval', (array) $this->params->get('allowed_groups', []));
		$userGroups    = $user->getAuthorisedGroups();

		return count(array_intersect($allowedGroups, $userGroups)) > 0;
	}

	/**
	 * Process {exclusive}...{/exclusive} shortcodes.
	 * CRITICAL SECURITY RULE:
	 * When user has no access the content inside the tags is completely
	 * removed from the HTML. No blur, no hidden text, nothing.
	 */
	private function processShortcodes(string $text, bool $hasAccess): string
	{
		$pattern = '/\{exclusive\}(.*?)\{\/exclusive\}/is';

		if (!preg_match($pattern, $text)) {
			return $text;
		}

		if ($hasAccess) {
			// User has access → remove only the tags, keep the content
			return preg_replace($pattern, '$1', $text);
		}

		// User has NO access → completely discard the content and show login form
		$form = $this->buildLoginForm();

		return preg_replace($pattern, $form, $text);
	}

	/**
	 * Full article restriction (when the per-article switch is ON).
	 * Only a teaser is kept. Fulltext is discarded completely.
	 */
	private function applyFullArticleRestriction(object $article): void
	{
		$teaserMode  = $this->params->get('teaser_mode', 'introtext');
		$teaserChars = (int) $this->params->get('teaser_chars', 400);

		$teaser = '';

		if ($teaserMode === 'introtext' && !empty($article->introtext)) {
			$teaser = $article->introtext;
		} else {
			$source = !empty($article->introtext) ? $article->introtext : $article->text;
			$plain  = strip_tags($source);
			$teaser = '<p>' . HTMLHelper::_('string.truncate', $plain, $teaserChars, true, false) . '</p>';
		}

		$article->text     = $teaser . $this->buildLoginForm();
		$article->fulltext = ''; // security: never leave fulltext in the object
	}

	/**
	 * Builds the clean login form HTML.
	 * No restricted text is ever included here.
	 */
	private function buildLoginForm(): string
	{
		$title    = Text::_($this->params->get('message_title', 'PLG_CONTENT_EXCLUSIVECONTENT_MESSAGE_TITLE_DEFAULT'));
		$subtitle = Text::_($this->params->get('message_subtitle', 'PLG_CONTENT_EXCLUSIVECONTENT_MESSAGE_SUBTITLE_DEFAULT'));

		$showRegister = (int) $this->params->get('show_register_link', 1);
		$registerUrl  = trim($this->params->get('register_url', ''));
		
		$registerText     = Text::_($this->params->get('register_text', 'PLG_CONTENT_EXCLUSIVECONTENT_REGISTER_TEXT_DEFAULT'));
		$registerLinkText = Text::_($this->params->get('register_link_text', 'PLG_CONTENT_EXCLUSIVECONTENT_REGISTER_LINK_TEXT_DEFAULT'));
		
		$showRecovery = (int) $this->params->get('show_recovery_links', 1);

		if ($showRegister && $registerUrl === '') {
			$registerUrl = 'index.php?option=com_users&view=registration';
		}

		$bgStyle = $this->params->get('background_style', 'clean'); // clean | solid
		$bgColor = $this->params->get('background_color', '#f8f9fa');

		$wrapperStyle = '';
		if ($bgStyle === 'solid') {
			$wrapperStyle = ' style="background-color:' . htmlspecialchars($bgColor, ENT_QUOTES, 'UTF-8') . ';"';
		}

		$token  = Session::getFormToken();
		$return = base64_encode(Uri::getInstance()->toString());

		ob_start();
		?>
		<div class="exclusive-content-container">
			<div class="exclusive-content-wrapper exclusive-bg-<?php echo htmlspecialchars($bgStyle, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $wrapperStyle; ?>>
				<div class="exclusive-content-card">
				<h3 class="exclusive-title"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h3>
				<p class="exclusive-subtitle"><?php echo htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8'); ?></p>

				<form id="exclusive-login-form" class="exclusive-login-form" method="post"
					  action="<?php echo Uri::root(); ?>index.php?option=com_users&task=user.login">

					<div class="form-group">
						<label for="exclusive-username"><?php echo Text::_('JGLOBAL_USERNAME'); ?> / E-mail</label>
						<input type="text" name="username" id="exclusive-username" class="form-control"
							   required autocomplete="username" placeholder="<?php echo Text::_('JGLOBAL_USERNAME'); ?> / E-mail">
					</div>

					<div class="form-group">
						<label for="exclusive-password"><?php echo Text::_('JGLOBAL_PASSWORD'); ?></label>
						<div class="password-wrapper">
							<input type="password" name="password" id="exclusive-password" class="form-control"
								   required autocomplete="current-password">
							<button type="button" class="toggle-password" aria-label="Mostrar senha">👁</button>
						</div>
					</div>

					<div id="exclusive-login-error" class="exclusive-error" style="display:none;"></div>

					<button type="submit" class="btn btn-primary exclusive-btn-login">
						<?php echo Text::_('JLOGIN'); ?>
					</button>

					<?php if ($showRecovery) : ?>
					<div class="exclusive-links">
						<a href="<?php echo Uri::root(); ?>index.php?option=com_users&view=remind">
							<?php echo Text::_('COM_USERS_LOGIN_REMIND'); ?>
						</a>
						<a href="<?php echo Uri::root(); ?>index.php?option=com_users&view=reset">
							<?php echo Text::_('COM_USERS_LOGIN_RESET'); ?>
						</a>
					</div>
					<?php endif; ?>

					<?php if ($showRegister && $registerUrl) : ?>
						<div class="exclusive-register">
							<?php echo htmlspecialchars($registerText, ENT_QUOTES, 'UTF-8'); ?>
							<a href="<?php echo htmlspecialchars($registerUrl, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($registerLinkText, ENT_QUOTES, 'UTF-8'); ?></a>
						</div>
					<?php endif; ?>

					<input type="hidden" name="return" value="<?php echo $return; ?>">
					<input type="hidden" name="<?php echo $token; ?>" value="1">
				</form>
			</div>
		</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Load CSS + JS only when needed
	 */
	private function loadAssets(): void
	{
		$wa = $this->getApplication()->getDocument()->getWebAssetManager();

		$wa->registerAndUseStyle(
			'plg_content_exclusivecontent.style',
			'plg_content_exclusivecontent/exclusivecontent.css'
		);

		$wa->registerAndUseScript(
			'plg_content_exclusivecontent.script',
			'plg_content_exclusivecontent/exclusivecontent.js',
			[],
			['defer' => true]
		);
	}
}