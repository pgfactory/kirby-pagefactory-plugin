# kirby-pagefactory-plugin

PageFactory plugin for Kirby CMS

Adds a comfort layer on top of Kirby.

## Key Features

### Reduced Visual noise

In a conventional Kirby template you might find something like this:

    <div class="column" style="--columns: 8">
      <ul class="album-gallery">
        <?php foreach ($gallery as $image): ?>
        <li>
          <a href="<?= $image->url() ?>" data-lightbox>
            <figure class="img" style="--w:<?= $image->width() ?>;--h:<?= $image->height() ?>">
              <?= $image->resize(800) ?>
            </figure>
          </a>
        </li>
        <?php endforeach ?>
      </ul>
    </div>

As you can see there is HTML mixed with PHP code.
So, PageFactory provides a mechnism to separate them:

First, you define a propre HTML template, that, as an exerpt might look like this:

    <div class="column" style="--columns: 8">
      {{ gallery }}
    </div>

Second, in PHP code, you'd define the content of this msng gallery }} variable:

	$html = "<ul class='album-gallery'>\n";
    foreach ($gallery as $image) {
    	$url = $image->url();
    	$imageSize = '--w:' . $image->width() . ';--h:' . $image->height();
    	$image->resize(800);
    	$html .= <<<EOT
        <li>
          <a href="$url" data-lightbox>
            <figure class="img" style="$imageSize">
            </figure>
          </a>
        </li>
    EOT;
	}
	$html .= "</ul>\n";
	setVariable( 'gallery', $html );



### Variables and Macros

In the example above you could see basic use of "Variables", meaning text-replacement variables.

Similar to that there are "Macros". They look like this: {{ mymacro( text: xy, id:my-macro ) }}.  
So, syntactially, they are Variables with arguments. Thus, if convenient, the example above might have been implemented with a parameter "width", like

	{{ gallery( width: 800 ) }}



### Markdown extensions

Markdown is an excellently simple way to write HTML-content without bothering with HTML syntax. 
Yet, when it comes do defining slightly more advanced web content, you are bound to run into limitations.

For instance, if you want to define a block of content with special styling. 
You could use some HTML like &lt;div class...> but that's ugly and you are bound to face additional problems.
Another possibility would be applying a class to each and every element within this block (assuming your Markdown dialect supports that).

With PageFactory's Markdown extensions for DIV-blocks you can do it more elegantly like this:

	@@@ .box
	...
	@@@

You can even define the wrapper's HTML tag, style, aria-attributes and even more. Moreover, you can nest them if you make sure that the inner markers have a different length, e.g. ``@@@@``.


### Fine-grained multi-language support

PageFactory lets you work with multiple languages on the level of individual Variables and blocks (see last example).

Variables are inherently language aware, that is. Thus, if a Variable definition provides variantes of content for different languages, the right one is rendered automatically.

Here is an example of a multilingual Variable definition (in this case from a YAML-file):

	location:
		de: Ort
		_:  Location

Note that '_' defines the default language, i.e. if a language is to be rendered for which there is no translation.

BTW, for DIV-blocks you can specify a language, then that block will be rendered only if that langauge is active:

	@@@ .box !lang=de
	Beispiel
	@@@ .box !lang=en
	Example
	@@@

So, it's really easy to define content in multiple language variantes within one page -- if that's what you want. This may not be a good idea under all cirumstances, but sooner or later you are bound to run into a situation where you'll appreciate that possibility...


### Custom Macros

As briefly touched above, PageFactory supports Macros. To define one, you just create a file in ``site/custom/``, carrying the name of your Macro.

There's a template file to get you up and running with in minutes.

### Macro-Plugins

There is a library of generic macros that ships with PageFactory (to be installed in ``/site/plugins/pagefactory-macros``).

This library covers basic functionality, such as ``Lorem()``, ``Space()``, ``Button()``, ``Dir()`` and many more.

Beyond that, there may be additional libraries for specific purposes. For instance, there is one in preparaton that covers functionality particularly useful for NGOs, non-profit organisations, clubs etc.


### SCSS

PageFactory supports SCSS/SASS (Syntactically Awesome Stylesheets) out of the box. Stylesheet that you include, styles that you employ in your source will be compiled to CSS automatically.


