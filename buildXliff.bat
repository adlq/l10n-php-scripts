@echo OFF
setlocal ENABLEDELAYEDEXPANSION

REM Prepare csv output file
echo FileName;TotalStrings;NotTranslatedStrings;TotalWordCount;NotTranslatedWords > buildXliffOutput.csv

set po=%1
REM Check if the first argument is a .po file
if x%po:.po=%==x%po% (
	echo First argument must be a .po file
	exit /B
) else (
	REM Does the given po file exist?
	if not exist !po! (
		echo The given .po file doesn't exist
		exit /B
	)
	REM Has the user given any locales? 
	if "%2"=="" (
		echo No locales given
		exit /B
	)
)

REM The given PO file must be up to date!
REM We merge the duplicate entries in the given PO file
msguniq --use-first --no-wrap %1 -o %1

REM Loop through given locales
:Loop
if "%2"=="" goto Continue
	
REM Path to locale folder
set POFILE=..\source\locales\%2

REM If the appropriate locale folder exists in ..\source\locales
REM Then we use the lang.po file in it if it exists
REM Otherwise, we use the given template file
if exist !POFILE! (
	if exist ..\source\locales\%2\lang.po (
		set POFILE=..\source\locales\%2\lang.po
	) else (
		set POFILE=!po!
	)
) else (
	set POFILE=!po!
)

echo !POFILE!

REM Merge duplicate entries in the original PO file
msguniq --use-first --no-wrap !POFILE! -o from_en-GB_to_%2uniq.po

REM Merge the PO file with new entries that can potentially come from the updated lang.po file
msgmerge from_en-GB_to_%2uniq.po !po! --update --no-wrap --no-location --sort-output

REM Filter out obsolete comments from the unique entries in the PO file
REM msgattrib --no-obsolete --no-wrap --no-location !po! -o clean.po
msgattrib --no-obsolete --no-wrap --no-location from_en-GB_to_%2uniq.po -o from_en-GB_to_%2clean.po

REM Convert the 'clean' PO file to a generic xliff file
po2xliff from_en-GB_to_%2clean.po %2_raw.xlf

REM Summon the PHP script that converts the resulting xliff file into LionBridge-compatible file
php xliff2lb.php %2_raw.xlf en-GB %2.xlf %2 csv >> buildXliffOutput.csv

REM Delete temporary files
del %2_raw.xlf from_en-GB_to_%2uniq.po* from_en-GB_to_%2clean.po*

shift
goto Loop
:Continue


endlocal