#!/usr/bin/env bash
# assumes you are in your new web-app's root folder.

## select the branch you want to check out:
branch=''
#branch='-b Dev'


## Check/clone Kirby plainkit:
if [ ! -e kirby/ ]; then
	/usr/local/bin/git clone https://github.com/getkirby/plainkit.git .
	echo Kirby installed
else
	echo Kerby already installed
fi


## Clone PageFactory:
/usr/local/bin/git clone $branch git@github.com:pgfactory/kirby-pagefactory-plugin site/plugins/pagefactory
echo PageFactory installed


## Check/copy essential files to final location:
if [ ! -e site/templates/page_template.html ]; then
	cp -R site/plugins/pagefactory/install/content/*  content
	cp -R site/plugins/pagefactory/install/media/*    media
	cp -R site/plugins/pagefactory/install/site/*     site
	echo Essential files copied to final location
fi

echo
echo Now open this website in your browser.
