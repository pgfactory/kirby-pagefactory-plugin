#!/usr/bin/env bash
# Usage:
#   install.sh path-to-webapp-folder
#
# if arg is omitted, script assumes you already navigated to your new web-app folder.
# NOTE: in that case the folder must be empty, i.e. the install.sh script must
# be stored somewhere else.

if [[ -z "$1" ]]; then
	echo "Usage:"
	echo   install.sh path-to-webapp-folder
	echo
	exit
fi

cwd=`pwd`
if [[ "$1" != "." ]]; then
	if [[ ! -e $1 ]]; then
		mkdir $1
	fi
	if [[ ! -d $1 ]]; then
		echo Target folder could not be created
		echo
		exit
	fi
	cd $1
fi

if [[ -n "$(ls -A)" &&  ! -d kirby/ ]]; then
   echo "Not empty - to install Kirby, folder must be empty"
   exit
fi


## Check/clone Kirby plainkit:
if [[ ! -e kirby/ ]]; then
	echo "Now installing Kirby to folder -> `pwd`"
	/usr/local/bin/git clone https://github.com/getkirby/plainkit.git .
	echo Kirby installed
else
	echo Kirby already installed
fi

$cwd/install-pagefactory.sh $2
