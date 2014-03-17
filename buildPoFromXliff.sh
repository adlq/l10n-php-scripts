#!/bin/bash

# XLIFF to PO conversion tool

# Supported locales in CKLS
locales=(de-DE es-ES it-IT ja-JP nl-NL pl-PL pt-BR ru-RU zh-CHS)

if [ $# -ne 3 ]
then
  echo "Usage: `basename $0` input_file.xlf locale output_file.po"
  exit 1
fi

# Check if a value exists in an array
# @param $1 mixed  Needle  
# @param $2 array  Haystack
# @return  Success (0) if value exists, Failure (1) otherwise
# Usage: in_array "$needle" "${haystack[@]}"
# See: http://fvue.nl/wiki/Bash:_Check_if_array_element_exists

in_array() {
    local hay needle=$1
    shift
    for hay; do
        if [[ $hay == $needle ]]
        then
            return 0
        fi
    done
    return 1
}

xlf_inputfile=$1
locale=$2
po_outputfile=$3


# Check if the first argument is a .xlf file
if ! [[ $xlf_inputfile =~ \.xlf$ ]];
then
    echo "First argument should be the an file with .xlf extension"
    exit 1
fi

# Check  given xlf file exist
if ! [ -f $xlf_inputfile ]
then
    echo "The given xlf file ($xlf_inputfile) does not exist"
    exit 1
fi

# Check given locale is supported
in_array "$locale" "${locales[@]}"
if [[ "$?" -ne "0" ]]
then  
    echo "$locale is not a supported locale"
    exit 1
fi

# We can proceed to conversion

# Prepare csv output file
echo "FileName;TotalStrings;NotTranslatedStrings;TotalWordCount;NotTranslatedWords" > buildPoOutput.csv

# Summon the PHP script that converts the given xlf file into a regular one

php lb2xliff.php $xlf_inputfile en-GB $locale.temp.xlf $locale csv >> buildPoOutput.csv

# Convert the resulting xlf file to a PO file
xliff2po $locale.temp.xlf $locale.po

msguniq $locale.po --no-location --no-wrap --sort-output -o $po_outputfile

#  Delete temporary files
rm $locale.temp.xlf $locale.po