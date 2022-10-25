#!/usr/bin/env bash
# Usage:
#   install-pagefactory.sh {branch}
#
# script assumes you already cd'ed into your Kirby-app folder.

## select the branch you want to check out:
if [[ -z "$1" ]]; then
	branch=''
else
	branch="-b $1"
fi

## Check Kirby plainkit:
if [[ ! -e kirby/ ]]; then
	echo No Kirby installation found - please install Kirby first
	exit
fi

## Check Kirby plainkit:
if [[ -e site/plugins/pagefactory/ ]]; then
	echo Pagefactory already installed
	echo 
	exit
fi

echo Now installing Pagefactory

## Clone PageFactory:
/usr/local/bin/git clone $branch https://github.com/pgfactory/kirby-pagefactory-plugin.git site/plugins/pagefactory
echo PageFactory installed


## Check/copy essential files to final location:
if [ ! -e site/templates/page_template.html ]; then
	cp -R site/plugins/pagefactory/install/assets/   assets
	cp -R site/plugins/pagefactory/install/content/  content
	cp -R site/plugins/pagefactory/install/site/     site
	mv content/home/home.txt content/home/zzz_page.txt
	mv content/home content/1_home
	echo Essential files copied to final location
fi

echo
echo Now open this website in your browser.
echo 
