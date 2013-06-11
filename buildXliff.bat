@echo OFF
setlocal ENABLEDELAYEDEXPANSION

REM Prepare csv output file
echo FileName;TotalStrings;NotTranslatedStrings;TotalWordCount;NotTranslatedWords > buildXliffOutput.csv

set po=%1
set locale=%2
set output=%3

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

msguniq --no-location --no-wrap --sort-output !po! -o !output!uniq.po

msgattrib --no-obsolete --no-wrap --no-location --sort-output !output!uniq.po -o !output!clean.po
REM msgattrib --no-obsolete --no-wrap --no-location --sort-output !po! -o !output!clean.po

REM Convert the 'clean' PO file to a generic xliff file
po2xliff !output!clean.po !output!raw.xlf

REM Summon the PHP script that converts the resulting xliff file into LionBridge-compatible file
php xliff2lb.php !output!raw.xlf en-GB !output!.xlf !locale! csv >> buildXliffOutput.csv

REM Delete temporary files
del !output!raw.xlf !output!uniq.po !output!clean.po 

endlocal