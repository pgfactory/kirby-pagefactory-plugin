# PageFactory Template(s)

Pages to be rendered by PageFactory must use a meta-file 'z_pfy.txt'.
Thus, Kirby invokes site/templates/z_pfy.php, which redirects rendering to PageFactory.

PageFactory in turns determines which template file (.html) to use. Default is 'page_template.html'.
(-> template can be spedified in Frontmatter of each page.)

Use {{ variables }} in the template for any variable parts.

List of useful variables:
- {{ lang }}                    // the language code, e.g. 'en'
- {{ kirby-site-title }}        // site-title as determinded by Kirby
- {{ kirby-page-title }}        // page-title as determinded by Kirby
- {{ pfy-page-title }}          // page title (default '{{ page-title }} / {{ site-title }}') *) 
- {{ generator }}			    // reference to Kirby and PageFactory
- {{ pfy-lang-selection }} 	    // autmatically generated selection menu for supported languages
- {{ pfy-admin-panel-link }}	// link to the Kirby Panel (label from ``\{{ pfy-admin-panel-link-text }}``)
- {{ pfy-login-button }}	    // login icon (assicated action to be defined by custom code)
- {{ webmaster-email }}	        // automatically derived (guessed) from domain


## Modifiers for use of variables

`^`: e.g. {{^ varname }}
: If variable 'varname' is not defined, it is replaced by an empty string.
: (Otherwise the name of the variable is left in place after remove the brackets {{}}.) 

`#`: e.g. {{# anything }}
: Comments out the variable call, thus just removes the brackets {{}} and everythin inside.

`@`: e.g. {{@ pfy-body-end-injections }}
: Instructs PageFactory to wait till the last moment before resolving this variable.
: For some variables this is necessary because macros can request asset files for loading,
: which modifies specific variables, in particular {{@ pfy-head-injections }}, 
: {{@ pfy-body-tag-attributes }} and {{@ pfy-body-end-injections }}.


*) Define/override variables via ``site/config/config.php``. Example:

    'usility.pagefactory.options' => [
        'variables' => [
             'pfy-page-title' => [
                 'de' => 'MEINE SEITE',
                 '_' => '{{ kirby-site-title }} | {{ kirby-page-title }}',
             ],
    ],
