PageFactory
-----------

Place your site-wide custom styles sheets in .scss format here (e.g. my.scss).
These files will be automatically compiled and saved in the parent directory (content/assets/css/).

From there you need to explicitly load them into the page (either via Frontmatter as `assets: ~assets/css/-my.css` or 
by code as `PageFactory::$pg->addAssets('~assets/css/-my.css');`)


Note: a leading dash signals that the asset file is automatically generated and can be deleted any time.


