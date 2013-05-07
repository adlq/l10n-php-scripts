@echo OFF
setlocal ENABLEDELAYEDEXPANSION

REM Prepare csv output file
echo FileName;TotalStrings;NotTranslatedStrings;TotalWordCount;NotTranslatedWords > buildPoOutput.csv

REM Has the user given any locales?
if "%1"=="" (
	echo No files given
	exit /B
)

REM Loop through given locales
:Loop
if "%1"=="" goto Continue
	REM Retrieve xlf file name
	set fileName=%1
	if not x%fileName:.xlf=%==x%fileName% (
		echo !fileName!
		
		REM Assertion: the xlf files are named {locale}.xlf
		REM Extract locale name
		set locale=%fileName:.xlf=%
		echo !locale!
		
		if exist !fileName! (
			REM Summon the PHP script that converts the given xlf file into a regular one
			php lb2xliff.php !fileName! en-GB !locale!_temp.xlf !locale! csv >> buildPoOutput.csv
			
			REM Convert the resulting xlf file to a PO file
			xliff2po !locale!_temp.xlf !locale!.po
			
			REM Delete temporary files
			del !locale!_temp.xlf
		)
	)
shift
goto Loop
:Continue

endlocal