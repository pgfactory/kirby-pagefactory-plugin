# PageFactory's Twig-Template(s)

Pages to be rendered by PageFactory must use a meta-file 'z.txt'.
Thus, Kirby invokes site/templates/z.twig.

Use {{ variables }} in the template for any variable parts.

List of useful variables:

- {{ page.pageContent|raw }}	    \// the actual page pageContent
- {{ page.lang }}				    \// the language code, e.g. 'en'
- {{ page.langActive }}			    \// can be lang-variant, e.g. de2
- {{ page.pageUrl }}			    \// URL of current page
- {{ page.appUrl }}				    \// URL of app root
- {{ page.generator }}			    \// reference to Kirby and PageFactory
- {{ page.phpVersion }}			    \// version of PHP
- {{ page.pageTitle }}			    \// page-title as determinded by Kirby
- {{ page.siteTitle }}			    \// site-title as determinded by Kirby
- {{ page.headTitle }}			    \// page title (default '{{ page.pageTitle }} / {{ page.siteTitle }}')
- {{ page.webmasterEmail }}		    \// automatically derived (guessed) from domain
- {{ page.menuIcon|raw }}		    \// hamburger icon
- {{ page.smallScreenHeader|raw }}	\// default is site-title + menu-icon
- {{ page.langSelection|raw }}		\// automatically generated selection menu for supported languages
- {{ page.loggedInAsUser|raw }}		\// username of logged in user, login-link if not logged in
- {{ page.loginButton|raw }}		\// login icon
- {{ page.adminPanelLink|raw }}		\// link to the Kirby Panel (label from var pfy-admin-panel-link-text)


## PHP-Template

Note the file "#default.php". It's the most basic but actually functional PHP-template.
Rename it to "default.php" before use.
