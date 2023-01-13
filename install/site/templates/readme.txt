# PageFactory's Twig-Template(s)

Pages to be rendered by PageFactory must use a meta-file 'z.txt'.
Thus, Kirby invokes site/templates/zy.twig.

Use {{ variables }} in the template for any variable parts.

List of useful variables:
- {{ page.lang}}				// the language code, e.g. 'en'
- {{ page.langActive}}			// can be lang-variant, e.g. de2
- {{ page.pageUrl}}				// URL of current page
- {{ page.appUrl}}				// URL of app root
- {{ page.generator}}			// reference to Kirby and PageFactory
- {{ page.phpVersion}}			// version of PHP
- {{ page.pageTitle}}			// page-title as determinded by Kirby
- {{ page.siteTitle}}			// site-title as determinded by Kirby
- {{ page.headTitle}}			// page title (default '{{ page-title }} / {{ site-title }}')
- {{ page.webmasterEmail}}		// automatically derived (guessed) from domain
- {{ page.menuIcon}}			// hamburger icon
- {{ page.smallScreenHeader}}	// default is site-title + menu-icon
- {{ page.langSelection}}		// automatically generated selection menu for supported languages
- {{ page.loggedInAsUser}}		// username of logged in user, login-link if not logged in
- {{ page.loginButton}}			// login icon
- {{ page.adminPanelLink}}		// link to the Kirby Panel (label from var pfy-admin-panel-link-text)



*) Define/override variables via ``site/config/config.php``. Example:

    'usility.pagefactory.options' => [
        'variables' => [
             'pfy-page-title' => [
                 'de' => 'MEINE SEITE',
                 '_' => '{{ kirby-site-title }} | {{ kirby-page-title }}',
             ],
    ],
