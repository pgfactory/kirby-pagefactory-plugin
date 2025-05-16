scss:
strong a { color: red; }

.mdp-accordion {
	margin: 2em 0;
}
----

# Congratulations!


You successfully installed **(link: https://getkirby.com/ text:Kirby CMS)** and the **[PageFactory](https://pagefactory.info/)** plugin.


<> To modify this page...  // '<>'this creates an accorion that spans to the closing '<>'

-> edit the file ``site/home/home.md``.

{{ vgap('2em') }}

To add additional pages and administer your new website in general, 

&rarr; open the {{ adminPanelLink }}.

<>

For more information,

-> visit the {{ link(url:'https://pagefactory.info/', PageFactory Documentation Website, target:newwin) }}.

Have fun!


__END__
Everything below __END__ will be ignored...


Quick hints:
============

To insert a variable or field value, use "{{ variable }}". E.g.:

# {{ title }}


To apply content defined in the panel, you can use:
{{ field(name: 'ContentBlocks') }} // renders field 'Contentblocks'

Or any other field, e.g.:
{{ field(name: title) }} // renders field 'title'

