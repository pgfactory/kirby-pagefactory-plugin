#!/usr/bin/env bash
#
# Installation script for Kirby and Pagefactory-plugin
#
cwd=`pwd`

if [[ -z "$1" ]]; then
	echo 
	echo "Installation script for Kirby and Pagefactory-plugin"
	echo "----------------------------------------------------"
	echo "Usage:"
	echo "  install.sh  path-to-myapp-folder  {branch}"
	echo "     path-to-myapp-folder='.' for current folder"
	echo "     branch is optional"
	echo 
	exit
fi

if [[ "$1" != "." ]]; then
	if [[ ! -e $1 ]]; then
		mkdir $1
	fi
	if [[ ! -d $1 ]]; then
		echo Target folder could not be created
		echo
		exit
	fi
	
	## cd to app-root folder:
	cd $1
fi

appRoot="$cwd/$1"


if [[ -n "$(ls -A)" &&  ! -d kirby/ ]]; then
   echo "Not empty - to install Kirby, folder must be empty"
   exit
fi


## Check/clone Kirby plainkit:
if [[ ! -e kirby/ ]]; then
	echo "Now installing Kirby to folder -> `pwd`"
	/usr/bin/env git clone https://github.com/getkirby/plainkit.git .
	echo Kirby installed
else
	echo Kirby already installed
fi


## select the branch you want to check out:
if [[ -z "$2" ]]; then
	branch=''
else
	branch="-b $2"
fi

## Check pagefactory:
if [[ -e site/plugins/pagefactory/ ]]; then
	echo Pagefactory already installed
	echo 
	exit
fi

echo
echo Now installing Pagefactory

## Clone PageFactory:

echo
echo Kirby-Twig:
/usr/bin/env git submodule add https://github.com/wearejust/kirby-twig.git site/plugins/kirby-twig
/usr/bin/env composer update --working-dir=$appRoot/site/plugins/kirby-twig

echo
echo MarkdownPlus:
/usr/bin/env git submodule add https://github.com/pgfactory/markdownplus.git site/plugins/markdownplus
/usr/bin/env composer update --working-dir=$appRoot/site/plugins/markdownplus

echo
echo PageFactory:
/usr/bin/env git submodule add $branch https://github.com/pgfactory/kirby-pagefactory-plugin.git site/plugins/pagefactory
/usr/bin/env composer update --working-dir=$appRoot/site/plugins/pagefactory

echo
echo PageFactory installed


## Check/copy essential files to final location:
if [ ! -e site/templates/page_template.html ]; then
	cp -R site/plugins/pagefactory/install/content/  content
	cp -R site/plugins/pagefactory/install/site/     site
	mv content/home/home.txt content/home/z.txt
	mv content/home content/1_home
	echo Essential files copied to final location
fi

echo
echo => Now open this website in your browser.
echo 
