@echo OFF
setlocal ENABLEDELAYEDEXPANSION

REM Prepare csv output file
echo FileName;TotalStrings;NotTranslatedStrings;TotalWordCount;NotTranslatedWords > buildPoOutput.csv

REM Has the user given any files?
if "%1"=="" (
	echo No files given
	exit /B
)

if "%2"=="" (
	echo No locales given
	exit /B
)

REM Retrieve xlf file name
set fileName=%1

REM Extract locale name
set locale=%2

REM Extract output file name 
set output=%3

if not x%fileName:.xlf=%==x%fileName% (
	if exist !fileName! (
		REM Summon the PHP script that converts the given xlf file into a regular one
		php lb2xliff.php !fileName! en-GB !locale!_temp.xlf !locale! csv >> buildPoOutput.csv
		
		REM Convert the resulting xlf file to a PO file
		xliff2po !locale!_temp.xlf !locale!.po
		
		msgmerge !locale!.po !locale!.po --no-location --no-wrap --sort-output -o !output!
		
		REM Delete temporary files
		del !locale!_temp.xlf !locale!.po
	)
)

endlocal