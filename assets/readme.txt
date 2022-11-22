# Plugin Asset Folder

Note:  
Files which have a leading '-' are created by PageFactory and can be deleted. They will be re-built automatically.

## ``css``

-> contains compiled SCSS files in `site/plugins/pagefactory/scss/`

## ``icons``

-> contains some icons specifically tailored for PageFactory.

## ``js``

-> contains JavaScript files

With the exception of `-pagefactory.js`, these files need to be explictly queued for loading into the page.
This can be achieved in any page's FrontMatter as `~pagefactory/xy.js`

## ``js/autoload``

-> contains JavaScript files that are automatically loaded into any pages (rendered by PageFactory)

## ``svg-icons``

-> free icons available through macro ``icon()`` or manually invoked

