# Exclusive Content Plugin for Joomla

This plugin allows you to restrict specific parts of your articles, or entire articles, strictly to logged-in users or specific VIP user groups. 

## Features

- Restrict parts of an article using shortcodes.
- Restrict an entire article using the article settings.
- Fully customizable texts and styles via the Joomla plugin manager.
- Integrates naturally with Joomla's user access levels.

## How to use

### 1. Restricting parts of a text (Shortcode)
Wrap the text you want to restrict between {exclusive} and {/exclusive}. The hidden content will be replaced by a login or subscription form for unauthorized users.

### 2. Restricting the entire article
When creating or editing a Joomla article, open the Options tab and find the 'Exclusive Content' field. Activating it will automatically lock the article right after the Intro Text (Read more separator), preventing the rest of the text from being sent to the browser.

## Security
The restricted content is completely removed from the source code before rendering, ensuring that unauthorized users cannot access it even by inspecting the page source.

## Installation
1. Download the latest release from the Releases page.
2. Go to your Joomla Administrator Panel -> System -> Install -> Extensions.
3. Upload the package file and let Joomla handle the rest.

## Authorship
Developed by Uziel via Ponto Mega.
