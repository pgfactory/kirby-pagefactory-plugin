#!/usr/bin/env bash

if [[ -z "$1" ]]; then
	echo 
	echo "Installation script for Kirby/Pagefactory"
	echo "-----------------------------------------"
	echo "Usage:"
	echo "  install.sh  path-to-webapp-folder  {branch}"
	echo "     path-to-webapp-folder='.' for current folder"
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

# Invoke Pagefactory installation script (assumed to be in same location as install.sh):
thisScript=$0
path=${thisScript%/*}
$path/install-pagefactory.sh $2
