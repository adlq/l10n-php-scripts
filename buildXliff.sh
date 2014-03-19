#!/bin/bash

# PO to XLIFF conversion tool

# Supported locales in CKLS
locales=(de-DE es-ES fi_FI fr_FR it-IT ja-JP nb_NO nl-NL pl-PL pt-BR ru-RU sv_SE zh-CHS)

if [ $# -ne 3 ]
then
  echo "Usage: `basename $0` input_file.po locale output_file.xlf"
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

po_inputfile=$1
locale=$2
xlf_outputfile=$3

# Check if the first argument is a .po file
if ! [[ $po_inputfile =~ \.po$ ]];
then
    echo "First argument should be the an file with .po extension"
    exit 1
fi

# Check  given po file exist
if ! [ -f $po_inputfile ]
then
    echo "The given po file ($po_inputfile) does not exist"
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

# Step 1:         msguniq: Unifies duplicate translations in a translation catalog.
#   --no-location           do not write '#: filename:line' lines
#   --no-wrap               do not break long message lines, longer than the output page width, into several lines
#   -s, --sort-output       generate sorted output
#   -o, --output-file=FILE      write output to specified file

msguniq --no-location --no-wrap --sort-output $po_inputfile -o $xlf_outputfile.uniq.po


# Step 2:         msgattrib: Filters the messages of a translation catalog according to their attributes, and manipulates the attributes.
#   --no-obsolete           remove obsolete #~ messages
#   --no-wrap               do not break long message lines, longer than the output page width, into several lines
#   --no-location           do not write '#: filename:line' lines
#   -s, --sort-output           generate sorted output

msgattrib --no-obsolete --no-wrap --no-location --sort-output $xlf_outputfile.uniq.po -o $xlf_outputfile.clean.po

# Step 3:        Convert the 'clean' PO file to a generic xliff file
po2xliff $xlf_outputfile.clean.po $xlf_outputfile.raw.xlf

#  Prepare csv output file
echo "FileName;TotalStrings;NotTranslatedStrings;TotalWordCount;NotTranslatedWords" > buildXliffOutput.csv

# Step4:         Summon the PHP script that converts the resulting xliff file into LionBridge-compatible file
php xliff2lb.php $xlf_outputfile.raw.xlf en-GB $xlf_outputfile $locale csv >> buildXliffOutput.csv

# Delete temporary files
rm $xlf_outputfile.raw.xlf $xlf_outputfile.uniq.po $xlf_outputfile.clean.po