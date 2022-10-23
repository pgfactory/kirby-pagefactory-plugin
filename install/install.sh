#!/usr/bin/env bash
# Usage:
#   install.sh path-to-webapp-folder
#
# if arg is omitted, script assumes you already navigated to your new web-app folder.
# NOTE: in that case the folder must be empty, i.e. the install.sh script must
# be stored somewhere else.

if [ "$1" != "" ]; then
	echo "Ok go"
	mkdir $1
	cd $1
fi


## select the branch you want to check out:
branch=''
# branch='-b Dev'


## Check/clone Kirby plainkit:
if [ ! -e kirby/ ]; then
	/usr/local/bin/git clone https://github.com/getkirby/plainkit.git .
	echo Kirby installed
else
	echo Kerby already installed
fi


## Clone PageFactory:
/usr/local/bin/git clone $branch https://github.com/pgfactory/kirby-pagefactory-plugin.git site/plugins/pagefactory
echo PageFactory installed


## Check/copy essential files to final location:
if [ ! -e site/templates/page_template.html ]; then
	cp -R site/plugins/pagefactory/install/assets/   assets
	cp -R site/plugins/pagefactory/install/content/  content
	cp -R site/plugins/pagefactory/install/site/     site
	cp content/home/home.txt content/home/zzz_page.txt
	mv content/home/home.txt content/home/zzz_page.en.txt
	cp content/site.txt content/site.en.txt
	mv content/home content/1_home
	echo Essential files copied to final location
fi

echo
echo Now open this website in your browser.
